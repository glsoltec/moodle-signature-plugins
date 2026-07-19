<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

class admin_setting_certinfo extends \admin_setting {

    public function __construct() {
        $this->nosave = true;
        parent::__construct('local_certificatesign/certinfo',
            get_string('certinfo', 'local_certificatesign'),
            get_string('certinfo_help', 'local_certificatesign'), '');
    }

    public function get_setting() {
        return '';
    }

    public function write_setting($data) {
        return '';
    }

    public function output_html($data, $query = '') {
        $pfxcontent = signer::get_pfx_content();
        $password = get_config('local_certificatesign', 'certpassword');

        $html = \html_writer::start_div('form-item clearfix');
        $html .= \html_writer::start_div('form-label');
        $html .= \html_writer::tag('label', $this->visiblename, ['class' => 'form-label-addon']);
        $html .= \html_writer::end_div();
        $html .= \html_writer::start_div('form-setting');

        if ($pfxcontent === null || empty($password)) {
            $html .= \html_writer::tag('span', get_string('certinfonocert', 'local_certificatesign'), ['class' => 'text-muted']);
        } else {
            try {
                $info = signer::get_cert_info($pfxcontent, $password);
                $html .= \html_writer::start_tag('table', ['class' => 'table table-sm mb-0', 'style' => 'max-width:500px']);
                $html .= \html_writer::tag('tr',
                    \html_writer::tag('td', get_string('certinfo_cn', 'local_certificatesign'), ['class' => 'font-weight-bold pr-3']) .
                    \html_writer::tag('td', s($info['cn'])));
                $html .= \html_writer::tag('tr',
                    \html_writer::tag('td', get_string('certinfo_org', 'local_certificatesign'), ['class' => 'font-weight-bold pr-3']) .
                    \html_writer::tag('td', s($info['org'])));
                $html .= \html_writer::tag('tr',
                    \html_writer::tag('td', get_string('certinfo_valid', 'local_certificatesign'), ['class' => 'font-weight-bold pr-3']) .
                    \html_writer::tag('td',
                        userdate($info['validfrom'], '%d/%m/%Y') . ' — ' . userdate($info['validto'], '%d/%m/%Y')));
                $html .= \html_writer::tag('tr',
                    \html_writer::tag('td', get_string('certinfo_issuer', 'local_certificatesign'), ['class' => 'font-weight-bold pr-3']) .
                    \html_writer::tag('td', s($info['issuer'])));
                $html .= \html_writer::end_tag('table');

                $html .= \html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => 's_local_certificatesign_certinfo',
                    'value' => '',
                ]);

                if ($info['validto'] < time()) {
                    $html .= \html_writer::tag('div', get_string('certexpired', 'local_certificatesign'),
                        ['class' => 'alert alert-danger mt-2 mb-0']);
                }
            } catch (\Exception $e) {
                $html .= \html_writer::tag('span', get_string('invalidpfx', 'local_certificatesign'), ['class' => 'text-danger']);
            }
        }

        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        return $html;
    }
}
