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
 * Unit tests for completion_util::is_complete().
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Test case for completion_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_individualized\util\completion_util
 */
final class completion_util_test extends \advanced_testcase {
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
        $generator      = $this->getDataGenerator();
        $this->course   = $generator->create_course();
        $this->student  = $generator->create_user();
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $this->setUser($this->student);
    }

    /**
     * Returns the cm_info object for a given course-module ID.
     *
     * @param  int      $cmid   Course-module ID.
     * @return \cm_info         Corresponding cm_info object.
     */
    private function get_cm(int $cmid): \cm_info {
        $modinfo = get_fast_modinfo($this->course, $this->student->id);
        return $modinfo->get_cm($cmid);
    }

    // Assign tests.

    /**
     * Tests that an unsubmitted assignment returns false.
     */
    public function test_assign_not_submitted_returns_false(): void {
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($assign->cmid);
        $this->assertFalse(completion_util::is_complete($cm, $this->student->id));
    }

    /**
     * Tests that a submitted assignment returns true.
     */
    public function test_assign_submitted_returns_true(): void {
        global $DB;
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $DB->insert_record('assign_submission', [
            'assignment'    => $assign->id,
            'userid'        => $this->student->id,
            'status'        => 'submitted',
            'timecreated'   => time(),
            'timemodified'  => time(),
            'attemptnumber' => 0,
            'latest'        => 1,
        ]);
        $cm = $this->get_cm($assign->cmid);
        $this->assertTrue(completion_util::is_complete($cm, $this->student->id));
    }

    /**
     * Tests that a draft assignment returns false.
     */
    public function test_assign_draft_returns_false(): void {
        global $DB;
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $DB->insert_record('assign_submission', [
            'assignment'    => $assign->id,
            'userid'        => $this->student->id,
            'status'        => 'draft',
            'timecreated'   => time(),
            'timemodified'  => time(),
            'attemptnumber' => 0,
            'latest'        => 1,
        ]);
        $cm = $this->get_cm($assign->cmid);
        $this->assertFalse(completion_util::is_complete($cm, $this->student->id));
    }

    // Quiz tests.

    /**
     * Tests that a quiz with no attempt returns false.
     */
    public function test_quiz_no_attempt_returns_false(): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($quiz->cmid);
        $this->assertFalse(completion_util::is_complete($cm, $this->student->id));
    }

    /**
     * Tests that a quiz with a finished attempt returns true.
     */
    public function test_quiz_finished_attempt_returns_true(): void {
        global $DB;
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id,
        ]);
        $DB->insert_record('quiz_attempts', [
            'quiz'          => $quiz->id,
            'userid'        => $this->student->id,
            'state'         => 'finished',
            'timestart'     => time(),
            'timefinish'    => time(),
            'timemodified'  => time(),
            'attempt'       => 1,
            'sumgrades'     => 0,
            'uniqueid'      => 1,
            'layout'        => '',
            'currentpage'   => 0,
        ]);
        $cm = $this->get_cm($quiz->cmid);
        $this->assertTrue(completion_util::is_complete($cm, $this->student->id));
    }

    /**
     * Tests that a quiz with an in-progress attempt returns false.
     */
    public function test_quiz_inprogress_attempt_returns_false(): void {
        global $DB;
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id,
        ]);
        $DB->insert_record('quiz_attempts', [
            'quiz'          => $quiz->id,
            'userid'        => $this->student->id,
            'state'         => 'inprogress',
            'timestart'     => time(),
            'timefinish'    => 0,
            'timemodified'  => time(),
            'attempt'       => 1,
            'sumgrades'     => 0,
            'uniqueid'      => 2,
            'layout'        => '',
            'currentpage'   => 0,
        ]);
        $cm = $this->get_cm($quiz->cmid);
        $this->assertFalse(completion_util::is_complete($cm, $this->student->id));
    }

    // Workshop tests.

    /**
     * Tests that a workshop with no submission returns false.
     */
    public function test_workshop_no_submission_returns_false(): void {
        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $this->course->id,
        ]);
        $cm = $this->get_cm($workshop->cmid);
        $this->assertFalse(completion_util::is_complete($cm, $this->student->id));
    }

    /**
     * Tests that a workshop with a submission returns true.
     */
    public function test_workshop_with_submission_returns_true(): void {
        global $DB;
        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $this->course->id,
        ]);
        $DB->insert_record('workshop_submissions', [
            'workshopid'    => $workshop->id,
            'authorid'      => $this->student->id,
            'timecreated'   => time(),
            'timemodified'  => time(),
            'title'         => 'Test submission',
            'content'       => '',
            'contentformat' => 0,
            'contenttrust'  => 0,
            'example'       => 0,
            'late'          => 0,
            'published'     => 0,
            'gradeoverby'   => 0,
        ]);
        $cm = $this->get_cm($workshop->cmid);
        $this->assertTrue(completion_util::is_complete($cm, $this->student->id));
    }
}
