<?php
namespace local_certificatesign\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class generate_cert extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'cn', get_string('gen_cn', 'local_certificatesign'));
        $mform->setType('cn', PARAM_TEXT);
        $mform->addRule('cn', null, 'required', null, 'client');
        $mform->setDefault('cn', 'Instituto Federal');

        $mform->addElement('text', 'org', get_string('gen_org', 'local_certificatesign'));
        $mform->setType('org', PARAM_TEXT);

        $mform->addElement('text', 'country', get_string('gen_country', 'local_certificatesign'));
        $mform->setType('country', PARAM_TEXT);
        $mform->setDefault('country', 'BR');

        $mform->addElement('passwordunmask', 'password', get_string('gen_password', 'local_certificatesign'));
        $mform->setType('password', PARAM_RAW);
        $mform->addRule('password', null, 'required', null, 'client');

        $mform->addElement('passwordunmask', 'password_confirm', get_string('gen_password_confirm', 'local_certificatesign'));
        $mform->setType('password_confirm', PARAM_RAW);
        $mform->addRule('password_confirm', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('gen_generate', 'local_certificatesign'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = get_string('gen_passwords_mismatch', 'local_certificatesign');
        }
        if (strlen($data['password']) < 4) {
            $errors['password'] = get_string('gen_password_weak', 'local_certificatesign');
        }

        return $errors;
    }
}
