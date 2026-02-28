<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

use core\exception\moodle_exception;

$PAGE->set_context(context_system::instance());

// Get attempt ID from query parameter
$attemptid = optional_param('ap_attempt_id', 0, PARAM_ALPHANUMEXT);
if (!$attemptid) {
    throw new moodle_exception('invalidaccess', 'quizaccess_autoproctor');
}

$PAGE->set_title('AutoProctor Test');
$PAGE->set_url(new moodle_url(
    '/mod/quiz/accessrule/autoproctor/loadreport.php', 
    ['ap_attempt_id' => $attemptid]
));

// get client ID and secret from config
$clientId = get_config('quizaccess_autoproctor', 'client_id');
$clientSecret = get_config('quizaccess_autoproctor', 'client_secret');

// Determine environment based on hostname
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
$apDomain = $isLocalhost ? 'https://dev.autoproctor.co' : 'https://autoproctor.co';
$apEnv = $isLocalhost ? 'development' : 'production';
$apEntryUrl = $isLocalhost
    ? 'https://ap-development.s3.ap-south-1.amazonaws.com/ap-entry-moodle.js'
    : 'https://cdn.autoproctor.co/ap-entry-moodle.js';

// load autoproctor js module (will be called after SDK loads)
$PAGE->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'loadReport', [
    'clientId' => $clientId,
    'clientSecret' => $clientSecret,
    'testAttemptId' => $attemptid,
    'includeSessionRecording' => true,
    'apDomain' => $apDomain,
    'apEnv' => $apEnv,
]);

echo $OUTPUT->header();

// Load dependencies

echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>';
echo '<script src="' . $apEntryUrl . '"></script>';
echo $OUTPUT->render_from_template('quizaccess_autoproctor/autoproctor', []);
echo $OUTPUT->footer();
