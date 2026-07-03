<?php
/**
 * Serve os arquivos das fontes de assinatura para o navegador.
 *
 * O diretório $CFG->dataroot/fonts/ (moodledata/fonts) NÃO é acessível via
 * web — este endpoint faz a ponte, com whitelist de slugs (sem path
 * traversal: o nome do arquivo vem do mapa em lib.php, nunca do usuário).
 *
 * Ordem de busca: local/usersignature/fonts/ → $CFG->dataroot/fonts/,
 * preferindo woff2 > woff > ttf > otf. Aceita também o basename em
 * minúsculas (ex.: autography.ttf).
 *
 * URL: /local/usersignature/font.php?font=autography
 */

// Fontes são recursos públicos e estáticos; sem cookie evita lock de sessão.
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

$slug  = required_param('font', PARAM_ALPHA);
$fonts = local_usersignature_fonts();

if (!isset($fonts[$slug])) {
    send_file_not_found();
}

$mimes = [
    'woff2' => 'font/woff2',
    'woff'  => 'font/woff',
    'ttf'   => 'font/ttf',
    'otf'   => 'font/otf',
];

$base = $fonts[$slug]['file'];
$dirs = [__DIR__ . '/fonts', $CFG->dataroot . '/fonts'];

$path = null;
$mime = null;
foreach ($mimes as $ext => $extmime) {
    foreach ($dirs as $dir) {
        foreach ([$base, strtolower($base)] as $name) {
            $candidate = $dir . '/' . $name . '.' . $ext;
            if (is_readable($candidate)) {
                $path = $candidate;
                $mime = $extmime;
                break 3;
            }
        }
    }
}

if ($path === null) {
    send_file_not_found();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');
header('Access-Control-Allow-Origin: *'); // Fontes exigem CORS quando há CDN/domínio alternativo.
readfile($path);
die;
