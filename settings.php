<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('quizaccess_autoproctor', get_string('pluginname', 'quizaccess_autoproctor'));
    $ADMIN->add('modsettings', $settings);

    // AutoProctor API Settings
    $settings->add(new admin_setting_configtext(
        'quizaccess_autoproctor/client_id',
        get_string('client_id', 'quizaccess_autoproctor'),
        get_string('client_id_desc', 'quizaccess_autoproctor'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'quizaccess_autoproctor/client_secret',
        get_string('client_secret', 'quizaccess_autoproctor'),
        get_string('client_secret_desc', 'quizaccess_autoproctor'),
        '',
    ));

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'quizaccess_autoproctor/enable_by_default',
            get_string('enable_by_default', 'quizaccess_autoproctor'),
            get_string('enable_by_default_desc', 'quizaccess_autoproctor'),
            0
        ));
    }
} 