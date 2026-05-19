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
 * External function: get_filter_options for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2026 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use report_individualized\util\category_util;

/**
 * Returns the updated lists of students, courses and categories for the filter selectors.
 *
 * Logic:
 *  - categoryid != 0 → courses are restricted to that category / its descendants.
 *  - courseid   >  0 → only students enrolled in that course.
 *  - userid     >  0 → only courses that student is enrolled in (intersected with category filter).
 *  - Otherwise      → all students / all courses on the site.
 *  - Categories list is always the full set (built from all enrolled-student courses).
 */
class get_filter_options extends external_api {
    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'     => new external_value(PARAM_INT, 'Currently selected user ID (0 = all)', VALUE_DEFAULT, 0),
            'courseid'   => new external_value(PARAM_INT, 'Currently selected course ID (0 = all)', VALUE_DEFAULT, 0),
            'categoryid' => new external_value(PARAM_INT, 'Currently selected category ID (0 = all)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns updated student, course and category lists.
     *
     * @param  int   $userid     Currently selected user ID.
     * @param  int   $courseid   Currently selected course ID.
     * @param  int   $categoryid Currently selected category ID.
     * @return array             ['users' => [...], 'courses' => [...], 'categories' => [...]]
     */
    public static function execute(int $userid, int $courseid, int $categoryid): array {
        global $DB, $CFG;

        ['userid' => $userid, 'courseid' => $courseid, 'categoryid' => $categoryid] =
            self::validate_parameters(
                self::execute_parameters(),
                ['userid' => $userid, 'courseid' => $courseid, 'categoryid' => $categoryid]
            );

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('report/individualized:view', $context);

        // Student list.
        $users = [['id' => 0, 'name' => get_string('allusers', 'report_individualized')]];

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        if ($studentrole) {
            if ($courseid > 0) {
                // Conditional filter: only students from this course.
                $coursecontext = \context_course::instance($courseid);
                $roleusers = get_role_users(
                    $studentrole->id,
                    $coursecontext,
                    false,
                    'u.id, u.firstname, u.lastname',
                    'u.lastname ASC, u.firstname ASC'
                );
                foreach ($roleusers as $u) {
                    $users[] = ['id' => (int)$u->id, 'name' => fullname($u)];
                }
            } else {
                $allusers = $DB->get_records_sql(
                    "SELECT DISTINCT u.id, u.firstname, u.lastname
                       FROM {user} u
                       JOIN {role_assignments} ra ON ra.userid = u.id
                       JOIN {role} r ON r.id = ra.roleid
                      WHERE r.shortname = 'student'
                        AND u.deleted = 0
                        AND u.suspended = 0
                   ORDER BY u.lastname ASC, u.firstname ASC"
                );
                foreach ($allusers as $u) {
                    $users[] = ['id' => (int)$u->id, 'name' => fullname($u)];
                }
            }
        }

        // Course list (filtered by category and/or student).
        $courses = [['id' => 0, 'name' => get_string('allcourses', 'report_individualized')]];

        if ($userid > 0) {
            // Conditional filter: only this student's courses.
            $usercourses = enrol_get_users_courses($userid, true, 'id, fullname, category', 'fullname ASC');
            // Applies the category filter if active.
            if ($categoryid !== 0) {
                $usercourses = array_column(
                    category_util::filter_courses_by_category($categoryid, array_values($usercourses)),
                    null,
                    'id'
                );
            }
            foreach ($usercourses as $c) {
                $courses[] = ['id' => (int)$c->id, 'name' => format_string($c->fullname)];
            }
        } else {
            $allcourses = $DB->get_records_select('course', 'id <> 1', [], 'fullname ASC', 'id, fullname, category');
            // Applies the category filter if active.
            if ($categoryid !== 0) {
                $allcourses = array_column(
                    category_util::filter_courses_by_category($categoryid, array_values($allcourses)),
                    null,
                    'id'
                );
            }
            foreach ($allcourses as $c) {
                $courses[] = ['id' => (int)$c->id, 'name' => format_string($c->fullname)];
            }
        }

        // Category list (always the full set, independent of other filters).
        $categories = [['id' => 0, 'name' => get_string('allcategories', 'report_individualized')]];
        $catopts    = category_util::get_category_options(0);
        foreach ($catopts as $opt) {
            $categories[] = ['id' => (int)$opt['id'], 'name' => $opt['path']];
        }

        return [
            'users'      => $users,
            'courses'    => $courses,
            'categories' => $categories,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        // Shared structure for option lists.
        $optionlist = new external_multiple_structure(
            new external_single_structure([
                'id'   => new external_value(PARAM_INT, 'Option value (ID)'),
                'name' => new external_value(PARAM_TEXT, 'Option label'),
            ])
        );
        return new external_single_structure([
            'users'      => $optionlist,
            'courses'    => $optionlist,
            'categories' => $optionlist,
        ]);
    }
}
