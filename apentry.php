<?php
require_once('../../../../config.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/quiz/accessrule/autoproctor/apentry.php'));
$PAGE->set_title('AutoProctor Test');

// get client ID and secret from config
$clientId = get_config('quizaccess_autoproctor', 'client_id');
$clientSecret = get_config('quizaccess_autoproctor', 'client_secret');

// load autoproctor js module
$PAGE->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'init', [
    'clientId' => $clientId,
    'clientSecret' => $clientSecret,
]);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('quizaccess_autoproctor/autoproctor', []);
echo $OUTPUT->footer();
