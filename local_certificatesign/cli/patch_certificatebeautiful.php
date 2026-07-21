<?php
/**
 * Aplica patch no mod/certificatebeautiful/view-pdf.php para assinar digitalmente
 * o PDF no momento da geração.
 *
 * Uso:
 *   sudo -u www-data php local/certificatesign/cli/patch_certificatebeautiful.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$viewpdf = $CFG->dirroot . '/mod/certificatebeautiful/view-pdf.php';

if (!file_exists($viewpdf)) {
    cli_error("Arquivo não encontrado: {$viewpdf}");
}

$content = file_get_contents($viewpdf);
if ($content === false) {
    cli_error("Não foi possível ler: {$viewpdf}");
}

// Verificar se já está patchado.
if (strpos($content, 'local_certificatesign\\\\signer::sign_pdf') !== false) {
    cli_writeln("Patch já aplicado em {$viewpdf}");
    exit(0);
}

// Verificar se o plugin local_certificatesign está instalado.
$plugindir = $CFG->dirroot . '/local/certificatesign/classes/signer.php';
if (!file_exists($plugindir)) {
    cli_error("local_certificatesign não encontrado. Instale o plugin primeiro.");
}

$search = <<<'SEARCH'
$fs->create_file_from_string($filerecord, $contentpdf);
$certificatebeautifulissueupdate = (object) [
    "id" => $certificatebeautiful->id,
    "version" => $certificatebeautiful->timemodified,
];
$DB->update_record("certificatebeautiful_issue", $certificatebeautifulissueupdate);

certificatebeautiful_show_header
SEARCH;

$replace = <<<'REPLACE'
$fs->create_file_from_string($filerecord, $contentpdf);

// Patched by local_certificatesign - sign PDF immediately.
try {
    $signedpdf = \local_certificatesign\signer::sign_pdf($contentpdf);
    $storedfile = $fs->get_file(
        $filerecord->contextid, $filerecord->component,
        $filerecord->filearea, $filerecord->itemid,
        $filerecord->filepath, $filerecord->filename
    );
    if ($storedfile) {
        $storedfile->delete();
    }
    $fs->create_file_from_string($filerecord, $signedpdf);
    $contentpdf = $signedpdf;
} catch (\Exception $e) {
    debugging("local_certificatesign: {$e->getMessage()}");
}

$certificatebeautifulissueupdate = (object) [
    "id" => $certificatebeautiful->id,
    "version" => $certificatebeautiful->timemodified,
];
$DB->update_record("certificatebeautiful_issue", $certificatebeautifulissueupdate);

certificatebeautiful_show_header
REPLACE;

$newcontent = str_replace($search, $replace, $content);

if ($newcontent === $content) {
    cli_error("Não foi possível encontrar o ponto de patch em view-pdf.php. O arquivo pode já ter sido modificado.");
}

$result = file_put_contents($viewpdf, $newcontent);
if ($result === false) {
    cli_error("Não foi possível escrever em: {$viewpdf}");
}

cli_writeln("Patch aplicado com sucesso em {$viewpdf}");

/**
 * CLI helpers.
 */
function cli_writeln($msg) {
    echo $msg . "\n";
}

function cli_error($msg) {
    fwrite(STDERR, "ERRO: {$msg}\n");
    exit(1);
}
