<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

class signer {

    public static function sign_pdf(string $pdfcontent): string {
        $pfx = self::get_pfx_content();
        if ($pfx === null) {
            throw new \moodle_exception('notconfigured', 'local_certificatesign');
        }
        $pw = get_config('local_certificatesign', 'certpassword');
        $c = self::read_pfx($pfx, $pw);
        $info = self::get_cert_info($pfx, $pw);

        $pdf = str_replace("\r\n", "\n", $pdfcontent);
        $pdf = rtrim($pdf) . "\n";

        $maxhex = 25000;
        $ph = str_repeat('0', $maxhex);
        $objnum = 999999;
        $name = self::pesc($info['cn'] ?? '');
        $loc  = self::pesc($info['location'] ?? '');
        $reason = self::pesc(get_config('local_certificatesign', 'signerreason') ?: 'Certificate');

        $sig = "<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached";
        if ($name) { $sig .= " /Name ($name)"; }
        if ($loc)  { $sig .= " /Location ($loc)"; }
        if ($reason) { $sig .= " /Reason ($reason)"; }
        $sig .= " /M (D:" . date('YmdHisP') . ")";
        $sig .= " /ByteRange [0 %d %d %d]";
        $sig .= " /Contents <$ph> >>";

        $before = strlen($pdf);
        $scount = strlen($objnum . " 0 obj\n" . $sig . "\nendobj\n") + 2;

        $sized = $objnum . " 0 obj\n"
            . sprintf($sig, $before, $before + $scount + $maxhex / 2, 0)
            . "\nendobj\n";

        $no = $objnum + 1;
        $xo = strlen($pdf) + strlen($sized) + 1;

        $tobj = "$no 0 obj\n<< /Type /Catalog /AcroForm << /Fields [$objnum 0 R] /SigFlags 3 >> /Perms << /DocMDP << /P /SigQ /V 2 /Reference [{/Type /SigRef /TransformMethod /DocMDP /TransformParams << /P /SigQ /V /2 /Type /TransformParams >>}] >> >> >>\nendobj\n";

        $xr = "xref\n0 0\n{$objnum} 1\n" . sprintf("%010d %05d n \n", $before + 1, 0)
            . "$no 1\n" . sprintf("%010d %05d n \n", $before + 1 + strlen($sized) + 1, 0);

        $tr = "trailer\n<< /Size $no /Root $no 0 R >>\nstartxref\n$xo\n%%EOF";

        $full = $pdf . "\n" . $sized . $tobj . $xr . $tr;
        $be = $before + $scount + $maxhex / 2;

        $sign = substr($full, 0, $before)
            . substr($full, $before + $scount, $be - ($before + $scount));

        $pkcs7 = self::make_pkcs7($sign, $c['cert'], $c['pkey'], $pw);

        return str_replace($ph, bin2hex($pkcs7), $full);
    }

    private static function make_pkcs7(string $data, string $cert, string $pkey, string $pw): string {
        $td = sys_get_temp_dir();
        $in  = tempnam($td, 'csign_');
        $out = tempnam($td, 'csign_');

        file_put_contents($in, $data);

        $key = openssl_pkey_get_private($pkey, $pw);
        if ($key === false) {
            @unlink($in); @unlink($out);
            throw new \moodle_exception('errorreadingpfx', 'local_certificatesign');
        }

        $r = openssl_pkcs7_sign($in, $out, $cert, $key, [], PKCS7_DETACHED);

        @unlink($in);

        if (!$r) {
            @unlink($out);
            $err = openssl_error_string();
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', $err ?: '');
        }

        $raw = file_get_contents($out);
        @unlink($out);

        if ($raw === false || strlen($raw) < 10) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign');
        }

        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        if (preg_match('/-----BEGIN PKCS7-----.*?\n(.+)\n-----END PKCS7-----/s', $raw, $m)) {
            return base64_decode(str_replace("\n", '', $m[1]));
        }

        if (bin2hex(substr($raw, 0, 1)) === '30') {
            return $raw;
        }

        throw new \moodle_exception('erroropenssl', 'local_certificatesign');
    }

    public static function get_pfx_content(): ?string {
        $fs = get_file_storage();
        $f = $fs->get_area_files(\context_system::instance()->id, 'local_certificatesign', 'pfxfile', 0, 'id DESC', false);
        return empty($f) ? null : reset($f)->get_content();
    }

    public static function read_pfx(string $pfx, string $pw): array {
        $c = [];
        if (!openssl_pkcs12_read($pfx, $c, $pw)) {
            throw new \moodle_exception('errorreadingpfx', 'local_certificatesign');
        }
        return $c;
    }

    public static function get_cert_info(string $pfx, string $pw): array {
        $c = self::read_pfx($pfx, $pw);
        $d = openssl_x509_parse($c['cert']);
        $cn = $d['subject']['CN'] ?? '';
        $l = '';
        if (!empty($d['subject']['L'])) { $l = $d['subject']['L']; }
        elseif (!empty($d['subject']['ST'])) { $l = $d['subject']['ST']; }
        if (!empty($d['subject']['O'])) { $l = $l ? "$l - {$d['subject']['O']}" : $d['subject']['O']; }
        return [
            'cn' => $cn, 'location' => $l, 'org' => $d['subject']['O'] ?? '',
            'validfrom' => $d['validFrom_time_t'] ?? 0, 'validto' => $d['validTo_time_t'] ?? 0,
            'issuer' => $d['issuer']['CN'] ?? '',
            'fingerprint' => strtoupper(openssl_x509_fingerprint($c['cert'])),
        ];
    }

    public static function validate_password(string $password): ?string {
        $pfx = self::get_pfx_content();
        if ($pfx === null) { return null; }
        try {
            self::read_pfx($pfx, $password);
            return null;
        } catch (\moodle_exception $e) {
            return $e->getMessage();
        }
    }

    private static function pesc(string $v): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $v);
    }
}
