<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A rule controlling the AutoProctor.
 *
 * @package   quizaccess_autoproctor
 * @copyright 2024 AutoProctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Compatibility check for Moodle versions.
if (class_exists('\mod_quiz\local\access_rule_base')) {
    class_alias('\mod_quiz\local\access_rule_base', '\quizaccess_autoproctor_parent_class_alias');
    class_alias('\mod_quiz\form\preflight_check_form', '\quizaccess_autoproctor_preflight_form_alias');
    class_alias('\mod_quiz\quiz_settings', '\quizaccess_autoproctor_quiz_settings_class_alias');
} else {
    require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
    class_alias('\quiz_access_rule_base', '\quizaccess_autoproctor_parent_class_alias');
    class_alias('\mod_quiz_preflight_check_form', '\quizaccess_autoproctor_preflight_form_alias');
    class_alias('\quiz', '\quizaccess_autoproctor_quiz_settings_class_alias');
}

class quizaccess_autoproctor extends quizaccess_autoproctor_parent_class_alias
{
    /** @var quizaccess_autoproctor_quiz_settings_class_alias */
    protected $quizobj;

    public function __construct($quizobj, $timenow)
    {
        parent::__construct($quizobj, $timenow);
        $this->quizobj = $quizobj;
    }

    public static function make(quizaccess_autoproctor_quiz_settings_class_alias $quizobj, $timenow, $canignoretimelimits)
    {
        $quizid = $quizobj->get_quiz()->id;
        $proctoring_enabled = self::get_ap_settings($quizid)->proctoring_enabled;
        if (empty($proctoring_enabled))
            return null;

        return new self($quizobj, $timenow);
    }

    public function prevent_access()
    {
        global $PAGE, $DB;

        // Only proceed if we're on the attempt page
        if (strpos($PAGE->url->get_path(), '/mod/quiz/attempt.php') === false) {
            return false;
        }

        // Get client credentials
        $clientId = get_config('quizaccess_autoproctor', 'client_id');
        $clientSecret = get_config('quizaccess_autoproctor', 'client_secret');

        // Check if client credentials are set
        if (empty($clientId) || empty($clientSecret)) {
            return get_string('proctoring_required', 'quizaccess_autoproctor');
        }

        // Get the current attempt ID and check if it's empty
        // If attempt ID is empty, return. Because in that case this is not the endpoint for quiz attempt
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (empty($attemptid))
            return false;

        // Get the current attempt ID or generate a new test attempt ID
        $testAttemptId = optional_param('test-attempt-id', uniqid('ap_'), PARAM_RAW);
        $tracking_options = self::get_ap_settings($this->quizobj->get_quiz()->id)->tracking_options;

        // Check if test attempt ID already exists for this quiz attempt
        $session = $DB->get_record('quizaccess_autoproctor_sessions', [
            'quiz_id' => $this->quizobj->get_quiz()->id,
            'quiz_attempt_id' => $attemptid,
        ]);

        if ($session) {
            // If session exists, use that test attempt ID
            $testAttemptId = $session->test_attempt_id;
        } else {
            // If session does not exist, create a new one
            $session = new stdClass();
            $session->quiz_id = $this->quizobj->get_quiz()->id;
            $session->quiz_attempt_id = $attemptid;
            $session->test_attempt_id = $testAttemptId;
            $session->started_at = time();
            $session->tracking_options = json_encode($tracking_options);
            $session->timecreated = $session->timemodified = time();
            $DB->insert_record('quizaccess_autoproctor_sessions', $session);
        }

        // Include the scripts and styles
        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>';
        echo '<script src="https://ap-development.s3.amazonaws.com/autoproctor.4.2.4.min.js"></script>';
        echo '<link rel="stylesheet" href="https://ap-development.s3.amazonaws.com/autoproctor.4.2.4.min.css"/>';

        // Include necessary scripts/styles for AutoProctor during preflight check
        $PAGE->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'init', [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'testAttemptId' => $testAttemptId,
            'trackingOptions' => $tracking_options
        ]);

