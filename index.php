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
 * Main page for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/tablelib.php');

use report_individualized\util\date_util;
use report_individualized\util\view_stats_util;
use report_individualized\util\completion_util;
use report_individualized\util\duration_util;
use report_individualized\util\feedback_util;
use report_individualized\util\workshop_util;
use report_individualized\util\summary_util;
use report_individualized\util\category_util;

// -------------------------------------------------------------------------
// 1. URL PARAMETERS
// -------------------------------------------------------------------------

$userid     = optional_param('userid', 0, PARAM_INT);
$courseid   = optional_param('courseid', 0, PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);

// Date filters received as YYYY-MM-DD strings from the date input.
// PARAM_ALPHANUMEXT allows digits and hyphens (e.g. "2026-04-13").
// Conversion to Unix timestamps uses make_timestamp() which respects
// the Moodle user's timezone.
$datefromstr = optional_param('datefrom', '', PARAM_ALPHANUMEXT);
$datetostr   = optional_param('dateto', '', PARAM_ALPHANUMEXT);

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

// -------------------------------------------------------------------------
// 2. CONTEXT AND PERMISSIONS
// -------------------------------------------------------------------------

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/individualized/index.php', [
    'userid'     => $userid,
    'courseid'   => $courseid,
    'categoryid' => $categoryid,
    'datefrom'   => $datefromstr,
    'dateto'     => $datetostr,
]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'report_individualized'));
$PAGE->set_heading(get_string('pluginname', 'report_individualized'));

require_login();
require_capability('report/individualized:view', $context);

// -------------------------------------------------------------------------
// 3. VISIBLE COLUMNS SETTINGS (admin parameters)
// -------------------------------------------------------------------------

$rescols = [];
foreach (['resourcename', 'availablefrom', 'viewed', 'viewrange', 'viewcount', 'estimatedduration'] as $col) {
    $val = get_config('report_individualized', 'rescol_' . $col);
    $rescols[$col] = ($val === false) ? true : (bool)(int)$val;
}

$actcols = [];
$activitycols = ['activityname', 'availablefrom', 'duedate', 'grade', 'feedback',
    'completion', 'opendate', 'closedate', 'viewrange', 'viewcount', 'estimatedduration'];
foreach ($activitycols as $col) {
    $val = get_config('report_individualized', 'actcol_' . $col);
    $actcols[$col] = ($val === false) ? true : (bool)(int)$val;
}

// -------------------------------------------------------------------------
// 4. FILTER DATA
// -------------------------------------------------------------------------

$studentrole = $DB->get_record('role', ['shortname' => 'student']);

$allusers = [];
if ($studentrole) {
    if ($courseid > 0) {
        $coursecontext = context_course::instance($courseid);
        $allusers = get_role_users(
            $studentrole->id,
            $coursecontext,
            false,
            'u.id, u.firstname, u.lastname, u.email',
            'u.lastname ASC, u.firstname ASC'
        );
    } else {
        $allusers = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
               FROM {user} u
               JOIN {role_assignments} ra ON ra.userid = u.id
               JOIN {role} r ON r.id = ra.roleid
              WHERE r.shortname = 'student'
                AND u.deleted = 0
                AND u.suspended = 0
           ORDER BY u.lastname ASC, u.firstname ASC"
        );
    }
}

// Course list for the selector: the category field is required for filtering.
$allcourses = [];
if ($userid > 0) {
    $allcourses = enrol_get_users_courses($userid, true, 'id, fullname, shortname, category', 'fullname ASC');
} else {
    $allcourses = $DB->get_records_select('course', 'id <> 1', [], 'fullname ASC', 'id, fullname, shortname, category');
}

// Apply category filter to the course selector list.
if ($categoryid !== 0) {
    $allcourses = array_column(
        report_individualized_filter_courses_by_category($categoryid, array_values($allcourses)),
        null,
        'id'
    );
}

// Category options for the selector (built from courses with enrolled learners).
$categoryoptions = category_util::get_category_options($userid > 0 ? $userid : 0);

