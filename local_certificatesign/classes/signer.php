<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

class signer {

    public static function sign_pdf(string $pdfcontent): string {
        $pfxcontent = self::get_pfx_content();
        if ($pfxcontent === null) {
            throw new \moodle_exception('notconfigured', 'local_certificatesign');
        }

        $password = get_config('local_certificatesign', 'certpassword');

        $certs = self::read_pfx($pfxcontent, $password);

        $certinfo = self::get_cert_info($pfxcontent, $password);

        return self::byte_range_sign($pdfcontent, $certs['cert'], $certs['pkey'], $password, $certinfo);
    }

    public static function get_pfx_content(): ?string {
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $files = $fs->get_area_files($syscontext->id, 'local_certificatesign', 'pfxfile', 0, 'id DESC', false);

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        return $file->get_content();
    }

    public static function read_pfx(string $pfxcontent, string $password): array {
        $certs = [];
        if (!openssl_pkcs12_read($pfxcontent, $certs, $password)) {
            throw new \moodle_exception('errorreadingpfx', 'local_certificatesign');
        }
        return $certs;
    }

    public static function get_cert_info(string $pfxcontent, string $password): array {
        $certs = self::read_pfx($pfxcontent, $password);
        $certdata = openssl_x509_parse($certs['cert']);

        $cn = $certdata['subject']['CN'] ?? '';
        $location = '';
        if (!empty($certdata['subject']['L'])) {
            $location = $certdata['subject']['L'];
        } else if (!empty($certdata['subject']['ST'])) {
            $location = $certdata['subject']['ST'];
        }
        if (!empty($certdata['subject']['O'])) {
            $location = $location ? "{$location} — {$certdata['subject']['O']}" : $certdata['subject']['O'];
        }

        $validfrom = $certdata['validFrom_time_t'] ?? 0;
        $validto = $certdata['validTo_time_t'] ?? 0;
        $issuer = $certdata['issuer']['CN'] ?? '';

        return [
            'cn'          => $cn,
            'location'    => $location,
            'org'         => $certdata['subject']['O'] ?? '',
            'validfrom'   => $validfrom,
            'validto'     => $validto,
            'issuer'      => $issuer,
            'fingerprint' => strtoupper(openssl_x509_fingerprint($certs['cert'])),
        ];
    }

    public static function validate_password(string $password): ?string {
        $pfxcontent = self::get_pfx_content();
        if ($pfxcontent === null) {
            return null;
        }
        try {
            self::read_pfx($pfxcontent, $password);
            return null;
        } catch (\moodle_exception $e) {
            return $e->getMessage();
        }
    }

    public static function generate_self_signed(string $cn, string $org, string $country, string $password): string {
        $tmpdir = make_temp_directory('certificatesign');
        $keypath  = $tmpdir . '/' . uniqid('genkey_') . '.pem';
        $certpath = $tmpdir . '/' . uniqid('gencert_') . '.pem';
        $pfxpath  = $tmpdir . '/' . uniqid('genpfx_') . '.pfx';

        $subj = "/CN={$cn}";
        if ($org) {
            $subj .= "/O={$org}";
        }
        if ($country) {
            $subj .= "/C={$country}";
        }

        $cmd = "openssl req -x509 -newkey rsa:2048 -keyout " . escapeshellarg($keypath)
            . " -out " . escapeshellarg($certpath)
            . " -days 3650 -nodes -subj " . escapeshellarg($subj)
            . " 2>&1";
        exec($cmd, $output, $exitcode);
        if ($exitcode !== 0) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', implode("\n", $output));
        }

