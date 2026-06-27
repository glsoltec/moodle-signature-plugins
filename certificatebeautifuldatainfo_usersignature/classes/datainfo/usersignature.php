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
 * Tags do DESTINATÁRIO do certificado (aluno):
 *   {$USERSIGNATURE->signature_img}  — <img> base64 pronta para o HTML/PDF
 *   {$USERSIGNATURE->signature_url}  — URL pública da imagem PNG (uso web)
 *   {$USERSIGNATURE->signature_has}  — "1" se há assinatura, "0" se não
 *   {$USERSIGNATURE->signature_font} — slug do estilo de fonte utilizado
 *
 * Tags do(s) PROFESSOR(es) do curso:
 *   {$USERSIGNATURE->teacher_signature_img1}  — assinatura do 1º professor
 *   {$USERSIGNATURE->teacher_signature_img2}  — assinatura do 2º professor
 *   {$USERSIGNATURE->teacher_signature_all}   — assinaturas de todos (lado a lado, com nome)
 *   {$USERSIGNATURE->teacher_signature_name1} — nome do 1º professor
 *   {$USERSIGNATURE->teacher_signature_name2} — nome do 2º professor
 *
 * O professor é identificado pelos papéis de contato do curso ($CFG->coursecontact,
 * normalmente "Professor"), a mesma regra usada pelo subplugin oficial "teachers".
 */
class usersignature extends help_base {

    const CLASS_NAME = 'usersignature';

    public static function table_structure(): array {
        return [
            ['key' => 'signature_img',  'label' => get_string('tag_signature_img',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'signature_url',  'label' => get_string('tag_signature_url',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'signature_has',  'label' => get_string('tag_signature_has',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'signature_font', 'label' => get_string('tag_signature_font', 'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_img1',  'label' => get_string('tag_teacher_signature_img1',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_img2',  'label' => get_string('tag_teacher_signature_img2',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_all',   'label' => get_string('tag_teacher_signature_all',   'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_name1', 'label' => get_string('tag_teacher_signature_name1', 'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_name2', 'label' => get_string('tag_teacher_signature_name2', 'certificatebeautifuldatainfo_usersignature')],
        ];
    }

    public static function get_data($course, $user): array {
        $userid = (int) $user->id;

        // ─── Assinatura do destinatário (aluno) ───────────────────────────────
        // Data URI base64: indispensável para o mPDF, que renderiza no servidor
        // e não consegue baixar a URL protegida do pluginfile.
        $datauri = local_usersignature_get_signature_datauri($userid);
        $has     = ($datauri !== '');

        // URL pública mantida para usos web (não usada no PDF).
        $url        = local_usersignature_get_signature_url($userid);
        $url_string = ($url !== null) ? $url->out() : '';

        $meta = local_usersignature_get_signature_meta($userid);

        $img_tag = '';
        if ($has) {
            $img_tag = self::build_img(
                $datauri,
                get_string('mysignature', 'local_usersignature') . ' — ' . fullname($user)
            );
        }

        // ─── Assinatura dos professores do curso ──────────────────────────────
        $teachers = self::get_course_teachers($course);
        $t1 = $teachers[0] ?? null;
        $t2 = $teachers[1] ?? null;

        return [
            'signature_img'  => $img_tag,
            'signature_url'  => $url_string,
            'signature_has'  => $has ? '1' : '0',
            'signature_font' => $meta['font'] ?? '',

            'teacher_signature_img1'  => $t1 ? self::teacher_img($t1) : '',
            'teacher_signature_img2'  => $t2 ? self::teacher_img($t2) : '',
            'teacher_signature_all'   => self::teachers_block($teachers),
            'teacher_signature_name1' => $t1 ? fullname($t1) : '',
            'teacher_signature_name2' => $t2 ? fullname($t2) : '',
        ];
    }

    /**
     * Hook pós-substituição: remove blocos marcados com data-sig-required="true"
     * quando o usuário ainda não cadastrou assinatura.
     *
     * @param string $html
     * @param object $course
     * @param object $user
     * @return string
     */
    public static function process_html(string $html, $course, $user): string {
        $datauri = local_usersignature_get_signature_datauri((int) $user->id);
        if ($datauri === '') {
            $html = preg_replace(
                '/<[^>]+data-sig-required=["\']true["\'][^>]*>.*?<\/\w+>/si',
                '',
                $html
            );
        }
        return $html;
    }

    /**
     * Monta a tag <img> com a assinatura em base64.
     *
     * @param string $datauri
     * @param string $alt
     * @return string
     */
    private static function build_img(string $datauri, string $alt): string {
        return sprintf(
            '<img src="%s" alt="%s" style="max-height:60px;width:auto;display:block;margin:0 auto;">',
            $datauri,
            htmlspecialchars($alt, ENT_QUOTES)
        );
    }

    /**
     * Assinatura de um professor (ou string vazia se ele não tiver assinatura).
     *
     * @param object $teacher
     * @return string
     */
    private static function teacher_img($teacher): string {
        $datauri = local_usersignature_get_signature_datauri((int) $teacher->id);
        if ($datauri === '') {
            return '';
        }
        return self::build_img($datauri, fullname($teacher));
    }

    /**
     * Bloco com as assinaturas de TODOS os professores que tenham assinatura,
     * dispostas lado a lado com o nome abaixo.
     *
     * @param array $teachers
     * @return string
     */
    private static function teachers_block(array $teachers): string {
        $blocks = [];
        foreach ($teachers as $teacher) {
            $datauri = local_usersignature_get_signature_datauri((int) $teacher->id);
            if ($datauri === '') {
                continue;
            }
            $blocks[] = sprintf(
                '<span style="display:inline-block;margin:0 18px;text-align:center;vertical-align:bottom;">'
                . '%s<span style="display:block;font-size:11px;margin-top:2px;">%s</span></span>',
                self::build_img($datauri, fullname($teacher)),
                htmlspecialchars(fullname($teacher), ENT_QUOTES)
            );
        }
        return implode('', $blocks);
    }

    /**
     * Professores do curso (papéis de contato), na mesma ordem usada pelo
     * subplugin oficial "teachers". Deduplica usuários com mais de um papel.
     *
     * @param object $course
     * @return array Lista de objetos de usuário.
     */
    private static function get_course_teachers($course): array {
        global $CFG;

        if (empty($course->id)) {
            return [];
        }
        $context = \context_course::instance($course->id, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        if (!empty($CFG->coursecontact)) {
            $roleids = explode(',', $CFG->coursecontact);
        } else {
            list($roleids) = get_roles_with_cap_in_context($context, 'moodle/course:manage');
        }

        $teachers = [];
        foreach ($roleids as $roleid) {
            foreach (get_role_users((int) $roleid, $context, true) as $u) {
                // Chaveado por id para deduplicar; preserva a ordem de inserção.
                if (!isset($teachers[$u->id])) {
                    $teachers[$u->id] = $u;
                }
            }
        }
        return array_values($teachers);
    }
}