// -------------------------------------------------------------------------
// 5. HTML RENDERING
// -------------------------------------------------------------------------

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'report_individualized'));

// Initialises the AMD filters.js module with the system context ID and current categoryid.
// The module intercepts filter changes and refreshes the report
// via AJAX using core/ajax (get_filter_options) and core/fragment (report rendering).
$PAGE->requires->js_call_amd(
    'report_individualized/filters',
    'init',
    [$context->id, $categoryid]
);

// Sticky bar: filters and PDF button.
$pdfurl = new moodle_url('/report/individualized/export_pdf.php', [
    'userid'     => $userid,
    'courseid'   => $courseid,
    'categoryid' => $categoryid,
    'datefrom'   => $datefromstr,
    'dateto'     => $datetostr,
]);

echo html_writer::start_div('report-individualized-filters');
echo html_writer::start_div('report-individualized-filters-inner d-flex justify-content-between align-items-center');

// Left side: filter form.
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/report/individualized/index.php'),
    'class'  => 'd-flex align-items-center gap-3 flex-grow-1 flex-wrap',
]);

// Category filter (displayed first).
echo html_writer::tag(
    'label',
    get_string('selectcategory', 'report_individualized'),
    ['for' => 'categoryid', 'class' => 'mb-0 me-2']
);

$catopts = [0 => get_string('allcategories', 'report_individualized')];
foreach ($categoryoptions as $opt) {
    $catopts[$opt['id']] = $opt['path'];
}
echo html_writer::select($catopts, 'categoryid', $categoryid, false, [
    'id'    => 'categoryid',
    'class' => 'form-select me-3',
]);

// Student filter.
echo html_writer::tag(
    'label',
    get_string('selectuser', 'report_individualized'),
    ['for' => 'userid', 'class' => 'mb-0 me-2']
);

$useroptions = [0 => get_string('allusers', 'report_individualized')];
foreach ($allusers as $u) {
    $useroptions[$u->id] = fullname($u);
}
echo html_writer::select($useroptions, 'userid', $userid, false, [
    'id'    => 'userid',
    'class' => 'form-select me-3',
]);

// Course filter.
echo html_writer::tag(
    'label',
    get_string('selectcourse', 'report_individualized'),
    ['for' => 'courseid', 'class' => 'mb-0 me-2']
);

$courseoptions = [0 => get_string('allcourses', 'report_individualized')];
foreach ($allcourses as $c) {
    $courseoptions[$c->id] = format_string($c->fullname);
}
echo html_writer::select($courseoptions, 'courseid', $courseid, false, [
    'id'    => 'courseid',
    'class' => 'form-select me-3',
]);

// Date filters.
echo html_writer::tag(
    'label',
    get_string('datefrom', 'report_individualized'),
    ['for' => 'datefrom', 'class' => 'mb-0 me-2']
);
echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'id'    => 'datefrom',
    'name'  => 'datefrom',
    'value' => $datefromstr,
    'class' => 'form-control me-3 report-individualized-date-input',
]);

echo html_writer::tag(
    'label',
    get_string('dateto', 'report_individualized'),
    ['for' => 'dateto', 'class' => 'mb-0 me-2']
);
echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'id'    => 'dateto',
    'name'  => 'dateto',
    'value' => $datetostr,
    'max'   => date('Y-m-d'),
    'class' => 'form-control me-3 report-individualized-date-input',
]);

echo html_writer::tag(
    'button',
    get_string('applyfilter', 'report_individualized'),
    ['type' => 'submit', 'class' => 'btn btn-primary me-2']
);

