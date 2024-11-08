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

defined('MOODLE_INTERNAL') || die();

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
/**
 * A rule controlling the AutoProctor.
 *
 * @package   quizaccess_autoproctor
 * @copyright 2024 AutProctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_autoproctor extends access_rule_base {
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        return new self($quizobj, $timenow);
    }

    public function prevent_access() {
        global $PAGE, $DB;
        
        // Get client credentials
        $clientId = get_config('quizaccess_autoproctor', 'client_id');
        $clientSecret = get_config('quizaccess_autoproctor', 'client_secret');
        
        // Check if client credentials are set
        if (empty($clientId) || empty($clientSecret)) {
            return get_string('proctoring_required', 'quizaccess_autoproctor');
        }
        
        // Include the scripts and styles
        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>';
        echo '<script src="https://ap-development.s3.amazonaws.com/autoproctor.4.2.4.min.js"></script>';
        echo '<link rel="stylesheet" href="https://ap-development.s3.amazonaws.com/autoproctor.4.2.4.min.css"/>';

        // Get the current attempt ID
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        
        // Look for test-attmpet-id on Params and generate a unique test attempt ID if not found
        $testAttemptId = optional_param(
            'test-attempt-id', 
            uniqid('ap_'), 
            PARAM_RAW
        );
        
        // Store in database
        $session = new stdClass();
        $session->quiz_attempt_id = $attemptid;
        $session->test_attempt_id = $testAttemptId;
        $session->status = 'pending';
        $session->timecreated = $session->timemodified = time();
        
        $DB->insert_record('quizaccess_autoproctor_sessions', $session);
        
        // Initialize AutoProctor
        $PAGE->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'init', [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'testAttemptId' => $testAttemptId,
        ]);
        
        return false;
    }

    public function attempt_must_be_in_popup() {
        return true;
    }

    public function get_popup_options() {
        return array(
            'height' => 600,
            'width' => 800,
            'fullscreen' => true,
        );
    }

    public static function add_settings_form_fields(
        mod_quiz_mod_form $quizform,
        MoodleQuickForm $mform
    ) {
        $mform->addElement('selectyesno', 'requireautoproctor',
            get_string('requireautoproctor', 'quizaccess_autoproctor'));
        $mform->addHelpButton('requireautoproctor',
            'requireautoproctor', 'quizaccess_autoproctor');
        $mform->setDefault('requireautoproctor',
            get_config('quizaccess_autoproctor', 'enable_by_default'));
    }
} 