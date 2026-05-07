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
 * Capability definitions for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Main capability: view a learner report.
    'report/individualized:view' => [
        'captype'      => 'read',
        // Context level where this capability applies.
        // CONTEXT_SYSTEM = global site administration.
        'contextlevel' => CONTEXT_SYSTEM,
        // Default role archetypes for this capability.
        'archetypes'   => [
            'manager'        => CAP_ALLOW, // Manager: yes.
            'coursecreator'  => CAP_ALLOW, // Course creator: yes.
            'editingteacher' => CAP_ALLOW, // Editing teacher: yes.
            'teacher'        => CAP_ALLOW, // Non-editing teacher: yes.
            'student'        => CAP_PREVENT, // Student: no (cannot view other learners).
            'guest'          => CAP_PREVENT, // Guest: no.
        ],
        // If the capability does not yet exist on a custom role, permissions are
        // inherited from this core capability to avoid manual reconfiguration.
        'clonepermissionsfrom' => 'moodle/site:viewreports',
    ],
];
