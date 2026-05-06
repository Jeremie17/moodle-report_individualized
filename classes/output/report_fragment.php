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
 * Report fragment renderer for report_individualized.
 *
 * Contains all rendering logic for the AJAX fragment callback.
 * Called by lib.php::report_individualized_output_fragment_report().
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\output;

defined('MOODLE_INTERNAL') || die();

use report_individualized\util\date_util;
use report_individualized\util\view_stats_util;
use report_individualized\util\completion_util;
use report_individualized\util\duration_util;
use report_individualized\util\feedback_util;
use report_individualized\util\workshop_util;
use report_individualized\util\summary_util;
use html_writer;
use report_individualized\util\category_util;

/**
 * Renders the report HTML for the AJAX fragment endpoint.
 */
class report_fragment
{
    /**
     * Renders the full report content and returns it as an HTML string.
     *
     * Receives parameters from the AMD module (core/fragment) via lib.php shell.
     * Validates, queries, and renders all sections/tables for the selected filters.
     *
     * @param  array $args {
     *     userid     => int    Étudiant sélectionné (0 = tous).
     *     courseid   => int    Cours sélectionné (0 = tous).
     *     categoryid => int    Catégorie sélectionnée (0 = toutes, -1 = sans sous-catégorie).
     *     datefrom   => string Date de début YYYY-MM-DD ('' = pas de filtre).
     *     dateto     => string Date de fin   YYYY-MM-DD ('' = pas de filtre).
     * }
     * @return string HTML du rapport.
     */
    public static function render(array $args): string {
        global $DB, $OUTPUT, $CFG;
        require_once($CFG->libdir . '/tablelib.php');
        require_once($CFG->dirroot . '/report/individualized/lib.php');

        $context = \context_system::instance();
        require_capability('report/individualized:view', $context);

        // Parameters.
        $userid      = isset($args['userid']) ? clean_param($args['userid'], PARAM_INT) : 0;
        $courseid    = isset($args['courseid']) ? clean_param($args['courseid'], PARAM_INT) : 0;
        $categoryid  = isset($args['categoryid']) ? clean_param($args['categoryid'], PARAM_INT) : 0;
        $datefromstr = isset($args['datefrom']) ? clean_param($args['datefrom'], PARAM_ALPHANUMEXT) : '';
        $datetostr   = isset($args['dateto']) ? clean_param($args['dateto'], PARAM_ALPHANUMEXT) : '';

        $datefrom = 0;
        $dateto   = 0;

        if (!empty($datefromstr) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datefromstr)) {
            [$y, $m, $d] = explode('-', $datefromstr);
            $datefrom = (int)make_timestamp((int)$y, (int)$m, (int)$d, 0, 0, 0);
        }
        if (!empty($datetostr) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetostr)) {
            [$y, $m, $d] = explode('-', $datetostr);
            $dateto = (int)make_timestamp((int)$y, (int)$m, (int)$d, 23, 59, 59);
        }

        // Visible columns.
        $rescols = [];
        foreach (['resourcename', 'availablefrom', 'viewed', 'viewrange', 'viewcount', 'estimatedduration'] as $col) {
            $val = get_config('report_individualized', 'rescol_' . $col);
            $rescols[$col] = ($val === false) ? true : (bool)(int)$val;
        }
        $actcols = [];
        foreach (
            ['activityname', 'availablefrom', 'duedate', 'grade', 'feedback', 'completion',
                  'opendate', 'closedate', 'viewrange', 'viewcount', 'estimatedduration'] as $col
        ) {
            $val = get_config('report_individualized', 'actcol_' . $col);
            $actcols[$col] = ($val === false) ? true : (bool)(int)$val;
        }

        // Users to display.
        $userstoshow = [];
        if ($userid > 0) {
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
            if ($user) {
                $userstoshow[] = $user;
            }
        } else {
            $studentrole = $DB->get_record('role', ['shortname' => 'student']);
            if ($studentrole) {
                $userstoshow = array_values($DB->get_records_sql(
                    "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                            u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                       FROM {user} u
                       JOIN {role_assignments} ra ON ra.userid = u.id
                       JOIN {role} r ON r.id = ra.roleid
                      WHERE r.shortname = 'student'
                        AND u.deleted = 0
                        AND u.suspended = 0
                  ORDER BY u.lastname ASC, u.firstname ASC"
                ));
            }
        }

        if (empty($userstoshow)) {
            return '';
        }

        // Column headers.
        $connector      = get_string('columnheader_connector', 'report_individualized');
        $rheadertype    = get_string('resourcename_type', 'report_individualized')
            . $connector . '<br>'
            . get_string('resourcename_modality', 'report_individualized');
        $aheadertype    = get_string('activityname_type', 'report_individualized')
            . $connector . '<br>'
            . get_string('activityname_modality', 'report_individualized');
        $headerduration  = get_string('estimatedduration_line1', 'report_individualized') . '<br>'
            . get_string('estimatedduration_line2', 'report_individualized');
        $headerviewcount = get_string('viewcount_line1', 'report_individualized') . '<br>'
            . get_string('viewcount_line2', 'report_individualized');

        $resourcetypes = ['resource', 'url', 'page', 'folder', 'book', 'label', 'file'];

        ob_start();

        foreach ($userstoshow as $user) {
            echo $OUTPUT->heading(
                get_string('reportfor', 'report_individualized') . ' : ' . fullname($user),
                3
            );

            // Récupère les cours avec le champ category pour le filtrage.
            if ($courseid > 0) {
                $courses = [$courseid => get_course($courseid)];
            } else {
                $courses = enrol_get_users_courses($user->id, true, 'id, fullname, shortname, category');
                // Filtre catégorie.
                if ($categoryid !== 0) {
                    $courses = array_column(
                        category_util::filter_courses_by_category($categoryid, array_values($courses)),
                        null,
                        'id'
                    );
                }
            }

            if (empty($courses)) {
                echo $OUTPUT->notification(get_string('noenrolments', 'report_individualized'), 'info');
                continue;
            }

            foreach ($courses as $course) {
                $modinfo     = get_fast_modinfo($course, $user->id);
                $allsections = $modinfo->get_section_info_all();

                $cmsbysection = [];
                foreach ($modinfo->get_cms() as $cm) {
                    if ($cm->uservisible) {
                        $cmsbysection[$cm->sectionnum][] = $cm;
                    }
                }

                // Pré-collecte de tous les CMs du cours pour le résumé global.
                $globalresources     = [];
                $globalactivities    = [];
                $globaltimefeedbacks = [];
                foreach ($allsections as $section) {
                    if (empty($cmsbysection[$section->section])) {
                        continue;
                    }
                    if ($section->section === 0 && empty($section->name)) {
                        continue;
                    }
                    foreach ($cmsbysection[$section->section] as $cm) {
                        if (
                            $cm->modname === 'feedback'
                            && strpos(strtoupper(trim($cm->idnumber)), 'TIME') === 0
                        ) {
                            $globaltimefeedbacks[] = $cm;
                        } else if (in_array($cm->modname, $resourcetypes)) {
                            $globalresources[] = $cm;
                        } else {
                            $globalactivities[] = $cm;
                        }
                    }
                }
                if ($datefrom > 0 || $dateto > 0) {
                    $filtered = [];
                    foreach ($globalresources as $cm) {
                        $ts = date_util::get_module_availablefrom_timestamp($cm);
                        if ($ts === 0 || (!($datefrom > 0 && $ts < $datefrom) && !($dateto > 0 && $ts > $dateto))) {
                            $filtered[] = $cm;
                        }
                    }
                    $globalresources = $filtered;
                    $filtered = [];
                    foreach ($globalactivities as $cm) {
                        $ts = date_util::get_module_availablefrom_timestamp($cm);
                        if ($ts === 0 || (!($datefrom > 0 && $ts < $datefrom) && !($dateto > 0 && $ts > $dateto))) {
                            $filtered[] = $cm;
                        }
                    }
                    $globalactivities = $filtered;
                }
                $globalsummary = summary_util::compute(
                    $globalresources,
                    $globalactivities,
                    $globaltimefeedbacks,
                    $user->id,
                    $datefrom,
                    $dateto
                );

                ob_start();

                foreach ($allsections as $section) {
                    if (empty($cmsbysection[$section->section])) {
                        continue;
                    }

                    // Détection feedback TIME.
                    $timefeedbackcm = null;
                    $visiblecms     = [];
                    foreach ($cmsbysection[$section->section] as $cm) {
                        if (
                            $cm->modname === 'feedback'
                            && strpos(strtoupper(trim($cm->idnumber)), 'TIME') === 0
                        ) {
                            $timefeedbackcm = $cm;
                        } else {
                            $visiblecms[] = $cm;
                        }
                    }

                    $resources  = [];
                    $activities = [];
                    foreach ($visiblecms as $cm) {
                        if (in_array($cm->modname, $resourcetypes)) {
                            $resources[] = $cm;
                        } else {
                            $activities[] = $cm;
                        }
                    }

                    // Filtrage par plage de dates.
                    if ($datefrom > 0 || $dateto > 0) {
                        $filtered = [];
                        foreach ($resources as $cm) {
                            $ts = date_util::get_module_availablefrom_timestamp($cm);
                            if ($ts === 0) {
                                $filtered[] = $cm;
                                continue;
                            }
                            if ($datefrom > 0 && $ts < $datefrom) {
                                continue;
                            }
                            if ($dateto > 0 && $ts > $dateto) {
                                continue;
                            }
                            $filtered[] = $cm;
                        }
                        $resources = $filtered;

                        $filtered = [];
                        foreach ($activities as $cm) {
                            $ts = date_util::get_module_availablefrom_timestamp($cm);
                            if ($ts === 0) {
                                $filtered[] = $cm;
                                continue;
                            }
                            if ($datefrom > 0 && $ts < $datefrom) {
                                continue;
                            }
                            if ($dateto > 0 && $ts > $dateto) {
                                continue;
                            }
                            $filtered[] = $cm;
                        }
                        $activities = $filtered;
                    }

                    if ($section->section === 0 && empty($section->name)) {
                        continue;
                    }
                    if (empty($resources) && empty($activities)) {
                        continue;
                    }

                    $sectionname   = !empty($section->name)
                        ? format_string($section->name)
                        : get_string('unnamedsection', 'report_individualized');
                    $sectionsummary = summary_util::compute(
                        $resources,
                        $activities,
                        $timefeedbackcm ? [$timefeedbackcm] : [],
                        $user->id,
                        $datefrom,
                        $dateto
                    );

                    echo html_writer::start_div(
                        'd-flex align-items-center gap-3 mb-2 report-individualized-section-header'
                    );
                    echo html_writer::tag('h5', get_string('section') . ' : ' . $sectionname, ['class' => 'mb-0']);
                    echo html_writer::end_div();
                    echo summary_util::render_pills($sectionsummary);

                    // Resources table.
                    if (!empty($resources)) {
                        echo html_writer::start_div('report-individualized-table-wrap');
                        $tablepdfurl = new \moodle_url('/report/individualized/export_pdf.php', [
                            'userid'     => $user->id,
                            'courseid'   => $course->id,
                            'categoryid' => $categoryid,
                            'datefrom'   => $datefromstr,
                            'dateto'     => $datetostr,
                            'sectionnum' => $section->section,
                            'tabletype'  => 'resources',
                        ]);
                        echo html_writer::link(
                            $tablepdfurl,
                            get_string('exportpdf', 'report_individualized'),
                            ['class' => 'btn btn-sm btn-outline-dark report-individualized-table-pdf-btn']
                        );
                        echo $OUTPUT->heading(get_string('resources', 'report_individualized'), 6);

                        $rcolumns = [];
                        $rheaders = [];
                        if ($rescols['resourcename']) {
                            $rcolumns[] = 'resourcename';
                            $rheaders[] = $rheadertype;
                        }
                        if ($rescols['availablefrom']) {
                            $rcolumns[] = 'availablefrom';
                            $rheaders[] = get_string('availablefrom', 'report_individualized');
                        }
                        if ($rescols['viewed']) {
                            $rcolumns[] = 'viewed';
                            $rheaders[] = get_string('viewed', 'report_individualized');
                        }
                        if ($rescols['viewrange']) {
                            $rcolumns[] = 'viewrange';
                            $rheaders[] = get_string('viewrange', 'report_individualized');
                        }
                        if ($rescols['viewcount']) {
                            $rcolumns[] = 'viewcount';
                            $rheaders[] = $headerviewcount;
                        }
                        if ($rescols['estimatedduration']) {
                            $rcolumns[] = 'estimatedduration';
                            $rheaders[] = $headerduration;
                        }

                        // L'ID unique inclut le numéro de section pour éviter les clashes
                        // quand un cours a plusieurs sections sur la même page AJAX.
                        $rtable = new \flexible_table(
                            'rpt-ind-res-' . $course->id . '-' . $user->id . '-s' . $section->section
                        );
                        $rtable->define_columns($rcolumns);
                        $rtable->define_headers($rheaders);
                        $rtable->define_baseurl(new \moodle_url('/report/individualized/index.php'));
                        $rtable->set_attribute('class', 'generaltable local-individualized-table w-100');
                        $rtable->setup();

                        foreach ($resources as $cm) {
                            $vs  = view_stats_util::get_view_stats($cm, $user->id, $datefrom, $dateto);
                            $row = [];
                            if ($rescols['resourcename']) {
                                $row[] = view_stats_util::get_activity_label($cm);
                            }
                            if ($rescols['availablefrom']) {
                                $row[] = date_util::get_module_availablefrom($cm);
                            }
                            if ($rescols['viewed']) {
                                $row[] = $vs['count'] > 0 ? get_string('yes') : get_string('no');
                            }
                            if ($rescols['viewrange']) {
                                $row[] = view_stats_util::format_view_range($vs);
                            }
                            if ($rescols['viewcount']) {
                                $row[] = $vs['count'] > 0 ? $vs['count'] : '-';
                            }
                            if ($rescols['estimatedduration']) {
                                $row[] = duration_util::get_estimated_duration($cm);
                            }
                            $rtable->add_data($row);
                        }
                        $rtable->finish_output();
                        echo html_writer::end_div();
                    }

                    // Activities table.
                    if (!empty($activities)) {
                        echo html_writer::start_div('report-individualized-table-wrap');
                        $tablepdfurl = new \moodle_url('/report/individualized/export_pdf.php', [
                            'userid'     => $user->id,
                            'courseid'   => $course->id,
                            'categoryid' => $categoryid,
                            'datefrom'   => $datefromstr,
                            'dateto'     => $datetostr,
                            'sectionnum' => $section->section,
                            'tabletype'  => 'activities',
                        ]);
                        echo html_writer::link(
                            $tablepdfurl,
                            get_string('exportpdf', 'report_individualized'),
                            ['class' => 'btn btn-sm btn-outline-dark report-individualized-table-pdf-btn']
                        );
                        echo $OUTPUT->heading(get_string('activities', 'report_individualized'), 6);

                        $acolumns = [];
                        $aheaders = [];
                        if ($actcols['activityname']) {
                            $acolumns[] = 'activityname';
                            $aheaders[] = $aheadertype;
                        }
                        if ($actcols['availablefrom']) {
                            $acolumns[] = 'availablefrom';
                            $aheaders[] = get_string('availablefrom', 'report_individualized');
                        }
                        if ($actcols['duedate']) {
                            $acolumns[] = 'duedate';
                            $aheaders[] = get_string('duedate', 'report_individualized');
                        }
                        if ($actcols['grade']) {
                            $acolumns[] = 'grade';
                            $aheaders[] = get_string('grade', 'report_individualized');
                        }
                        if ($actcols['feedback']) {
                            $acolumns[] = 'feedback';
                            $aheaders[] = get_string('feedback', 'report_individualized');
                        }
                        if ($actcols['completion']) {
                            $acolumns[] = 'completion';
                            $aheaders[] = get_string('completion', 'report_individualized');
                        }
                        if ($actcols['opendate']) {
                            $acolumns[] = 'opendate';
                            $aheaders[] = get_string('opendate', 'report_individualized');
                        }
                        if ($actcols['closedate']) {
                            $acolumns[] = 'closedate';
                            $aheaders[] = get_string('closedate', 'report_individualized');
                        }
                        if ($actcols['viewrange']) {
                            $acolumns[] = 'viewrange';
                            $aheaders[] = get_string('viewrange', 'report_individualized');
                        }
                        if ($actcols['viewcount']) {
                            $acolumns[] = 'viewcount';
                            $aheaders[] = $headerviewcount;
                        }
                        if ($actcols['estimatedduration']) {
                            $acolumns[] = 'estimatedduration';
                            $aheaders[] = $headerduration;
                        }

                        $atable = new \flexible_table(
                            'rpt-ind-act-' . $course->id . '-' . $user->id . '-s' . $section->section
                        );
                        $atable->define_columns($acolumns);
                        $atable->define_headers($aheaders);
                        $atable->define_baseurl(new \moodle_url('/report/individualized/index.php'));
                        $atable->set_attribute('class', 'generaltable local-individualized-table w-100');
                        $atable->setup();

                        foreach ($activities as $cm) {
                            $openparams = [
                                'userid'   => $user->id,
                                'cmid'     => $cm->id,
                                'action'   => 'viewed',
                                'ctxlevel' => CONTEXT_MODULE,
                            ];
                            $openwhere = 'userid = :userid AND contextinstanceid = :cmid
                                          AND contextlevel = :ctxlevel AND action = :action';
                            if ($datefrom > 0) {
                                $openwhere .= ' AND timecreated >= :datefrom';
                                $openparams['datefrom'] = $datefrom;
                            }
                            if ($dateto > 0) {
                                $openwhere .= ' AND timecreated <= :dateto';
                                $openparams['dateto'] = $dateto;
                            }
                            $firstview  = $DB->get_record_select(
                                'logstore_standard_log',
                                $openwhere,
                                $openparams,
                                'id, timecreated',
                                IGNORE_MULTIPLE
                            );
                            $opendate       = !empty($firstview)
                                ? date_util::format_datetime($firstview->timecreated) : '-';
                            $availablefrom  = date_util::get_module_availablefrom($cm);
                            $completionicon = completion_util::get_completion_icon($cm, $user->id);
                            $vs             = view_stats_util::get_view_stats(
                                $cm,
                                $user->id,
                                $datefrom,
                                $dateto
                            );

                            if ($cm->modname === 'workshop') {
                                $items = workshop_util::get_workshop_items($cm, $user->id, $course->id);
                                foreach ($items as $item) {
                                    $row = [];
                                    if ($actcols['activityname']) {
                                        $row[] = view_stats_util::get_activity_label($cm, $item['label']);
                                    }
                                    if ($actcols['availablefrom']) {
                                        $row[] = $availablefrom;
                                    }
                                    if ($actcols['duedate']) {
                                        $row[] = $item['duedatestr'];
                                    }
                                    if ($actcols['grade']) {
                                        $row[] = $item['gradestr'];
                                    }
                                    if ($actcols['feedback']) {
                                        $row[] = $item['feedbackstr'];
                                    }
                                    if ($actcols['completion']) {
                                        $row[] = $item['completionicon'];
                                    }
                                    if ($actcols['opendate']) {
                                        $row[] = $opendate;
                                    }
                                    if ($actcols['closedate']) {
                                        $row[] = $item['closedatestr'];
                                    }
                                    if ($actcols['viewrange']) {
                                        $row[] = view_stats_util::format_view_range($vs);
                                    }
                                    if ($actcols['viewcount']) {
                                        $row[] = $vs['count'] > 0 ? $vs['count'] : '-';
                                    }
                                    if ($actcols['estimatedduration']) {
                                        $row[] = duration_util::get_estimated_duration($cm, $item['isassessment']);
                                    }
                                    $atable->add_data($row);
                                }
                                continue;
                            }

                            if ($cm->modname === 'h5pactivity') {
                                $h5pclose = $DB->get_record_select(
                                    'logstore_standard_log',
                                    'userid = :userid AND contextinstanceid = :cmid AND component = :component AND action = :action',
                                    [
                                        'userid'    => $user->id,
                                        'cmid'      => $cm->id,
                                        'component' => 'mod_h5pactivity',
                                        'action'    => 'received',
                                    ],
                                    'id, timecreated',
                                    IGNORE_MULTIPLE
                                );
                                $closedate = !empty($h5pclose)
                                    ? date_util::format_datetime((int)$h5pclose->timecreated) : '-';
                            } else {
                                $closeparams = [
                                    'userid'   => $user->id,
                                    'cmid'     => $cm->id,
                                    'action'   => 'submitted',
                                    'ctxlevel' => CONTEXT_MODULE,
                                ];
                                $closewhere = 'userid = :userid AND contextinstanceid = :cmid
                                               AND contextlevel = :ctxlevel AND action = :action';
                                if ($datefrom > 0) {
                                    $closewhere .= ' AND timecreated >= :datefrom';
                                    $closeparams['datefrom'] = $datefrom;
                                }
                                if ($dateto > 0) {
                                    $closewhere .= ' AND timecreated <= :dateto';
                                    $closeparams['dateto'] = $dateto;
                                }
                                $submission = $DB->get_record_select(
                                    'logstore_standard_log',
                                    $closewhere,
                                    $closeparams,
                                    'id, timecreated',
                                    IGNORE_MULTIPLE
                                );
                                $closedate = !empty($submission)
                                    ? date_util::format_datetime($submission->timecreated) : '-';
                            }

                            $gradestr  = '-';
                            $gradeitem = $DB->get_record('grade_items', [
                                'itemtype'     => 'mod',
                                'itemmodule'   => $cm->modname,
                                'iteminstance' => $cm->instance,
                                'courseid'     => $course->id,
                            ]);
                            if ($gradeitem) {
                                $grade = $DB->get_record('grade_grades', [
                                    'itemid' => $gradeitem->id,
                                    'userid' => $user->id,
                                ]);
                                if ($grade && $grade->finalgrade !== null) {
                                    $gradestr = round($grade->finalgrade, 2)
                                        . ' / ' . round($gradeitem->grademax, 2);
                                }
                            }

                            $row = [];
                            if ($actcols['activityname']) {
                                $row[] = view_stats_util::get_activity_label($cm);
                            }
                            if ($actcols['availablefrom']) {
                                $row[] = $availablefrom;
                            }
                            if ($actcols['duedate']) {
                                $row[] = date_util::get_module_duedate($cm, $user->id);
                            }
                            if ($actcols['grade']) {
                                $row[] = $gradestr;
                            }
                            if ($actcols['feedback']) {
                                $row[] = feedback_util::get_activity_feedback($cm, $user->id);
                            }
                            if ($actcols['completion']) {
                                $row[] = $completionicon;
                            }
                            if ($actcols['opendate']) {
                                $row[] = $opendate;
                            }
                            if ($actcols['closedate']) {
                                $row[] = $closedate;
                            }
                            if ($actcols['viewrange']) {
                                $row[] = view_stats_util::format_view_range($vs);
                            }
                            if ($actcols['viewcount']) {
                                $row[] = $vs['count'] > 0 ? $vs['count'] : '-';
                            }
                            if ($actcols['estimatedduration']) {
                                $row[] = duration_util::get_estimated_duration($cm);
                            }
                            $atable->add_data($row);
                        }
                        $atable->finish_output();
                        echo html_writer::end_div();
                    }
                } // fin foreach sections

                $courseoutput = ob_get_clean();
                if (!empty(trim($courseoutput))) {
                    echo html_writer::start_div('report-individualized-course-block');
                    echo $OUTPUT->heading(get_string('course', 'moodle') . ' : ' . format_string($course->fullname), 4);

                    // Chemin de catégorie affiché sous le titre du cours.
                    $catpath = category_util::get_category_path(
                        (int)($course->category ?? 0),
                        true
                    );
                    if (!empty($catpath)) {
                        echo html_writer::tag(
                            'p',
                            $catpath,
                            ['class' => 'text-muted small mb-2 report-individualized-catpath']
                        );
                    }

                    echo summary_util::render_pills($globalsummary);
                    echo $courseoutput;
                    echo html_writer::end_div();
                }
            } // fin foreach courses
        } // fin foreach users

        return ob_get_clean();
    }
}
