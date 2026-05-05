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
 * Strings for component 'report_individualized', language 'en'.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname']          = 'Individualized student report';
$string['individualized:view'] = 'View individualized student report';
$string['allstudentsreport'] = 'All students report';

// Filters.
$string['selectuser']   = 'Select a student';
$string['selectcourse'] = 'Select a course';
$string['allusers']     = 'All students';
$string['allcourses']   = 'All courses';

// Empty states.
$string['noenrolments'] = 'This student is not enrolled in any course.';
$string['noresources']  = 'No resources in this course.';
$string['noactivities'] = 'No activities in this course.';

// Sections.
$string['resources']  = 'Resources';
$string['activities'] = 'Activities';

// -------------------------------------------------------------------------
// Columns — two groups of strings per column:
//  - The "simple" string (e.g. 'resourcename') is used by settings.php
//    as the checkbox label in admin settings.
//  - The "split" strings (e.g. 'resourcename_type', 'resourcename_modality')
//    are used by index.php to build multi-line column headers.
// -------------------------------------------------------------------------

// Type/modality column.
$string['resourcename']          = 'Type and pedagogical modality';
$string['resourcename_type']     = 'Type';
$string['resourcename_modality'] = 'Pedagogical modality';
$string['activityname']          = 'Type and pedagogical modality';
$string['activityname_type']     = 'Type';
$string['activityname_modality'] = 'Pedagogical modality';
$string['columnheader_connector'] = ' and';

// Opening date column.
$string['availablefrom'] = 'Opening date';

// Viewed column.
$string['viewed'] = 'Viewed';

// Consultation range column.
$string['viewrange'] = 'Consultation range';

// View count column — split for multi-line header.
$string['viewcount']       = 'Total views';
$string['viewcount_line1'] = 'Total';
$string['viewcount_line2'] = 'views';

// Estimated duration column — split for multi-line header.
$string['estimatedduration']       = 'Estimated duration';
$string['estimatedduration_line1'] = 'Estimated';
$string['estimatedduration_line2'] = 'duration';

// Activity-only columns.
$string['duedate']    = 'Closing date';
$string['grade']      = 'Grade';
$string['feedback']   = 'Feedback';
$string['completion'] = 'Completion';
$string['opendate']   = 'Opening trace';
$string['closedate']  = 'Closing trace';

// Summary metrics (section + course summary bar).
$string['summary_completionrate']  = 'Completion';
$string['summary_avggrade']        = 'Average grade';
$string['summary_resourcesviewed'] = 'Resources viewed';

// Section duration badges.
$string['profestimated']    = 'Teacher estimate';
$string['studentestimated'] = 'Student estimate';

// PDF export.
$string['exportpdf']   = 'Export as PDF';
$string['reportfor']   = 'Report for';
$string['generatedon'] = 'Generated on';

// GDPR.
$string['privacy:metadata'] = 'The Individualized student report plugin does not store any personal data. It only displays existing Moodle logs and grade data.';

// Settings.
$string['unnamedsection'] = 'Untitled section';
$string['columnsettings'] = 'Individualized report display settings';

// Advanced settings.
$string['advancedsettings']             = 'Advanced settings';
$string['workshop_feedback_type']       = 'Workshop — rows to display in report';
$string['workshop_feedback_type_desc']  = 'Choose which rows to display for Workshop activities. Both are shown by default.';
$string['workshop_feedback_both']       = 'Both (submission + peer assessment)';
$string['workshop_feedback_submission'] = 'Submission only';
$string['workshop_feedback_assessment'] = 'Assessment only';
$string['categorydepth']      = 'Category path depth';
$string['categorydepth_desc'] = 'Maximum number of levels to display in the category path. Set to 0 to show all levels.';

// Date filters.
$string['datefrom']    = 'From';
$string['dateto']      = 'To';
$string['applyfilter'] = 'Apply';
$string['resetfilter'] = 'Reset';

// Category filter.
$string['selectcategory']   = 'Select a category';
$string['allcategories']    = 'All categories';
