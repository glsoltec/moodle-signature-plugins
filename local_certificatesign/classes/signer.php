<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

class signer {

    /**
     * Digitally signs an existing PDF and returns the signed binary.
     *
     * The previous implementation built ByteRange/xref/trailer manually. This
     * implementation delegates the PDF signature structure to TCPDF and uses
     * FPDI only to import the existing generated PDF pages.
     */
    public static function sign_pdf(string $pdfcontent): string {
        global $CFG;

        self::require_libraries();

        $pfx = self::get_pfx_content();
        $pw = (string)get_config('local_certificatesign', 'certpassword');
        if ($pfx === null || $pw === '') {
            throw new \moodle_exception('notconfigured', 'local_certificatesign');
        }

        $certs = self::read_pfx($pfx, $pw);
        $info = self::get_cert_info($pfx, $pw);
        $reason = get_config('local_certificatesign', 'signerreason') ?: 'Certificate';

        $tmpdir = make_temp_directory('local_certificatesign');
        $certfile = tempnam($tmpdir, 'cert_');
        $keyfile = tempnam($tmpdir, 'key_');
        $extrafile = tempnam($tmpdir, 'extra_');

        try {
            file_put_contents($certfile, $certs['cert']);
            file_put_contents($keyfile, $certs['pkey']);

            $extracerts = '';
            if (!empty($certs['extracerts']) && is_array($certs['extracerts'])) {
                $extracerts = implode("\n", $certs['extracerts']);
            }
            file_put_contents($extrafile, $extracerts);

            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->setFontSubsetting(false);
            $pdf->SetCreator('Moodle local_certificatesign');
            $pdf->SetAuthor($info['cn'] ?? '');
            $pdf->SetTitle('Signed certificate');

            $signatureinfo = [
                'Name' => $info['cn'] ?? '',
                'Location' => $info['location'] ?? '',
                'Reason' => $reason,
                'ContactInfo' => '',
            ];

            $pdf->setSignature(
                'file://' . $certfile,
                'file://' . $keyfile,
                '',
                $extracerts !== '' ? 'file://' . $extrafile : '',
                2,
                $signatureinfo
            );

            try {
                $pagecount = $pdf->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($pdfcontent));
            } catch (\Throwable $e) {
                throw new \moodle_exception('errorpdfparse', 'local_certificatesign', '', $e->getMessage());
            }

            for ($page = 1; $page <= $pagecount; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($template, 0, 0, $size['width'], $size['height'], true);
            }

            return $pdf->Output('', 'S');
        } finally {
            foreach ([$certfile, $keyfile, $extrafile] as $file) {
                if ($file && file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    private static function require_libraries(): void {
        global $CFG;

        $tcpdf = $CFG->libdir . '/tcpdf/tcpdf.php';
        if (is_readable($tcpdf)) {
            require_once($tcpdf);
        } else {
            require_once($CFG->libdir . '/pdflib.php');
        }

        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (is_readable($autoload)) {
            require_once($autoload);
        }

        if (!class_exists('\\setasign\\Fpdi\\Tcpdf\\Fpdi')) {
            throw new \moodle_exception('errornofpdi', 'local_certificatesign');
        }
    }

    public static function get_pfx_content(): ?string {
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'local_certificatesign', 'pfxfile', 0, 'id DESC', false);
        return empty($files) ? null : reset($files)->get_content();
    }

    public static function read_pfx(string $pfx, string $pw): array {
        $certs = [];
        if (!openssl_pkcs12_read($pfx, $certs, $pw)) {
            throw new \moodle_exception('errorreadingpfx', 'local_certificatesign');
        }
        return $certs;
    }

    public static function get_cert_info(string $pfx, string $pw): array {
        $certs = self::read_pfx($pfx, $pw);
        $parsed = openssl_x509_parse($certs['cert']);
        if ($parsed === false) {
            throw new \moodle_exception('invalidpfx', 'local_certificatesign');
        }

        $location = '';
        if (!empty($parsed['subject']['L'])) {
            $location = $parsed['subject']['L'];
        } else if (!empty($parsed['subject']['ST'])) {
            $location = $parsed['subject']['ST'];
        }
        if (!empty($parsed['subject']['O'])) {
            $location = $location ? $location . ' - ' . $parsed['subject']['O'] : $parsed['subject']['O'];
        }

        return [
            'cn' => $parsed['subject']['CN'] ?? '',
            'location' => $location,
            'org' => $parsed['subject']['O'] ?? '',
            'validfrom' => $parsed['validFrom_time_t'] ?? 0,
            'validto' => $parsed['validTo_time_t'] ?? 0,
            'issuer' => $parsed['issuer']['CN'] ?? '',
            'fingerprint' => strtoupper(openssl_x509_fingerprint($certs['cert'])),
        ];
    }

    public static function validate_password(string $password): ?string {
        $pfx = self::get_pfx_content();
        if ($pfx === null) {
            return null;
        }
        try {
            self::read_pfx($pfx, $password);
            return null;
        } catch (\moodle_exception $e) {
            return $e->getMessage();
        }
    }

    public static function generate_self_signed(string $cn, string $org, string $country, string $password): string {
        $cn = trim($cn);
        $org = trim($org);
        $country = strtoupper(trim($country));

        if ($cn === '') {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', 'Common Name is required.');
        }
        if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', 'Country must use a 2-letter ISO code.');
        }

        $dn = ['commonName' => $cn];
        if ($org !== '') {
            $dn['organizationName'] = $org;
        }
        if ($country !== '') {
            $dn['countryName'] = $country;
        }

        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
            'digest_alg' => 'sha256',
        ];

        $privatekey = openssl_pkey_new($config);
        if ($privatekey === false) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', self::openssl_errors());
        }

        $csr = openssl_csr_new($dn, $privatekey, $config);
        if ($csr === false) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', self::openssl_errors());
        }

        $certificate = openssl_csr_sign($csr, null, $privatekey, 3650, $config);
        if ($certificate === false) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', self::openssl_errors());
        }

        $pfx = '';
        if (!openssl_pkcs12_export($certificate, $pfx, $privatekey, $password, ['friendly_name' => $cn])) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', self::openssl_errors());
        }

        return $pfx;
    }

    private static function openssl_errors(): string {
        $errors = [];
        while ($error = openssl_error_string()) {
            $errors[] = $error;
        }
        return implode('; ', $errors) ?: 'Unknown OpenSSL error';
    }
}