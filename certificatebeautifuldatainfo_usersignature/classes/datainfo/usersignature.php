<?php
namespace certificatebeautifuldatainfo_usersignature\datainfo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/usersignature/lib.php');

use mod_certificatebeautiful\datainfo\help_base;

/**
 * Subplugin que expõe a assinatura cursiva do usuário como tags
 * substituíveis no template HTML do mod_certificatebeautiful.
 *
 * Tags disponíveis:
 *   {$USERSIGNATURE->signature_img}  — <img> completa pronta para o HTML
 *   {$USERSIGNATURE->signature_url}  — URL pública da imagem PNG
 *   {$USERSIGNATURE->signature_has}  — "1" se há assinatura, "0" se não
 *   {$USERSIGNATURE->signature_font} — slug do estilo de fonte utilizado
 */
class usersignature extends help_base {

    const CLASS_NAME = 'usersignature';

    public static function table_structure(): array {
        return [
            ['key' => 'signature_img',  'label' => get_string('tag_signature_img',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'signature_url',  'label' => get_string('tag_signature_url',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'signature_has',  'label' => get_string('tag_signature_has',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'signature_font', 'label' => get_string('tag_signature_font', 'certificatebeautifuldatainfo_usersignature')],
        ];
    }

    public static function get_data($course, $user): array {
        $url  = local_usersignature_get_signature_url((int) $user->id);
        $meta = local_usersignature_get_signature_meta((int) $user->id);

        $has        = ($url !== null);
        $url_string = $has ? $url->out() : '';

        $img_tag = '';
        if ($has) {
            $img_tag = sprintf(
                '<img src="%s" alt="%s" style="max-height:60px;width:auto;display:block;margin:0 auto;">',
                htmlspecialchars($url_string, ENT_QUOTES),
                htmlspecialchars(get_string('mysignature', 'local_usersignature') . ' — ' . fullname($user), ENT_QUOTES)
            );
        }

        return [
            'signature_img'  => $img_tag,
            'signature_url'  => $url_string,
            'signature_has'  => $has ? '1' : '0',
            'signature_font' => $meta['font'] ?? '',
        ];
    }

    /**
     * Hook pós-substituição: remove blocos marcados com data-sig-required="true"
     * quando o usuário ainda não cadastrou assinatura.
     */
    public static function process_html(string $html, $course, $user): string {
        $url = local_usersignature_get_signature_url((int) $user->id);
        if ($url === null) {
            $html = preg_replace(
                '/<[^>]+data-sig-required=["\']true["\'][^>]*>.*?<\/\w+>/si',
                '',
                $html
            );
        }
        return $html;
    }
}
