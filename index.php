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
 * Renders the page shell (header, sticky filter bar, empty content container,
 * footer). All table rendering is delegated to report_fragment.php via the
 * AMD filters module (core/fragment AJAX). This keeps rendering logic in a
 * single place and enables server-side pagination on every load path.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use report_individualized\util\category_util;

// 1. URL parameters.

$userid     = optional_param('userid', 0, PARAM_INT);
$courseid   = optional_param('courseid', 0, PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);

// Date filters received as YYYY-MM-DD strings from the date input.
// PARAM_ALPHANUMEXT allows digits and hyphens.
// Conversion to Unix timestamps is done via make_timestamp() which respects
// the Moodle user timezone.
$datefromstr = optional_param('datefrom', '', PARAM_ALPHANUMEXT);
$datetostr   = optional_param('dateto', '', PARAM_ALPHANUMEXT);

// 2. Context and permissions.

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

// 3. Filter data.

$studentrole = $DB->get_record('role', ['shortname' => 'student']);

$allusers = [];
if ($studentrole) {
    if ($courseid > 0) {
        $coursecontext = context_course::instance($courseid);
        $allusers      = get_role_users(
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

// Course list for the selector; the category field is required for filtering.
$allcourses = [];
if ($userid > 0) {
    $allcourses = enrol_get_users_courses($userid, true, 'id, fullname, shortname, category', 'fullname ASC');
} else {
    $allcourses = $DB->get_records_select('course', 'id <> 1', [], 'fullname ASC', 'id, fullname, shortname, category');
}

// Apply category filter to the course selector list.
if ($categoryid !== 0) {
    $allcourses = array_column(
        category_util::filter_courses_by_category($categoryid, array_values($allcourses)),
        null,
        'id'
    );
}

// Category options for the selector, built from courses with enrolled learners.
$categoryoptions = category_util::get_category_options($userid > 0 ? $userid : 0);

// 4. HTML output.

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'report_individualized'));

// Initialise the AMD filters.js module with the system context ID.
// The module intercepts filter changes and refreshes the report via core/fragment.
// It also triggers an initial load on page start when a filter is already set.
$PAGE->requires->js_call_amd('report_individualized/filters', 'init', [$context->id]);

$pdfurl = new moodle_url('/report/individualized/export_pdf.php', [
    'userid'     => $userid,
    'courseid'   => $courseid,
    'categoryid' => $categoryid,
    'datefrom'   => $datefromstr,
    'dateto'     => $datetostr,
]);

echo html_writer::start_div('report-individualized-filters');
echo html_writer::start_div('report-individualized-filters-inner d-flex justify-content-between align-items-center');

// Filter form.
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/report/individualized/index.php'),
    'class'  => 'd-flex align-items-center gap-3 flex-grow-1 flex-wrap',
]);

// Category filter.
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

// Learner filter.
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

// PDF button — always in the DOM, shown or hidden by JS depending on the learner filter.
// The initial inline style reflects the URL parameter state on page load.
echo html_writer::link(
    $pdfurl,
    get_string('exportpdf', 'report_individualized'),
    [
        'class' => 'btn btn-outline-dark ms-3 flex-shrink-0',
        'style' => $userid > 0 ? '' : 'display:none',
    ]
);

echo html_writer::end_div();
echo html_writer::end_div();

// 5. Report container.

// This div is the AJAX container. Its content is managed entirely by filters.js:
// an initial load is triggered on page start when a filter is already set,
// then reloaded on every filter change. All rendering logic lives in
// classes/output/report_fragment.php.
$nofilter = $userid === 0 && $courseid === 0 && $categoryid === 0
    && empty($datefromstr) && empty($datetostr);

echo html_writer::start_div('', ['id' => 'report-individualized-content']);
if ($nofilter) {
    // Placeholder shown when no filter is active — no AJAX load is triggered.
    echo html_writer::tag(
        'p',
        get_string('selectlearner', 'report_individualized'),
        ['class' => 'text-muted mt-4 text-center report-individualized-placeholder']
    );
}
echo html_writer::end_div();

echo $OUTPUT->footer();