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
 * PDF export for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/pdflib.php');

use report_individualized\util\date_util;
use report_individualized\util\view_stats_util;
use report_individualized\util\completion_util;
use report_individualized\util\duration_util;
use report_individualized\util\feedback_util;
use report_individualized\util\workshop_util;
use report_individualized\util\summary_util;
use report_individualized\util\category_util;

// Enlève la limite de temps et monte la mémoire PHP.
set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// -------------------------------------------------------------------------
// 1. PARAMÈTRES ET PERMISSIONS
// -------------------------------------------------------------------------

$userid     = optional_param('userid',     0,  PARAM_INT);
$courseid   = optional_param('courseid',   0,  PARAM_INT);
$categoryid = optional_param('categoryid', 0,  PARAM_INT);
$sectionnum = optional_param('sectionnum', -1, PARAM_INT);
$tabletype  = optional_param('tabletype',  '',  PARAM_ALPHA);

$datefromstr = optional_param('datefrom', '', PARAM_ALPHANUMEXT);
$datetostr   = optional_param('dateto',   '', PARAM_ALPHANUMEXT);

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

$context = context_system::instance();
require_login();
require_capability('report/individualized:view', $context);

// -------------------------------------------------------------------------
// 2. LISTE DES UTILISATEURS À EXPORTER
// -------------------------------------------------------------------------

if ($userid > 0) {
    $singleuser = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
    $users      = [$singleuser];
} else {
    $singleuser  = null;
    $studentrole = $DB->get_record('role', ['shortname' => 'student']);
    $users       = $studentrole ? array_values($DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
           FROM {user} u
           JOIN {role_assignments} ra ON ra.userid = u.id
           JOIN {role} r ON r.id = ra.roleid
          WHERE r.shortname = 'student'
            AND u.deleted = 0
            AND u.suspended = 0
       ORDER BY u.lastname ASC, u.firstname ASC"
    )) : [];
}

$resourcetypes = ['resource', 'url', 'page', 'folder', 'book', 'label', 'file'];

// -------------------------------------------------------------------------
// 3. CRÉATION DU PDF
// -------------------------------------------------------------------------

$pdf = new pdf();
$pdf->SetAuthor(fullname($USER));
$pdf->SetTitle(
    $singleuser
        ? get_string('reportfor', 'report_individualized') . ' ' . fullname($singleuser)
        : get_string('allstudentsreport', 'report_individualized')
);
$pdf->SetCreator('Moodle ' . $CFG->release);
$pdf->SetMargins(15, 20, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 25);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage('L');

// -------------------------------------------------------------------------
// 4. CONTENU HTML
// -------------------------------------------------------------------------

$html  = '<h1 style="color:#333; font-size:18px;">'
    . ($singleuser
        ? get_string('reportfor', 'report_individualized') . ' : ' . fullname($singleuser)
        : get_string('allstudentsreport', 'report_individualized'))
    . '</h1>';
$html .= '<p style="color:#666; font-size:10px;">'
    . get_string('generatedon', 'report_individualized') . ' : ' . userdate(time()) . '</p>';

if ($datefrom > 0 || $dateto > 0) {
    $filterinfo = '';
    if ($datefrom > 0) {
        $filterinfo .= get_string('datefrom', 'report_individualized') . ' : ' . $datefromstr;
    }
    if ($datefrom > 0 && $dateto > 0) {
        $filterinfo .= ' — ';
    }
    if ($dateto > 0) {
        $filterinfo .= get_string('dateto', 'report_individualized') . ' : ' . $datetostr;
    }
    $html .= '<p style="color:#666; font-size:10px;">' . $filterinfo . '</p>';
}

$html .= '<hr/>';

