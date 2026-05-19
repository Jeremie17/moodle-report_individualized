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
 * Moodle hooks and fragment callbacks for report_individualized.
 *
 * This file contains only entrypoints required by Moodle core conventions:
 *  - Navigation hooks (profile + course)
 *  - Fragment callback shell (delegates to report_fragment class)
 *
 * All rendering logic lives in classes/output/report_fragment.php.
 * All business logic lives in classes/util/.
 *
 * @package   report_individualized
 * @copyright 2026 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Navigation hooks.

/**
 * Adds a link in the user profile navigation.
 *
 * @param  core_user\output\myprofile\tree $tree          Profile navigation tree.
 * @param  stdClass                        $user          Viewed user.
 * @param  bool                            $iscurrentuser True if viewing own profile.
 * @param  stdClass|null                   $course        Course context or null.
 * @return void
 */
function report_individualized_myprofile_navigation(
    core_user\output\myprofile\tree $tree,
    stdClass $user,
    bool $iscurrentuser,
    ?stdClass $course
): void {
    if (!has_capability('report/individualized:view', context_system::instance())) {
        return;
    }
    if ($iscurrentuser) {
        return;
    }
    $url  = new moodle_url('/report/individualized/index.php', ['userid' => $user->id]);
    $node = new core_user\output\myprofile\node(
        'reports',
        'report_individualized',
        get_string('pluginname', 'report_individualized'),
        null,
        $url,
        null
    );
    $tree->add_node($node);
}

/**
 * Adds a link in the course navigation.
 *
 * @param  navigation_node $navigation Course navigation node.
 * @param  stdClass        $course     Current course.
 * @param  context         $context    Course context.
 * @return void
 */
function report_individualized_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context $context
): void {
    if (!has_capability('report/individualized:view', $context)) {
        return;
    }
    $url = new moodle_url('/report/individualized/index.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('pluginname', 'report_individualized'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        null,
        new pix_icon('i/report', '')
    );
}

// AJAX fragment callback.

/**
 * Fragment callback: renders the report content via AJAX.
 *
 * Moodle requires fragment callbacks to be defined in lib.php and to follow
 * the pluginname_output_fragment_fragmentname() convention.
 * This shell immediately delegates to report_fragment::render() to keep
 * lib.php lightweight and logic in classes/.
 *
 * @param  array $args Parameters passed by core/fragment (JS).
 * @return string      Report HTML.
 */
function report_individualized_output_fragment_report(array $args): string {
    return \report_individualized\output\report_fragment::render($args);
}
