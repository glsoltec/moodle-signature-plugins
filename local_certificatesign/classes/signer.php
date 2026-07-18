<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

/**
 * Signs PDF files with a PFX/P12 certificate using byte-range signing.
 *
 * The signature is embedded as an incremental update, preserving the original
 * PDF content. The /ByteRange covers the original file bytes, and the PKCS#7
 * detached signature is stored in /Contents.
 */
class signer {

    /**
     * Sign a PDF with the configured certificate.
     *
     * @param string $pdfcontent Raw PDF binary content.
     * @return string Signed PDF binary content.
     */
    public static function sign_pdf(string $pdfcontent): string {
        $pfxcontent = self::get_pfx_content();
        if ($pfxcontent === null) {
            throw new \moodle_exception('notconfigured', 'local_certificatesign');
        }

        $password = get_config('local_certificatesign', 'certpassword');

        $certs = [];
        if (!openssl_pkcs12_read($pfxcontent, $certs, $password)) {
            throw new \moodle_exception('errorreadingpfx', 'local_certificatesign');
        }

        return self::byte_range_sign($pdfcontent, $certs['cert'], $certs['pkey'], $password);
    }

    /**
     * Retrieve the PFX file content from Moodle file storage.
     *
     * @return string|null
     */
    private static function get_pfx_content(): ?string {
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $files = $fs->get_area_files($syscontext->id, 'local_certificatesign', 'pfxfile', 0, 'id DESC', false);

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        return $file->get_content();
    }

    /**
     * Perform byte-range PDF signing.
     *
     * @param string $pdfcontent
     * @param string $cert PEM certificate.
     * @param string $pkey PEM private key.
     * @param string $password Private key password.
     * @return string
     */
    private static function byte_range_sign(string $pdfcontent, string $cert, string $pkey, string $password): string {
        $signername   = get_config('local_certificatesign', 'signername') ?: '';
        $location     = get_config('local_certificatesign', 'signerlocation') ?: '';
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

        $sig_content = $signature_obj_num . " 0 obj\n" . $sig_obj . "\nendobj\n";

        $content_before_sig = strlen($pdfcontent);

        $byte_range_1 = $content_before_sig;
        $byte_range_2 = strlen($sig_content) + 2;
        $byte_range_3 = 0;

        $sig_content_sized = sprintf($sig_obj,
            $byte_range_1,
            $byte_range_1 + $byte_range_2 + $content_byte_len,
            $byte_range_3
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

        $byterange_end = $byte_range_1 + $byte_range_2 + $content_byte_len;

        $data_to_sign = substr($full_pdf, 0, $byte_range_1);
        $data_to_sign .= substr($full_pdf, $byte_range_1 + $byte_range_2, $byterange_end - ($byte_range_1 + $byte_range_2));

        $tmpdir = make_temp_directory('certificatesign');
        $tmpfile = $tmpdir . '/' . uniqid('pdfsig_') . '.bin';
        file_put_contents($tmpfile, $data_to_sign);

        $signedfile = $tmpdir . '/' . uniqid('pdfsig_res_');
        $certfile = self::create_temp_cert($cert, $tmpdir);
        $keyfile = self::create_temp_key($pkey, $password, $tmpdir);

        $openssl_cmd = "openssl smime -sign -in " . escapeshellarg($tmpfile)
            . " -signer " . escapeshellarg($certfile)
            . " -inkey " . escapeshellarg($keyfile)
            . " -out " . escapeshellarg($signedfile)
            . " -binary -outform DER 2>&1";

        $output = null;
        $exitcode = 0;
        exec($openssl_cmd, $output, $exitcode);

        if ($exitcode !== 0) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign', '', implode("\n", $output));
        }

        $signature_der = file_get_contents($signedfile);
        if ($signature_der === false || strlen($signature_der) === 0) {
            throw new \moodle_exception('erroropenssl', 'local_certificatesign');
        }

        $signature_hex = bin2hex($signature_der);

        $signed_pdf = str_replace($hex_placeholder, $signature_hex, $full_pdf);

        @unlink($tmpfile);
        @unlink($signedfile);
        @unlink($certfile);
        @unlink($keyfile);

        return $signed_pdf;
    }

    /**
     * Escape a string for use in a PDF literal string.
     */
    private static function pdf_escape(string $value): string {
        $value = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
        return $value;
    }

    /**
     * Write certificate to a temp file for OpenSSL CLI.
     */
    private static function create_temp_cert(string $cert, string $tmpdir): string {
        $path = $tmpdir . '/' . uniqid('cert_') . '.pem';
        file_put_contents($path, $cert);
        return $path;
    }

    /**
     * Write private key to a temp file for OpenSSL CLI.
     */
    private static function create_temp_key(string $pkey, string $password, string $tmpdir): string {
        $path = $tmpdir . '/' . uniqid('key_') . '.pem';
        file_put_contents($path, $pkey);
        return $path;
    }
}
