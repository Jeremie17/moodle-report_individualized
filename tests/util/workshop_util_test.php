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
 * Unit tests for workshop_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Test case for workshop_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_individualized\util\workshop_util
 */
final class workshop_util_test extends \advanced_testcase {
    /** @var \stdClass Test course. */
    private \stdClass $course;

    /** @var \stdClass Test learner. */
    private \stdClass $student;

    /**
     * Creates a course and a learner reused across all tests.
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

    // Get_workshop_items tests.

    /**
     * Tests that a workshop returns two items by default (both submission and assessment).
     */
    public function test_get_workshop_items_returns_two_items_by_default(): void {
        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $this->course->id,
        ]);
        $cm    = $this->get_cm($workshop->cmid);
        $items = workshop_util::get_workshop_items($cm, $this->student->id, $this->course->id);

        $this->assertCount(2, $items);
    }

    /**
     * Tests that submission feedback_type setting returns only the submission row.
     */
    public function test_get_workshop_items_submission_type_returns_one_item(): void {
        set_config('workshop_feedback_type', 'submission', 'report_individualized');

        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $this->course->id,
        ]);
        $cm    = $this->get_cm($workshop->cmid);
        $items = workshop_util::get_workshop_items($cm, $this->student->id, $this->course->id);

        $this->assertCount(1, $items);
        $this->assertFalse($items[0]['isassessment']);
    }

    /**
     * Tests that assessment feedback_type setting returns only the assessment row.
     */
    public function test_get_workshop_items_assessment_type_returns_one_item(): void {
        set_config('workshop_feedback_type', 'assessment', 'report_individualized');

        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $this->course->id,
        ]);
        $cm    = $this->get_cm($workshop->cmid);
        $items = workshop_util::get_workshop_items($cm, $this->student->id, $this->course->id);

        $this->assertCount(1, $items);
        $this->assertTrue($items[0]['isassessment']);
    }

    /**
     * Tests that each item contains the expected keys.
     */
    public function test_get_workshop_items_has_expected_keys(): void {
        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $this->course->id,
        ]);
        $cm    = $this->get_cm($workshop->cmid);
        $items = workshop_util::get_workshop_items($cm, $this->student->id, $this->course->id);

        foreach ($items as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('gradestr', $item);
            $this->assertArrayHasKey('feedbackstr', $item);
            $this->assertArrayHasKey('closedatestr', $item);
            $this->assertArrayHasKey('duedatestr', $item);
            $this->assertArrayHasKey('completionicon', $item);
            $this->assertArrayHasKey('isassessment', $item);
        }
    }

    // Get_submission_feedback tests.

    /**
     * Tests that a workshop with no submission returns '-'.
     */
    public function test_get_submission_feedback_no_submission_returns_dash(): void {
        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($workshop->cmid);
        $this->assertEquals('-', workshop_util::get_submission_feedback($cm, $this->student->id));
    }

    // Get_assessment_feedback tests.

    /**
     * Tests that a workshop with no assessments returns '-'.
     */
    public function test_get_assessment_feedback_no_assessments_returns_dash(): void {
        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($workshop->cmid);
        $this->assertEquals('-', workshop_util::get_assessment_feedback($cm, $this->student->id));
    }
}