        return false;
    }

    public function attempt_must_be_in_popup() {
        // If proctoring is enabled, the attempt must be in a popup
        return $this->get_ap_settings($this->quizobj->get_quiz()->id)->proctoring_enabled;
    }

    public function get_popup_options() {
        return [
            'height' => 600,
            'width' => 800,
            'fullscreen' => true,
        ];
    }

    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform)
    {
        // Get the current autoproctor settings for the quiz
        $ap_settings = self::get_ap_settings($quizform->get_current()->id);

        // Add settings for enabling/disabling AutoProctor for a quiz
        $mform->addElement(
            'selectyesno',
            'requireautoproctor',
            get_string('requireautoproctor', 'quizaccess_autoproctor')
        );
        $mform->addHelpButton(
            'requireautoproctor',
            'requireautoproctor',
            'quizaccess_autoproctor'
        );
        $mform->setDefault(
            'requireautoproctor',
            $ap_settings->proctoring_enabled ?? get_config(
                'quizaccess_autoproctor',
                'enable_by_default'
            )
        );

        // Add all tracking options
        $tracking_options = [
            'audio' => get_string('tracking_audio', 'quizaccess_autoproctor'),
            'numHumans' => get_string('tracking_numHumans', 'quizaccess_autoproctor'),
            'tabSwitch' => get_string('tracking_tabSwitch', 'quizaccess_autoproctor'),
            'captureSwitchedTab' => get_string('tracking_captureSwitchedTab', 'quizaccess_autoproctor'),
            'photosAtRandom' => get_string('tracking_photosAtRandom', 'quizaccess_autoproctor'),
            'recordSession' => get_string('tracking_recordSession', 'quizaccess_autoproctor'),
            'detectMultipleScreens' => get_string('tracking_detectMultipleScreens', 'quizaccess_autoproctor'),
            'testTakerPhoto' => get_string('tracking_testTakerPhoto', 'quizaccess_autoproctor'),
            'showCamPreview' => get_string('tracking_showCamPreview', 'quizaccess_autoproctor'),
            'forceFullScreen' => get_string('tracking_forceFullScreen', 'quizaccess_autoproctor')
        ];

        foreach ($tracking_options as $option => $string) {
            $element_name = "tracking_{$option}";
            $mform->addElement('selectyesno', $element_name, $string);
            $mform->addHelpButton($element_name, "tracking_{$option}", 'quizaccess_autoproctor');
            $mform->setDefault($element_name, $ap_settings->tracking_options[$option] ?? 1);
            $mform->disabledIf($element_name, 'requireautoproctor', 'eq', 0);
            $mform->setType($element_name, PARAM_INT);
        }
    }

    public static function save_settings($quiz)
    {
        global $DB;

        // Get existing settings to preserve tracking options
        $existing = $DB->get_record('quizaccess_autoproctor', ['quiz_id' => $quiz->id]);
        $existing_options = $existing ? json_decode($existing->tracking_options, true) : [];

        // Prepare record for database
        $record = new stdClass();
        $record->quiz_id = $quiz->id;
        $record->proctoring_enabled = empty($quiz->requireautoproctor) ? 0 : 1;

        // Prepare tracking options
        $tracking_options = new stdClass();
        $options = [
            'audio',
            'numHumans',
            'tabSwitch',
            'captureSwitchedTab',
            'photosAtRandom',
            'recordSession',
            'detectMultipleScreens',
            'testTakerPhoto',
            'showCamPreview',
            'forceFullScreen'
        ];
        foreach ($options as $option) {
            $form_field = "tracking_{$option}";
            // If proctoring is enabled, use form values, otherwise keep existing values
            if ($record->proctoring_enabled) {
                $tracking_options->$option = isset($quiz->$form_field) ? (bool) $quiz->$form_field : true;
            } else {
                $tracking_options->$option = $existing_options[$option] ?? true;
            }
            unset($quiz->$form_field);
        }
        $record->tracking_options = json_encode($tracking_options);

        // Insert or update the record
        if ($existing) {
            $record->timemodified = time();
            $record->id = $existing->id;
            $DB->update_record('quizaccess_autoproctor', $record);
        } else {
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('quizaccess_autoproctor', $record);
        }

        return true;
    }

    private static function get_ap_settings($quizid)
    {
        global $DB;
        $result = new stdClass();
        $record = $DB->get_record('quizaccess_autoproctor', ['quiz_id' => $quizid]);

        if ($record) {
            $result->tracking_options = json_decode($record->tracking_options, true);
            $result->proctoring_enabled = $record->proctoring_enabled;
        } else {
            $result->tracking_options = [];
            $result->proctoring_enabled = 0;
        }
        return $result;
    }


    /**
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     * There is no obligation to return anything. If it is not appropriate to tell students
     * about this rule, then just return ''.
     *
     * @return mixed a message, or array of messages, explaining the restriction
     * @throws coding_exception
     */
    public function description() {
        $messages = [get_string('proctoringheader', 'quizaccess_autoproctor'), get_string('proctoringpermissions', 'quizaccess_autoproctor')];

        $messages[] = $this->get_download_config_button();

        return $messages;
    }

    /**
     * Get a button to view the Proctoring report.
     *
     * @return string A link to view report
     * @throws coding_exception
     */
    private function get_download_config_button(): string {
        global $OUTPUT, $USER, $CFG;

        $context = context_module::instance($this->quizobj->get_quiz()->cmid, MUST_EXIST);
        
        if (has_capability('quizaccess/autoproctor:viewreport', $context, $USER->id)) {
            $httplink = get_string('autoproctorresultslink', 'quizaccess_autoproctor');
            // Add site identifier to make the lookup key globally unique
            $lookup_key = ($CFG->siteidentifier) . '_' . $this->quizobj->get_quiz()->cmid . '_' . $this->quizobj->get_quiz()->id;
            $httplink = $httplink . '?lookup_key=' . $lookup_key;
            return $OUTPUT->single_button($httplink, get_string('autoproctorresults', 'quizaccess_autoproctor'), 'get');
        } else {
            return '';
        }
    }
}
