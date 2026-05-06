<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings and links for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Lien vers le rapport dans le bloc Rapports.
    $ADMIN->add(
        'reports',
        new admin_externalpage(
            'reportindividualized',
            get_string('pluginname', 'report_individualized'),
            new moodle_url('/report/individualized/index.php')
        )
    );

    // Page de configuration des colonnes — apparaît dans Plugins > Rapports.
    // admin_settingpage crée une page native dans l'interface d'administration.
    $settings = new admin_settingpage(
        'report_individualized',
        get_string('columnsettings', 'report_individualized')
    );

    // Resources section.

    $settings->add(new admin_setting_heading(
        'report_individualized/resourcesheading',
        get_string('resources', 'report_individualized'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/rescol_resourcename',
        get_string('resourcename', 'report_individualized'),
        null,
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/rescol_availablefrom',
        get_string('availablefrom', 'report_individualized'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/rescol_viewed',
        get_string('viewed', 'report_individualized'),
        '',
        1
    ));

    // Plage de consultation (première → dernière) — activée par défaut.
    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/rescol_viewrange',
        get_string('viewrange', 'report_individualized'),
        '',
        1
    ));

    // Nombre total de consultations — activé par défaut.
    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/rescol_viewcount',
        get_string('viewcount', 'report_individualized'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/rescol_estimatedduration',
        get_string('estimatedduration', 'report_individualized'),
        '',
        1
    ));

    // Activities section.

    $settings->add(new admin_setting_heading(
        'report_individualized/activitiesheading',
        get_string('activities', 'report_individualized'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_activityname',
        get_string('activityname', 'report_individualized'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_availablefrom',
        get_string('availablefrom', 'report_individualized'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_duedate',
        get_string('duedate', 'report_individualized'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_grade',
        get_string('grade', 'report_individualized'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_feedback',
        get_string('feedback', 'report_individualized'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_completion',
        get_string('completion', 'report_individualized'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_opendate',
        get_string('opendate', 'report_individualized'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_closedate',
        get_string('closedate', 'report_individualized'),
        '',
        1
    ));

    // Plage de consultation activités — désactivée par défaut.
    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_viewrange',
        get_string('viewrange', 'report_individualized'),
        '',
        0
    ));

    // Nombre de consultations activités — désactivé par défaut.
    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_viewcount',
        get_string('viewcount', 'report_individualized'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_individualized/actcol_estimatedduration',
        get_string('estimatedduration', 'report_individualized'),
        '',
        1
    ));

    // Advanced options section.

    $settings->add(new admin_setting_heading(
        'report_individualized/advancedheading',
        get_string('advancedsettings', 'report_individualized'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'report_individualized/workshop_feedback_type',
        get_string('workshop_feedback_type', 'report_individualized'),
        get_string('workshop_feedback_type_desc', 'report_individualized'),
        'both',
        [
            'both'       => get_string('workshop_feedback_both', 'report_individualized'),
            'submission' => get_string('workshop_feedback_submission', 'report_individualized'),
            'assessment' => get_string('workshop_feedback_assessment', 'report_individualized'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'report_individualized/categorydepth',
        get_string('categorydepth', 'report_individualized'),
        get_string('categorydepth_desc', 'report_individualized'),
        '0',
        PARAM_INT
    ));
}