if (empty($users)) {
    $html .= '<p>' . get_string('noenrolments', 'report_individualized') . '</p>';
} else {

    foreach ($users as $user) {

        // Titre par étudiant uniquement en mode "tous les étudiants".
        if (!$singleuser) {
            $html .= '<h2 style="color:#222; font-size:15px; margin-top:16px; border-bottom:1px solid #ccc;">'
                . get_string('reportfor', 'report_individualized') . ' : ' . fullname($user)
                . '</h2>';
        }

        // Cours de l'étudiant, avec filtre catégorie si actif.
        if ($courseid > 0) {
            $courses = [$courseid => get_course($courseid)];
        } else {
            $courses = enrol_get_users_courses($user->id, true, 'id, fullname, shortname, category');
            if ($categoryid !== 0) {
                $courses = array_column(
                    category_util::filter_courses_by_category($categoryid, array_values($courses)),
                    null,
                    'id'
                );
            }
        }

        if (empty($courses)) {
            $html .= '<p>' . get_string('noenrolments', 'report_individualized') . '</p>';
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

            // Pré-collecte pour le résumé global du cours.
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
                    if ($cm->modname === 'feedback'
                        && strpos(strtoupper(trim($cm->idnumber)), 'TIME') === 0) {
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

            $coursehtml = '';

            foreach ($allsections as $section) {

                if (empty($cmsbysection[$section->section])) {
                    continue;
                }

                // Filtre de section : si sectionnum est spécifié, on n'exporte que celle-là.
                if ($sectionnum >= 0 && $section->section !== $sectionnum) {
                    continue;
                }

                // Détection feedback TIME — exclu des tableaux.
                $timefeedbackcm = null;
                $visiblecms     = [];
                foreach ($cmsbysection[$section->section] as $cm) {
                    if ($cm->modname === 'feedback'
                        && strpos(strtoupper(trim($cm->idnumber)), 'TIME') === 0) {
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
                        if ($ts === 0) { $filtered[] = $cm; continue; }
                        if ($datefrom > 0 && $ts < $datefrom) { continue; }
                        if ($dateto   > 0 && $ts > $dateto)   { continue; }
                        $filtered[] = $cm;
                    }
                    $resources = $filtered;

                    $filtered = [];
                    foreach ($activities as $cm) {
                        $ts = date_util::get_module_availablefrom_timestamp($cm);
                        if ($ts === 0) { $filtered[] = $cm; continue; }
                        if ($datefrom > 0 && $ts < $datefrom) { continue; }
                        if ($dateto   > 0 && $ts > $dateto)   { continue; }
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

                $sectionname = !empty($section->name)
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
                $sectionbadge = summary_util::render_pdf($sectionsummary);

                $coursehtml .= '<h3 style="color:#444; font-size:12px; margin-top:10px;">'
                    . $sectionname . '</h3>';
                if (!empty($sectionbadge)) {
                    $coursehtml .= '<p style="color:#555; font-size:8px; margin-top:2px; margin-bottom:4px;">'
                        . $sectionbadge . '</p>';
                }

                // -----------------------------------------------------------------
                // TABLEAU RESSOURCES
                // -----------------------------------------------------------------
                if ((empty($tabletype) || $tabletype === 'resources') && !empty($resources)) {

                    $coursehtml .= '<h4 style="color:#555; font-size:11px;">'
                        . get_string('resources', 'report_individualized') . '</h4>';
                    $coursehtml .= '<table border="1" cellpadding="4" cellspacing="0"
                                          style="width:100%; font-size:9px; border-collapse:collapse;">';
                    $coursehtml .= '<tr style="background-color:#e8e8e8; font-weight:bold;">';
                    $coursehtml .= '<th>' . get_string('resourcename',      'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('availablefrom',     'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('viewed',            'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('viewrange',         'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('viewcount',         'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('estimatedduration', 'report_individualized') . '</th>';
                    $coursehtml .= '</tr>';

                    $rowindex = 0;
                    foreach ($resources as $cm) {
                        $viewstats = view_stats_util::get_view_stats($cm, $user->id, $datefrom, $dateto);
                        $bgcolor   = ($rowindex % 2 === 0) ? '#ffffff' : '#f5f5f5';
                        $coursehtml .= '<tr style="background-color:' . $bgcolor . ';">';
                        $coursehtml .= '<td>' . view_stats_util::get_activity_label($cm, null, true) . '</td>';
                        $coursehtml .= '<td>' . date_util::get_module_availablefrom($cm, true) . '</td>';
                        $coursehtml .= '<td>' . ($viewstats['count'] > 0 ? get_string('yes') : get_string('no')) . '</td>';
                        $coursehtml .= '<td>' . view_stats_util::format_view_range($viewstats, true) . '</td>';
                        $coursehtml .= '<td>' . ($viewstats['count'] > 0 ? $viewstats['count'] : '-') . '</td>';
                        $coursehtml .= '<td>' . duration_util::get_estimated_duration($cm) . '</td>';
                        $coursehtml .= '</tr>';
                        $rowindex++;
                    }
                    $coursehtml .= '</table><br/>';
                }

                // -----------------------------------------------------------------
                // TABLEAU ACTIVITÉS
                // -----------------------------------------------------------------
                if ((empty($tabletype) || $tabletype === 'activities') && !empty($activities)) {

                    $coursehtml .= '<h4 style="color:#555; font-size:11px;">'
                        . get_string('activities', 'report_individualized') . '</h4>';
                    $coursehtml .= '<table border="1" cellpadding="4" cellspacing="0"
                                          style="width:100%; font-size:9px; border-collapse:collapse;">';
                    $coursehtml .= '<tr style="background-color:#e8e8e8; font-weight:bold;">';
                    $coursehtml .= '<th>' . get_string('activityname',      'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('availablefrom',     'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('duedate',           'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('grade',             'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('feedback',          'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('completion',        'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('opendate',          'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('closedate',         'report_individualized') . '</th>';
                    $coursehtml .= '<th>' . get_string('estimatedduration', 'report_individualized') . '</th>';
                    $coursehtml .= '</tr>';

                    $rowindex = 0;
                    foreach ($activities as $cm) {

                        $firstview = $DB->get_record_select(
                            'logstore_standard_log',
                            'userid = :userid AND contextinstanceid = :cmid
                             AND contextlevel = :ctxlevel AND action = :action',
                            ['userid' => $user->id, 'cmid' => $cm->id,
                             'ctxlevel' => CONTEXT_MODULE, 'action' => 'viewed'],
                            'id, timecreated',
                            IGNORE_MULTIPLE
                        );
                        $opendate = !empty($firstview)
                            ? date_util::format_datetime($firstview->timecreated, true)
                            : '-';

                        $availablefrom = date_util::get_module_availablefrom($cm, true);
                        $completionstr = completion_util::get_completion_icon($cm, $user->id, true);

                        if ($cm->modname === 'workshop') {
                            $workshopitems = workshop_util::get_workshop_items($cm, $user->id, $course->id, true);
                            foreach ($workshopitems as $item) {
                                $bgcolor     = ($rowindex % 2 === 0) ? '#ffffff' : '#f5f5f5';
                                $coursehtml .= '<tr style="background-color:' . $bgcolor . ';">';
                                $coursehtml .= '<td>' . view_stats_util::get_activity_label($cm, $item['label'], true) . '</td>';
                                $coursehtml .= '<td>' . $availablefrom . '</td>';
                                $coursehtml .= '<td>' . $item['duedatestr'] . '</td>';
                                $coursehtml .= '<td>' . $item['gradestr'] . '</td>';
                                $coursehtml .= '<td>' . $item['feedbackstr'] . '</td>';
                                $coursehtml .= '<td>' . $item['completionicon'] . '</td>';
                                $coursehtml .= '<td>' . $opendate . '</td>';
                                $coursehtml .= '<td>' . $item['closedatestr'] . '</td>';
                                $coursehtml .= '<td>' . duration_util::get_estimated_duration($cm, $item['isassessment']) . '</td>';
                                $coursehtml .= '</tr>';
                                $rowindex++;
                            }
                            continue;
                        }

                        $submission = $DB->get_record_select(
                            'logstore_standard_log',
                            'userid = :userid AND contextinstanceid = :cmid AND action = :action',
                            ['userid' => $user->id, 'cmid' => $cm->id, 'action' => 'submitted'],
                            'id, timecreated',
                            IGNORE_MULTIPLE
                        );
                        $closedate = !empty($submission)
                            ? date_util::format_datetime($submission->timecreated, true)
                            : '-';

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

                        $bgcolor     = ($rowindex % 2 === 0) ? '#ffffff' : '#f5f5f5';
                        $coursehtml .= '<tr style="background-color:' . $bgcolor . ';">';
                        $coursehtml .= '<td>' . view_stats_util::get_activity_label($cm, null, true) . '</td>';
                        $coursehtml .= '<td>' . $availablefrom . '</td>';
                        $coursehtml .= '<td>' . date_util::get_module_duedate($cm, $user->id, true) . '</td>';
                        $coursehtml .= '<td>' . $gradestr . '</td>';
                        $coursehtml .= '<td>' . feedback_util::get_activity_feedback($cm, $user->id, true) . '</td>';
                        $coursehtml .= '<td>' . $completionstr . '</td>';
                        $coursehtml .= '<td>' . $opendate . '</td>';
                        $coursehtml .= '<td>' . $closedate . '</td>';
                        $coursehtml .= '<td>' . duration_util::get_estimated_duration($cm) . '</td>';
                        $coursehtml .= '</tr>';
                        $rowindex++;
                    }
                    $coursehtml .= '</table>';
                }

                $coursehtml .= '<br/>';

            } // fin foreach sections

            if (!empty($coursehtml)) {
                $html .= '<h2 style="color:#333; font-size:14px; margin-top:20px;">'
                    . format_string($course->fullname) . '</h2>';
                $catpath = category_util::get_category_path((int)($course->category ?? 0), true);
                if (!empty($catpath)) {
                    $html .= '<p style="color:#888; font-size:9px; margin-top:-8px;">'
                        . $catpath . '</p>';
                }
                $globalbadge = summary_util::render_pdf($globalsummary);
                if (!empty($globalbadge)) {
                    $html .= '<p style="color:#444; font-size:9px; font-style:italic; margin-bottom:6px;">'
                        . $globalbadge . '</p>';
                }
                $html .= $coursehtml;
            }

        } // fin foreach courses

    } // fin foreach users
}

// -------------------------------------------------------------------------
// 5. RENDU ET TÉLÉCHARGEMENT
// -------------------------------------------------------------------------

$pdf->writeHTML($html, true, false, true, false, '');

$filename = $singleuser
    ? clean_filename('rapport_' . $singleuser->firstname . '_' . $singleuser->lastname . '_' . date('Ymd') . '.pdf')
    : clean_filename('rapport_tous_etudiants_' . date('Ymd') . '.pdf');

$pdf->Output($filename, 'D');
exit;