echo html_writer::link(
    new moodle_url('/report/individualized/index.php'),
    get_string('resetfilter', 'report_individualized'),
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_tag('form');

// PDF button always present in the DOM — shown/hidden by JS based on the learner filter.
// The initial inline style reflects the server-side state on page load.
echo html_writer::link(
    $pdfurl,
    get_string('exportpdf', 'report_individualized'),
    [
        'class' => 'btn btn-outline-dark ms-3 flex-shrink-0',
        'style' => $userid > 0 ? '' : 'display:none',
    ]
);

echo html_writer::end_div(); // Closes filters-inner.
echo html_writer::end_div(); // Closes report-individualized-filters.

// -------------------------------------------------------------------------
// 6. TABLES
// -------------------------------------------------------------------------

// The report-individualized-content div is the AJAX container.
// Its content is replaced by the PHP fragment on AJAX calls.
// On direct page load, index.php fills it directly.
echo html_writer::start_div('', ['id' => 'report-individualized-content']);

$userstoshow = [];
if ($userid > 0) {
    $userstoshow[] = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
} else if (!empty($allusers)) {
    $userstoshow = array_values($allusers);
}

// Column headers built with <br> for multi-line titles.
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

if (!empty($userstoshow)) {
    foreach ($userstoshow as $user) {
        echo $OUTPUT->heading(
            get_string('reportfor', 'report_individualized') . ' : ' . fullname($user),
            3
        );

        // Fetch the learner's courses including the category field for filtering.
        if ($courseid > 0) {
            $courses = [$courseid => get_course($courseid)];
        } else {
            $courses = enrol_get_users_courses($user->id, true, 'id, fullname, shortname, category', 'fullname ASC');
            // Apply category filter to the learner's courses.
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

        $resourcetypes = ['resource', 'url', 'page', 'folder', 'book', 'label', 'file'];

        foreach ($courses as $course) {
            $modinfo     = get_fast_modinfo($course, $user->id);
            $allsections = $modinfo->get_section_info_all();

            $cmsbysection = [];
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->uservisible) {
                    $cmsbysection[$cm->sectionnum][] = $cm;
                }
            }

            // Pre-collect all course CMs for the global course-level summary.
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

            // Capture section output into a buffer.
            // The course heading is only written if the buffer is non-empty.
            ob_start();

            foreach ($allsections as $section) {
                if (empty($cmsbysection[$section->section])) {
                    continue;
                }

                // Detect TIME feedback modules — excluded from the tables.
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

                // Apply date range filter.
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

                // Section 0 with no name is hidden.
                if ($section->section === 0 && empty($section->name)) {
                    continue;
                }

                // Empty section after filtering is hidden.
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

                echo html_writer::start_div(
                    'd-flex align-items-center gap-3 mb-2 report-individualized-section-header'
                );
                echo html_writer::tag('h5', get_string('section') . ' : ' . $sectionname, ['class' => 'mb-0']);
                echo html_writer::end_div();
                echo summary_util::render_pills($sectionsummary);

                // Resources table.
                if (!empty($resources)) {
                    echo html_writer::start_div('report-individualized-table-wrap');
                    $tablepdfurl = new moodle_url('/report/individualized/export_pdf.php', [
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

                    // Unique table ID includes section number to avoid clashes
                    // when a course has multiple sections on the same page.
                    $rtable = new \flexible_table(
                        'rpt-ind-res-' . $course->id . '-' . $user->id . '-s' . $section->section
                    );
                    $rtable->define_columns($rcolumns);
                    $rtable->define_headers($rheaders);
                    $rtable->define_baseurl($PAGE->url);
                    $rtable->set_attribute('class', 'generaltable local-individualized-table w-100');
                    $rtable->setup();

                    foreach ($resources as $cm) {
                        $viewstats = view_stats_util::get_view_stats($cm, $user->id, $datefrom, $dateto);
                        $row = [];
                        if ($rescols['resourcename']) {
                            $row[] = view_stats_util::get_activity_label($cm);
                        }
                        if ($rescols['availablefrom']) {
                            $row[] = date_util::get_module_availablefrom($cm);
                        }
                        if ($rescols['viewed']) {
                            $row[] = $viewstats['count'] > 0 ? get_string('yes') : get_string('no');
                        }
                        if ($rescols['viewrange']) {
                            $row[] = view_stats_util::format_view_range($viewstats);
                        }
                        if ($rescols['viewcount']) {
                            $row[] = $viewstats['count'] > 0 ? $viewstats['count'] : '-';
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
                    $tablepdfurl = new moodle_url('/report/individualized/export_pdf.php', [
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
                    $atable->define_baseurl($PAGE->url);
                    $atable->set_attribute('class', 'generaltable local-individualized-table w-100');
                    $atable->setup();

                    foreach ($activities as $cm) {
                        // Opening trace.
                        $openparams = [
                            'userid'   => $user->id,
                            'cmid'     => $cm->id,
                            'action'   => 'viewed',
                            'ctxlevel' => CONTEXT_MODULE,
                        ];
                        $openwhere = 'userid = :userid AND contextinstanceid = :cmid
                                      AND contextlevel = :ctxlevel AND action = :action';
                        if ($datefrom > 0) {
                            $openwhere             .= ' AND timecreated >= :datefrom';
                            $openparams['datefrom'] = $datefrom;
                        }
                        if ($dateto > 0) {
                            $openwhere           .= ' AND timecreated <= :dateto';
                            $openparams['dateto'] = $dateto;
                        }
                        $firstview = $DB->get_record_select(
                            'logstore_standard_log',
                            $openwhere,
                            $openparams,
                            'id, timecreated',
                            IGNORE_MULTIPLE
                        );
                        $opendate = !empty($firstview)
                            ? date_util::format_datetime($firstview->timecreated)
                            : '-';

                        $availablefrom  = date_util::get_module_availablefrom($cm);
                        $completionicon = completion_util::get_completion_icon($cm, $user->id);
                        $viewstats      = view_stats_util::get_view_stats(
                            $cm,
                            $user->id,
                            $datefrom,
                            $dateto
                        );

                        // Special case: workshop renders two rows.
                        if ($cm->modname === 'workshop') {
                            $workshopitems = workshop_util::get_workshop_items($cm, $user->id, $course->id);
                            foreach ($workshopitems as $item) {
                                $isassessment = (bool)preg_match(
                                    '/\(.*\b(?:assessment|évaluation|evaluation)\b.*\)/i',
                                    $item['label']
                                );
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
                                    $row[] = view_stats_util::format_view_range($viewstats);
                                }
                                if ($actcols['viewcount']) {
                                    $row[] = $viewstats['count'] > 0 ? $viewstats['count'] : '-';
                                }
                                if ($actcols['estimatedduration']) {
                                    $row[] = duration_util::get_estimated_duration($cm, $isassessment);
                                }
                                $atable->add_data($row);
                            }
                            continue;
                        }

                        // Standard case.
                        if ($cm->modname === 'h5pactivity') {
                            $h5pclose = $DB->get_record_select(
                                'logstore_standard_log',
                                'userid = :userid AND contextinstanceid = :cmid'
                                . ' AND component = :component AND action = :action',
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
                                $closewhere             .= ' AND timecreated >= :datefrom';
                                $closeparams['datefrom'] = $datefrom;
                            }
                            if ($dateto > 0) {
                                $closewhere           .= ' AND timecreated <= :dateto';
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
                                ? date_util::format_datetime($submission->timecreated)
                                : '-';
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
                            $row[] = view_stats_util::format_view_range($viewstats);
                        }
                        if ($actcols['viewcount']) {
                            $row[] = $viewstats['count'] > 0 ? $viewstats['count'] : '-';
                        }
                        if ($actcols['estimatedduration']) {
                            $row[] = duration_util::get_estimated_duration($cm);
                        }
                        $atable->add_data($row);
                    }
                    $atable->finish_output();
                    echo html_writer::end_div();
                }
            } // End foreach sections.

            $courseoutput = ob_get_clean();
            if (!empty(trim($courseoutput))) {
                echo html_writer::start_div('report-individualized-course-block');
                echo $OUTPUT->heading(get_string('course', 'moodle') . ' : ' . format_string($course->fullname), 4);

                // Category path displayed below the course heading.
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
        } // End foreach courses.
    }
}
echo html_writer::end_div(); // Closes report-individualized-content.
echo $OUTPUT->footer();