<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']              = 'Digital Certificate Signer';
$string['settings']                = 'Certificate Signature Settings';
$string['pfxfile']                 = 'PFX/P12 Certificate';
$string['pfxfile_help']            = 'Upload your PFX or P12 digital certificate file.';
$string['certpassword']            = 'Certificate Password';
$string['certpassword_help']       = 'Password to unlock the PFX/P12 certificate.';
$string['signerreason']            = 'Signing Reason';
$string['signerreason_help']       = 'Reason displayed in the signature (ex.: "Course Certificate").';
$string['autosign_enabled']        = 'Enable/disable scheduled task';
$string['autosign_enabled_help']   = 'When enabled, the scheduled task signs pending certificates.';
$string['task_interval']           = 'local_certificatesign | task_interval';
$string['task_interval_help']      = 'How often to process pending certificates. Default: 2 minutes.';
$string['task_sign']               = 'Sign pending certificates';
$string['privacy:metadata']        = 'This plugin does not store personal data directly.';
$string['errorreadingpfx']         = 'Error reading the PFX/P12 certificate. Check the password.';
$string['erroropenssl']            = 'OpenSSL error: {$a}';
$string['invalidpdf']              = 'Invalid PDF content.';
$string['notconfigured']           = 'Digital signing is not configured.';
