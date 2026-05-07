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
 * Unit tests for view_stats_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Test case for view_stats_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_individualized\util\view_stats_util
 */
final class view_stats_util_test extends \advanced_testcase {
    /** @var \stdClass Test course. */
    private \stdClass $course;

    /** @var \stdClass Test learner. */
    private \stdClass $student;

    /**
     * Creates a course and a learner reused across DB-dependent tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator     = $this->getDataGenerator();
        $this->course  = $generator->create_course();
        $this->student = $generator->create_user();
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $this->setUser($this->student);
    }

    /**
     * Returns the cm_info object for a given course-module ID.
     *
     * @param  int      $cmid Course-module ID.
     * @return \cm_info       Corresponding cm_info object.
     */
    private function get_cm(int $cmid): \cm_info {
        $modinfo = get_fast_modinfo($this->course, $this->student->id);
        return $modinfo->get_cm($cmid);
    }

    // Format_view_range tests.

    /**
     * Tests that zero views returns '-'.
     */
    public function test_format_view_range_no_views_returns_dash(): void {
        $stats = ['count' => 0, 'first' => 0, 'last' => 0];
        $this->assertEquals('-', view_stats_util::format_view_range($stats));
    }

    /**
     * Tests that a single view returns only the first date (no separator).
     */
    public function test_format_view_range_single_view_has_no_separator(): void {
        $ts    = mktime(10, 0, 0, 1, 15, 2026);
        $stats = ['count' => 1, 'first' => $ts, 'last' => $ts];
        $result = view_stats_util::format_view_range($stats);
        $this->assertStringNotContainsString('→', $result);
    }

    /**
     * Tests that multiple views in HTML mode contain the arrow separator.
     */
    public function test_format_view_range_multiple_views_html_has_arrow(): void {
        $ts1   = mktime(10, 0, 0, 1, 15, 2026);
        $ts2   = mktime(14, 0, 0, 2, 20, 2026);
        $stats = ['count' => 2, 'first' => $ts1, 'last' => $ts2];
        $result = view_stats_util::format_view_range($stats, false);
        $this->assertStringContainsString('→', $result);
    }

    /**
     * Tests that multiple views in plaintext mode use ' → ' as separator.
     */
    public function test_format_view_range_multiple_views_plaintext_separator(): void {
        $ts1   = mktime(10, 0, 0, 1, 15, 2026);
        $ts2   = mktime(14, 0, 0, 2, 20, 2026);
        $stats = ['count' => 2, 'first' => $ts1, 'last' => $ts2];
        $result = view_stats_util::format_view_range($stats, true);
        $this->assertStringContainsString(' → ', $result);
        $this->assertStringNotContainsString('<br>', $result);
    }

    // Get_modtype_label tests.

    /**
     * Tests that an unknown module name is returned as-is.
     */
    public function test_get_modtype_label_unknown_returns_modname(): void {
        $this->assertEquals('unknownmod', view_stats_util::get_modtype_label('unknownmod'));
    }

    /**
     * Tests that a known module returns a non-empty localized string.
     */
    public function test_get_modtype_label_known_module_returns_string(): void {
        $result = view_stats_util::get_modtype_label('assign');
        $this->assertNotEmpty($result);
        $this->assertNotEquals('assign', $result);
    }

    // Get_view_stats tests.

    /**
     * Tests that a module with no log entries returns zero count.
     */
    public function test_get_view_stats_no_logs_returns_zero(): void {
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $cm    = $this->get_cm($assign->cmid);
        $stats = view_stats_util::get_view_stats($cm, $this->student->id);

        $this->assertEquals(0, $stats['count']);
        $this->assertEquals(0, $stats['first']);
        $this->assertEquals(0, $stats['last']);
    }

    /**
     * Tests that logged views are counted correctly.
     */
    public function test_get_view_stats_with_logs_returns_correct_count(): void {
        global $DB;

        $assign  = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $cm      = $this->get_cm($assign->cmid);
        $context = \context_module::instance($cm->id);

        $ts1 = mktime(10, 0, 0, 1, 15, 2026);
        $ts2 = mktime(14, 0, 0, 1, 16, 2026);

        foreach ([$ts1, $ts2] as $ts) {
            $DB->insert_record('logstore_standard_log', [
                'eventname'         => '\mod_assign\event\course_module_viewed',
                'component'         => 'mod_assign',
                'action'            => 'viewed',
                'target'            => 'course_module',
                'userid'            => $this->student->id,
                'contextid'         => $context->id,
                'contextlevel'      => CONTEXT_MODULE,
                'contextinstanceid' => $cm->id,
                'courseid'          => $this->course->id,
                'timecreated'       => $ts,
                'anonymous'         => 0,
                'other'             => '',
                'relateduserid'     => 0,
                'realuserid'        => 0,
                'ip'                => '127.0.0.1',
                'origin'            => 'web',
            ]);
        }

        $stats = view_stats_util::get_view_stats($cm, $this->student->id);

        $this->assertEquals(2, $stats['count']);
        $this->assertEquals($ts1, $stats['first']);
        $this->assertEquals($ts2, $stats['last']);
    }
}
