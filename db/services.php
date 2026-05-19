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
 * External functions declaration for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2026 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Functions declared here are callable via AJAX (ajax: true).
// They are accessible to any authenticated user who has the required capability.
$functions = [

    // Returns the lists of students and courses for the filter selectors.
    // Called when the user changes the student or course selector.
    'report_individualized_get_filter_options' => [
        'classname'    => 'report_individualized\external\get_filter_options',
        'description'  => 'Returns updated student and course lists for the report filters.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'report/individualized:view',
        'loginrequired' => true,
    ],
];
