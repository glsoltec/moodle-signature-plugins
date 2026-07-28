<?php
/**
 * Reverts the legacy local_certificatesign patch from mod/certificatebeautiful/view-pdf.php.
 *
 * Usage:
 *   sudo -u www-data php local/certificatesign/cli/unpatch_viewpdf.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$file = $CFG->dirroot . '/mod/certificatebeautiful/view-pdf.php';
if (!file_exists($file)) {
    cli_error("File not found: {$file}");
}

$content = file_get_contents($file);
if ($content === false) {
    cli_error("Could not read: {$file}");
}

$original = $content;

$content = preg_replace('/\n\/\/ Autenticacao por token \(App Moodle\).*?\n(?=require_|\$|\/\/|if \(|echo |$)/s', "\n", $content);
$content = preg_replace('/\n\/\/ Assinar digitalmente \(local_certificatesign\).*?\n(?=\$|\/\/|if \(|echo |$)/s', "\n", $content);

if ($content === null) {
    cli_error('Regex error while reverting patch.');
}

if ($content === $original) {
    cli_writeln('No legacy local_certificatesign patch block found. Nothing changed.');
    exit(0);
}

$backup = $file . '.bak-local_certificatesign-' . date('Ymd-His');
if (!copy($file, $backup)) {
    cli_error("Could not create backup: {$backup}");
}

if (file_put_contents($file, $content) === false) {
    cli_error("Could not write: {$file}");
}

cli_writeln('Legacy patch removed successfully.');
cli_writeln("Backup: {$backup}");

function cli_writeln(string $message): void {
    echo $message . PHP_EOL;
}

function cli_error(string $message): void {
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}