        $cmd2 = "openssl pkcs12 -export -in " . escapeshellarg($certpath)
            . " -inkey " . escapeshellarg($keypath)
            . " -out " . escapeshellarg($pfxpath)
            . " -passout pass:" . escapeshellarg($password)
            . " 2>&1";
        exec($cmd2, $output2, $exitcode2);
        if ($exitcode2 !== 0) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', implode("\n", $output2));
        }

        $pfxcontent = file_get_contents($pfxpath);

        @unlink($keypath);
        @unlink($certpath);
        @unlink($pfxpath);

        return $pfxcontent;
    }

    private static function byte_range_sign(string $pdfcontent, string $cert, string $pkey, string $password, array $certinfo): string {
        $signername   = $certinfo['cn'] ?? '';
        $location     = $certinfo['location'] ?? '';
        $reason       = get_config('local_certificatesign', 'signerreason') ?: 'Certificate';

        $pdfcontent = str_replace("\r\n", "\n", $pdfcontent);
        $pdfcontent = rtrim($pdfcontent) . "\n";

        $sig_max_hex = 25000;
        $hex_placeholder = str_repeat('0', $sig_max_hex);
        $content_byte_len = $sig_max_hex / 2;
        $signature_obj_num = 999999;

        $sig_obj = "<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached";
        if ($signername) {
            $sig_obj .= " /Name (" . self::pdf_escape($signername) . ")";
        }
        if ($location) {
            $sig_obj .= " /Location (" . self::pdf_escape($location) . ")";
        }
        if ($reason) {
            $sig_obj .= " /Reason (" . self::pdf_escape($reason) . ")";
        }
        $sig_obj .= " /M (D:" . date('YmdHisP') . ")";
        $sig_obj .= " /ByteRange [0 %d %d %d]";
        $sig_obj .= " /Contents <" . $hex_placeholder . "> >>";

        $content_before_sig = strlen($pdfcontent);
        $sig_content = $signature_obj_num . " 0 obj\n" . $sig_obj . "\nendobj\n";

        $byte_range_2 = strlen($sig_content) + 2;

        $sig_content_sized = sprintf($sig_obj,
            $content_before_sig,
            $content_before_sig + $byte_range_2 + $content_byte_len,
            0
        );

        $sig_content = $signature_obj_num . " 0 obj\n" . $sig_content_sized . "\nendobj\n";
        $new_xref_offset = strlen($pdfcontent) + strlen($sig_content) + 1;
        $next_obj = $signature_obj_num + 1;

        $trailer = "{$next_obj} 0 obj\n<< /Type /Catalog /AcroForm << /Fields [{$signature_obj_num} 0 R] /SigFlags 3 >> /Perms << /DocMDP << /P /SigQ /V 2 /Reference [{/Type /SigRef /TransformMethod /DocMDP /TransformParams << /P /SigQ /V /2 /Type /TransformParams >>}] >> >> >>\nendobj\n";

        $xref = "xref\n0 0\n";
        $xref .= "{$signature_obj_num} 1\n" . sprintf("%010d %05d n \n", $content_before_sig + 1, 0);
        $xref .= "{$next_obj} 1\n" . sprintf("%010d %05d n \n", $content_before_sig + 1 + strlen($sig_content) + 1, 0);

        $trailer_content = "trailer\n<< /Size {$next_obj} /Root {$next_obj} 0 R >>\nstartxref\n{$new_xref_offset}\n%%EOF";

        $full_pdf = $pdfcontent . "\n" . $sig_content . $trailer . $xref . $trailer_content;

        $byterange_end = $content_before_sig + $byte_range_2 + $content_byte_len;
        $data_to_sign = substr($full_pdf, 0, $content_before_sig)
            . substr($full_pdf, $content_before_sig + $byte_range_2, $byterange_end - ($content_before_sig + $byte_range_2));

        $tmpdir = make_temp_directory('certificatesign');
        $tmpfile = $tmpdir . '/' . uniqid('pdfsig_') . '.bin';
        file_put_contents($tmpfile, $data_to_sign);
        $signedfile = $tmpdir . '/' . uniqid('pdfsig_res_');
        $certfile = self::create_temp_file($cert, $tmpdir);
        $keyfile = self::create_temp_file($pkey, $tmpdir);

        $openssl_cmd = "openssl smime -sign -in " . escapeshellarg($tmpfile)
            . " -signer " . escapeshellarg($certfile)
            . " -inkey " . escapeshellarg($keyfile)
            . " -out " . escapeshellarg($signedfile)
            . " -binary -outform DER 2>&1";

        exec($openssl_cmd, $output, $exitcode);
        if ($exitcode !== 0) {
            foreach ([$tmpfile, $signedfile, $certfile, $keyfile] as $f) { @unlink($f); }
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', implode("\n", $output));
        }

        $signature_der = file_get_contents($signedfile);
        if ($signature_der === false || strlen($signature_der) === 0) {
            foreach ([$tmpfile, $signedfile, $certfile, $keyfile] as $f) { @unlink($f); }
            throw new \moodle_exception('erroropenssl', 'local_certificatesign');
        }

        $signature_hex = bin2hex($signature_der);
        $signed_pdf = str_replace($hex_placeholder, $signature_hex, $full_pdf);

        foreach ([$tmpfile, $signedfile, $certfile, $keyfile] as $f) { @unlink($f); }

        return $signed_pdf;
    }

    private static function pdf_escape(string $value): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private static function create_temp_file(string $content, string $tmpdir): string {
        $path = $tmpdir . '/' . uniqid('tmp_') . '.pem';
        file_put_contents($path, $content);
        return $path;
    }
}
