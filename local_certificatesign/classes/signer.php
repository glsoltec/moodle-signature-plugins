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

        return self::byte_range_sign($pdfcontent, $certs['cert'], $certs['pkey'], $certinfo);
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

        return [
            'cn'          => $cn,
            'location'    => $location,
            'org'         => $certdata['subject']['O'] ?? '',
            'validfrom'   => $certdata['validFrom_time_t'] ?? 0,
            'validto'     => $certdata['validTo_time_t'] ?? 0,
            'issuer'      => $certdata['issuer']['CN'] ?? '',
            'fingerprint' => strtoupper(openssl_x509_fingerprint($certs['cert'])),
        ];
    }

    private static function byte_range_sign(string $pdfcontent, string $cert, string $pkey, array $certinfo): string {
        $signername = $certinfo['cn'] ?? '';
        $location   = $certinfo['location'] ?? '';
        $reason     = get_config('local_certificatesign', 'signerreason') ?: 'Certificate';

        $pdfcontent = str_replace("\r\n", "\n", $pdfcontent);
        $pdfcontent = rtrim($pdfcontent) . "\n";

        $sig_max_hex = 25000;
        $hex_placeholder = str_repeat('0', $sig_max_hex);
        $content_byte_len = $sig_max_hex / 2;
        $sig_obj_num = 999999;

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
        $sig_content = $sig_obj_num . " 0 obj\n" . $sig_obj . "\nendobj\n";
        $byte_range_2 = strlen($sig_content) + 2;

        $sig_obj_sized = sprintf($sig_obj,
            $content_before_sig,
            $content_before_sig + $byte_range_2 + $content_byte_len,
            0
        );

        $sig_content = $sig_obj_num . " 0 obj\n" . $sig_obj_sized . "\nendobj\n";
        $new_xref_offset = strlen($pdfcontent) + strlen($sig_content) + 1;
        $next_obj = $sig_obj_num + 1;

        $trailer_obj = "{$next_obj} 0 obj\n"
            . "<< /Type /Catalog /AcroForm << /Fields [{$sig_obj_num} 0 R] /SigFlags 3 >>"
            . " /Perms << /DocMDP << /P /SigQ /V 2"
            . " /Reference [{/Type /SigRef /TransformMethod /DocMDP"
            . " /TransformParams << /P /SigQ /V /2 /Type /TransformParams >>}] >> >> >>\nendobj\n";

        $xref = "xref\n0 0\n";
        $xref .= "{$sig_obj_num} 1\n" . sprintf("%010d %05d n \n", $content_before_sig + 1, 0);
        $xref .= "{$next_obj} 1\n" . sprintf("%010d %05d n \n", $content_before_sig + 1 + strlen($sig_content) + 1, 0);

        $trailer_content = "trailer\n<< /Size {$next_obj} /Root {$next_obj} 0 R >>\n"
            . "startxref\n{$new_xref_offset}\n%%EOF";

        $full_pdf = $pdfcontent . "\n" . $sig_content . $trailer_obj . $xref . $trailer_content;
        $byterange_end = $content_before_sig + $byte_range_2 + $content_byte_len;

        $data_to_sign = substr($full_pdf, 0, $content_before_sig)
            . substr($full_pdf, $content_before_sig + $byte_range_2,
                $byterange_end - ($content_before_sig + $byte_range_2));

        $pkcs7_der = self::create_pkcs7_signature($data_to_sign, $cert, $pkey);
        $signature_hex = bin2hex($pkcs7_der);

        return str_replace($hex_placeholder, $signature_hex, $full_pdf);
    }

    /**
     * Create a PKCS#7 detached signature using PHP's built-in OpenSSL.
     */
    private static function create_pkcs7_signature(string $data, string $cert, string $pkey): string {
        $tmpdir = make_temp_directory('certificatesign');
        $infile  = $tmpdir . '/' . uniqid('sig_in_') . '.bin';
        $outfile = $tmpdir . '/' . uniqid('sig_out_') . '.p7s';

        file_put_contents($infile, $data);

        $result = openssl_pkcs7_sign(
            $infile,
            $outfile,
            $cert,
            $pkey,
            [],
            PKCS7_DETACHED | PKCS7_BINARY
        );

        @unlink($infile);

        if (!$result) {
            @unlink($outfile);
            $err = openssl_error_string();
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', $err ?: '');
        }

        $output = file_get_contents($outfile);
        @unlink($outfile);

        if ($output === false || strlen($output) < 10) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign');
        }

        if (strpos($output, '-----BEGIN PKCS7-----') !== false) {
            $output = str_replace(["\r\n", "\r"], "\n", $output);
            if (preg_match('/-----BEGIN PKCS7-----.*?\n(.+)\n-----END PKCS7-----/s', $output, $m)) {
                return base64_decode(str_replace("\n", '', $m[1]));
            }
        }

        if (substr(bin2hex($output), 0, 2) === '30') {
            return $output;
        }

        throw new \moodle_exception('erroropenssl', 'local_certificatesign');
    }

    private static function pdf_escape(string $value): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
