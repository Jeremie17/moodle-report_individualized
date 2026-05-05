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
 * Category utility functions for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Helpers for course category filtering and path display.
 */
class category_util {
    /**
     * Returns the full category path string for a given category ID.
     *
     * Walks up the parent tree using the Moodle core_course_category API.
     * Example output: "Formation / Informatique / Python"
     *
     * When $applydepthlimit is true, the admin setting 'categorydepth' is applied
     * to truncate the path. This is intended for display under the course title
     * only — filter options always receive the full path.
     *
     * @param  int    $catid           Course category ID.
     * @param  bool   $applydepthlimit Whether to apply the admin depth limit.
     * @return string                  Slash-separated path, or empty string if not found.
     */
    public static function get_category_path(int $catid, bool $applydepthlimit = false): string {
        if ($catid <= 0) {
            return '';
        }
        try {
            $cat = \core_course_category::get($catid, IGNORE_MISSING, true);
            if (!$cat) {
                return '';
            }
            $names = [];
            foreach ($cat->get_parents() as $parentid) {
                $parent = \core_course_category::get($parentid, IGNORE_MISSING, true);
                if ($parent) {
                    $names[] = $parent->get_formatted_name();
                }
            }
            $names[] = $cat->get_formatted_name();

            // Limite la profondeur uniquement si demandé explicitement
            // (affichage sous le titre du cours, pas dans les filtres).
            if ($applydepthlimit) {
                $maxdepth = (int)get_config('report_individualized', 'categorydepth');
                if ($maxdepth > 0 && count($names) > $maxdepth) {
                    // On conserve les derniers niveaux (les plus précis).
                    $names = array_slice($names, -$maxdepth);
                }
            }

            return implode(' / ', $names);
        } catch (\moodle_exception $e) {
            return '';
        }
    }

    /**
     * Builds the category options list for the filter select.
     *
     * Returns all categories that contain enrolled-student courses.
     * Always uses the full path regardless of the depth limit setting.
     *
     * @param  int   $userid If > 0, restrict to courses enrolled by this user.
     * @return array         Array of ['id' => int, 'path' => string], sorted by path.
     */
    public static function get_category_options(int $userid = 0): array {
        global $DB;

        if ($userid > 0) {
            $courses = enrol_get_users_courses($userid, true, 'id, category');
        } else {
            $courses = $DB->get_records_sql(
                "SELECT DISTINCT c.id, c.category
                   FROM {course} c
                   JOIN {enrol} e ON e.courseid = c.id
                   JOIN {user_enrolments} ue ON ue.enrolid = e.id
                   JOIN {user} u ON u.id = ue.userid
                   JOIN {role_assignments} ra ON ra.userid = u.id
                   JOIN {context} ctx ON ctx.id = ra.contextid
                                      AND ctx.contextlevel = :ctxlevel
                                      AND ctx.instanceid = c.id
                   JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                  WHERE c.id <> 1 AND u.deleted = 0 AND u.suspended = 0",
                ['ctxlevel' => CONTEXT_COURSE]
            );
        }

        $seen = [];

        foreach ($courses as $course) {
            $catid = (int)($course->category ?? 0);
            if ($catid <= 0) {
                continue;
            }
            try {
                $cat = \core_course_category::get($catid, IGNORE_MISSING, true);
                if (!$cat) {
                    continue;
                }
                if (!isset($seen[$catid])) {
                    // Pas de limite de profondeur dans les filtres.
                    $seen[$catid] = self::get_category_path($catid, false);
                }
            } catch (\moodle_exception $e) {
                continue;
            }
        }

        asort($seen);

        $result = [];
        foreach ($seen as $id => $path) {
            $result[] = ['id' => $id, 'path' => $path];
        }
        return $result;
    }

    /**
     * Filters an array of course objects by the selected category filter value.
     *
     * categoryid = 0  → no filter, all courses returned.
     * categoryid > 0  → only courses in that category or any of its descendants.
     *
     * @param  int   $categoryid Selected category filter value.
     * @param  array $courses    Array of course objects with a `category` property.
     * @return array             Filtered array (plain array, same objects).
     */
    public static function filter_courses_by_category(int $categoryid, array $courses): array {
        if ($categoryid === 0) {
            return $courses;
        }

        try {
            $target = \core_course_category::get($categoryid, IGNORE_MISSING, true);
        } catch (\moodle_exception $e) {
            return $courses;
        }

        if (!$target) {
            return $courses;
        }

        $filtered = [];
        foreach ($courses as $course) {
            $catid = (int)(isset($course->category) ? $course->category : 0);

            if ($catid <= 0) {
                continue;
            }

            try {
                $cat = \core_course_category::get($catid, IGNORE_MISSING, true);
            } catch (\moodle_exception $e) {
                continue;
            }

            if (!$cat) {
                continue;
            }

            if ($cat->id === $target->id
                || strpos($cat->path, $target->path . '/') === 0) {
                $filtered[] = $course;
            }
        }
        return $filtered;
    }
}
