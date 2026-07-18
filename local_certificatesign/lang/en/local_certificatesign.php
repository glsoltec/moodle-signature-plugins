<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']              = 'Digital Certificate Signer';
$string['settings']                = 'Certificate Signature Settings';

$string['pfxfile']                 = 'PFX/P12 Certificate';
$string['pfxfile_help']            = 'Upload your PFX or P12 digital certificate file used to sign certificates.';
$string['certpassword']            = 'Certificate Password';
$string['certpassword_help']       = 'Password to unlock the PFX/P12 certificate.';
$string['signername']              = 'Signer Name';
$string['signername_help']         = 'Name displayed as the signer of the certificate.';
$string['signerlocation']          = 'Signer Location';
$string['signerlocation_help']     = 'Location/city of the signer.';
$string['signerreason']            = 'Signing Reason';
$string['signerreason_help']       = 'Reason for signing (e.g. "Course Certificate").';
$string['signercontact']           = 'Contact Info';
$string['signercontact_help']      = 'Contact information of the signer (e.g. email or phone).';

$string['task_sign']               = 'Sign pending certificates';
$string['signature_appended']      = 'Digitally signed certificate.';

$string['privacy:metadata']        = 'This plugin does not store personal data directly.';

$string['errorreadingpfx']         = 'Error reading the PFX/P12 certificate. Check the password.';
$string['erroropenssl']            = 'OpenSSL error: {$a}';
$string['invalidpdf']              = 'Invalid PDF content.';
$string['invalidpfx']              = 'Invalid or corrupted PFX/P12 certificate.';
$string['notconfigured']           = 'Digital signing is not configured. Upload a PFX certificate in the plugin settings.';
