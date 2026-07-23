<?php
/**
 * Aplica patch no mod/certificatebeautiful/view-pdf.php para:
 *  1. Suporte a token (App Moodle)
 *  2. Assinatura digital do PDF na geracao
 *
 * Uso:
 *   sudo -u www-data php local/certificatesign/cli/patch_viewpdf.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$file = $CFG->dirroot . '/mod/certificatebeautiful/view-pdf.php';

if (!file_exists($file)) {
    cli_error("Arquivo nao encontrado: $file");
}

$content = file_get_contents($file);
if ($content === false) {
    cli_error("Nao foi possivel ler: $file");
}

// Verificar se ja foi patchado.
if (strpos($content, 'local_certificatesign') !== false) {
    cli_writeln("Patch ja aplicado.");
    exit(0);
}

// Plugin local_certificatesign existe?
if (!file_exists($CFG->dirroot . '/local/certificatesign/classes/signer.php')) {
    cli_error("local_certificatesign nao encontrado. Instale o plugin primeiro.");
}

// ─── Patch 1: Token auth ─────────────────────────────────────────────────────
$token_code = <<<'PHP'

// Autenticacao por token (App Moodle)
$token = optional_param("token", false, PARAM_TEXT);
if ($token) {
    $extservice = $DB->get_record("external_services", ["shortname" => MOODLE_OFFICIAL_MOBILE_SERVICE]);
    $exttoken = $DB->get_record("external_tokens", ["token" => $token, "externalserviceid" => $extservice->id], "userid");
    if ($exttoken) {
        $tokenuser = $DB->get_record("user", ["id" => $exttoken->userid]);
        if ($tokenuser) {
            \core\session\manager::login_user($tokenuser);
        }
    }
}

PHP;

$search_login = 'require_course_login($cm->course);';
$pos = strpos($content, $search_login);
if ($pos === false) {
    cli_error("Nao foi possivel encontrar 'require_course_login(\$cm->course)' em view-pdf.php");
}
$pos += strlen($search_login);
$content = substr($content, 0, $pos) . $token_code . substr($content, $pos);

// ─── Patch 2: Signing ────────────────────────────────────────────────────────
$sign_code = <<<'PHP'

// Assinar digitalmente (local_certificatesign)
require_once($CFG->dirroot . '/local/certificatesign/classes/signer.php');
try {
    $signedpdf = \local_certificatesign\signer::sign_pdf($contentpdf);
    $tmpfile = $fs->get_file(
        $filerecord->contextid, $filerecord->component,
        $filerecord->filearea, $filerecord->itemid,
        $filerecord->filepath, $filerecord->filename);
    if ($tmpfile) { $tmpfile->delete(); }
    $fs->create_file_from_string($filerecord, $signedpdf);
    $contentpdf = $signedpdf;
} catch (\Exception $e) {
    debugging('local_certificatesign: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

PHP;

$search_echo = "\$contentpdf = \$pagepdf->create_pdf(\n    \$certificatebeautiful, \$certificatebeautifulissue, \$certificatebeautifulmodel, \$user, \$course);\n\n\$fs->create_file_from_string(\$filerecord, \$contentpdf);";
$pos = strpos($content, $search_echo);
if ($pos === false) {
    cli_error("Nao foi possivel encontrar o ponto de insercao da assinatura");
}
$pos += strlen($search_echo);
$content = substr($content, 0, $pos) . $sign_code . substr($content, $pos);

// ─── Escrever ────────────────────────────────────────────────────────────────
if (file_put_contents($file, $content) === false) {
    cli_error("Nao foi possivel escrever: $file");
}

cli_writeln("Patch aplicado com sucesso!");
cli_writeln("  - Token auth adicionado");
cli_writeln("  - Assinatura digital adicionada");

function cli_writeln($msg) { echo "$msg\n"; }
function cli_error($msg) { fwrite(STDERR, "ERRO: $msg\n"); exit(1); }
