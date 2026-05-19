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
 * Unit tests for feedback_util.
 *
 * @package   report_individualized
 * @copyright 2026 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Test case for feedback_util.
 *
 * @package   report_individualized
 * @copyright 2026 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_individualized\util\feedback_util
 */
final class feedback_util_test extends \advanced_testcase {
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

    // H5P tests.

    /**
     * Tests that h5pactivity always returns '-' (no teacher feedback supported).
     */
    public function test_h5pactivity_always_returns_dash(): void {
        $h5p = $this->getDataGenerator()->create_module('h5pactivity', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($h5p->cmid);
        $this->assertEquals('-', feedback_util::get_activity_feedback($cm, $this->student->id));
    }

    // Assign feedback tests.

    /**
     * Tests that an assignment with no grade record returns '-'.
     */
    public function test_assign_no_grade_returns_dash(): void {
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($assign->cmid);
        $this->assertEquals('-', feedback_util::get_assign_feedback($cm, $this->student->id));
    }

    /**
     * Tests that an assignment with a grade but no feedback comment returns '-'.
     */
    public function test_assign_grade_without_feedback_returns_dash(): void {
        global $DB;

        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($assign->cmid);

        $DB->insert_record('assign_grades', [
            'assignment'    => $assign->id,
            'userid'        => $this->student->id,
            'timecreated'   => time(),
            'timemodified'  => time(),
            'grader'        => 2,
            'grade'         => 15.0,
            'attemptnumber' => 0,
        ]);

        $this->assertEquals('-', feedback_util::get_assign_feedback($cm, $this->student->id));
    }

    /**
     * Tests that an assignment with a grade and a feedback comment returns the comment.
     */
    public function test_assign_with_feedback_returns_text(): void {
        global $DB;

        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($assign->cmid);

        $gradeid = $DB->insert_record('assign_grades', [
            'assignment'    => $assign->id,
            'userid'        => $this->student->id,
            'timecreated'   => time(),
            'timemodified'  => time(),
            'grader'        => 2,
            'grade'         => 15.0,
            'attemptnumber' => 0,
        ]);

        $DB->insert_record('assignfeedback_comments', [
            'assignment'    => $assign->id,
            'grade'         => $gradeid,
            'commenttext'   => 'Well done.',
            'commentformat' => FORMAT_PLAIN,
        ]);

        $result = feedback_util::get_assign_feedback($cm, $this->student->id);
        $this->assertStringContainsString('Well done.', $result);
    }

    // Quiz feedback tests.

    /**
     * Tests that a quiz with no grade record returns '-'.
     */
    public function test_quiz_no_grade_returns_dash(): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($quiz->cmid);
        $this->assertEquals('-', feedback_util::get_quiz_feedback($cm, $this->student->id));
    }

    // Gradebook fallback tests.

    /**
     * Tests that a module with no grade item in the gradebook returns '-'.
     */
    public function test_gradebook_no_grade_item_returns_dash(): void {
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($page->cmid);
        $this->assertEquals('-', feedback_util::get_gradebook_feedback($cm, $this->student->id));
    }
}
