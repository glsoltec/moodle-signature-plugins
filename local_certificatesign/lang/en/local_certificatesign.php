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

$string['signerreason']            = 'Signing Reason';
$string['signerreason_help']       = 'Reason displayed in the signature (ex.: "Course Certificate").';
$string['autosign_enabled']        = 'Enable/disable the scheduled task';
$string['autosign_enabled_help']   = 'When enabled, certificates are signed automatically by the observer and by the scheduled task as a fallback. Configure the task frequency in Site administration > Server > Scheduled tasks.';

$string['gen_heading']             = 'Would you like to generate a new self-signed certificate?';
$string['gen_heading_desc']        = 'If you do not have a PFX/P12 file, the system can generate a self-signed certificate valid for 10 years. Fill in the details below and it will be automatically installed as the plugin certificate.';
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

$string['privacy:metadata']        = 'The Digital Certificate Signer plugin stores audit access records for certificate viewing and download events.';
$string['privacy:metadata:local_certificatesign_audit'] = 'Audit log of certificate access events';
$string['privacy:metadata:local_certificatesign_audit:userid'] = 'The user who accessed the certificate';
$string['privacy:metadata:local_certificatesign_audit:issueid'] = 'The certificate issue record identifier';
$string['privacy:metadata:local_certificatesign_audit:cmid'] = 'The course module identifier';
$string['privacy:metadata:local_certificatesign_audit:courseid'] = 'The course identifier';
$string['privacy:metadata:local_certificatesign_audit:action'] = 'The action performed (view, download, token_view, pending)';
$string['privacy:metadata:local_certificatesign_audit:timecreated'] = 'The time the access occurred';
$string['privacy:metadata:local_certificatesign_audit:ipaddress'] = 'The IP address of the user';
$string['privacy:metadata:local_certificatesign_audit:useragent'] = 'The browser user agent string';
$string['privacy:metadata:local_certificatesign_log'] = 'Log of digitally signed certificate issues';
$string['privacy:metadata:local_certificatesign_log:issueid'] = 'The certificate issue record identifier';
$string['privacy:metadata:local_certificatesign_log:timecreated'] = 'The time the digital signature was applied';

$string['errorreadingpfx']         = 'Error reading the PFX/P12 certificate. Check the password.';
$string['erroropenssl']            = 'OpenSSL error: {$a}';
$string['invalidpdf']              = 'Invalid PDF content.';
$string['invalidpfx']              = 'Invalid or corrupted PFX/P12 certificate.';
$string['notconfigured']           = 'Digital signing is not configured. Upload a PFX certificate in the plugin settings.';

$string['errornofpdi']              = 'FPDI is not installed. Run composer install in local/certificatesign.';
$string['errorpdfparse']            = 'Could not import the generated PDF for signing: {$a}';
