<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']              = 'Digital Certificate Signer';
$string['settings']                = 'Certificate Signature Settings';

$string['pfxfile']                 = 'PFX/P12 Certificate';
$string['pfxfile_help']            = 'Upload your PFX or P12 digital certificate file. After uploading, enter the password below and save. If the password is correct, the certificate owner details will appear.';
$string['certpassword']            = 'Certificate Password';
$string['certpassword_help']       = 'Password to unlock the PFX/P12 certificate. Save the form to validate.';

$string['certinfo']                = 'Certificate Info';
$string['certinfo_help']           = 'Information extracted from the uploaded certificate after successful validation.';
$string['certinfonocert']          = 'No certificate uploaded or password not set. Upload a PFX file and enter the password, then save.';
$string['certinfo_cn']             = 'Owner (CN)';
$string['certinfo_org']            = 'Organization';
$string['certinfo_valid']          = 'Validity';
$string['certinfo_issuer']         = 'Issuer';
$string['certexpired']             = 'This certificate has expired!';

$string['signername']              = 'Signer Name';
$string['signername_help']         = 'Auto-filled from the certificate CN. Read-only.';
$string['signerlocation']          = 'Signer Location';
$string['signerlocation_help']     = 'Auto-filled from the certificate subject. Read-only.';
$string['signerreason']            = 'Signing Reason';
$string['signerreason_help']       = 'Reason displayed in the signature (ex.: "Course Certificate").';
$string['autosign_enabled']        = 'Enable/disable the scheduled task';
$string['autosign_enabled_help']   = 'When enabled, the scheduled task processes pending certificates and the observer signs immediately upon issuance. When disabled, the task does not run and no certificates are signed automatically.';
$string['task_interval']           = 'local_certificatesign | task_interval';
$string['task_interval_help']      = 'How often the scheduled task should process pending certificates. Default: 2 minutes.';

$string['gen_heading']             = 'Generate Self-Signed Certificate';
$string['gen_heading_desc']        = 'If you do not have a PFX certificate, you can generate a self-signed one. It will be valid for 10 years.';
$string['gen_btn']                 = 'Generate Self-Signed Certificate';
$string['gen_title']               = 'Generate Self-Signed Certificate';
$string['gen_cn']                  = 'Common Name (CN)';
$string['gen_org']                 = 'Organization';
$string['gen_country']             = 'Country (2-letter code)';
$string['gen_password']            = 'Certificate Password';
$string['gen_password_confirm']    = 'Confirm Password';
$string['gen_generate']            = 'Generate and Install';
$string['gen_passwords_mismatch']  = 'Passwords do not match.';
$string['gen_password_weak']       = 'Password must be at least 4 characters.';
$string['gen_success']             = 'Self-signed certificate generated and installed successfully.';

$string['task_sign']               = 'Sign pending certificates';
$string['signature_appended']      = 'Digitally signed certificate.';

$string['privacy:metadata']        = 'This plugin does not store personal data directly.';

$string['errorreadingpfx']         = 'Error reading the PFX/P12 certificate. Check the password.';
$string['erroropenssl']            = 'OpenSSL error: {$a}';
$string['invalidpdf']              = 'Invalid PDF content.';
$string['invalidpfx']              = 'Invalid or corrupted PFX/P12 certificate.';
$string['notconfigured']           = 'Digital signing is not configured. Upload a PFX certificate in the plugin settings.';
