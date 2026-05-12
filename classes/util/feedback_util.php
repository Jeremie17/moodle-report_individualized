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
 * Feedback utility for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

/**
 * Utility class for retrieving teacher feedback across module types.
 *
 * Workshop feedback is handled separately in workshop_util.
 */
class feedback_util
{
    /**
     * Dispatcher : returns teacher feedback for a non-workshop activity.
     *
     * Modules covered:
     *  - assign      → mdl_assignfeedback_comments
     *  - quiz        → mdl_quiz_feedback (automatic bearings)
     *  - h5pactivity → no teacher feedback
     *  - autres      → fallback gradebook
     *
     * @param  \cm_info $cm        Course module.
     * @param  int      $userid    Student ID.
     * @param  bool     $plaintext True = plain text without HTML (PDF export).
     * @return string              Formatted feedback or '-'.
     */
    public static function get_activity_feedback(
        \cm_info $cm,
        int $userid,
        bool $plaintext = false
    ): string {
        switch ($cm->modname) {
            case 'assign':
                $feedback = self::get_assign_feedback($cm, $userid);
                break;
            case 'quiz':
                $feedback = self::get_quiz_feedback($cm, $userid);
                break;
            case 'h5pactivity':
                return '-';
            default:
                $feedback = self::get_gradebook_feedback($cm, $userid);
                break;
        }

        return $plaintext ? strip_tags($feedback) : $feedback;
    }

    /**
     * Feedback on an assignment (assign) from mdl_assignfeedback_comments.
     *
     * Path :
     *  assign_grades (assignment + userid) → id
     *  → assignfeedback_comments (assignment + grade = id) → commenttext
     *
     * @param  \cm_info $cm     Assign module.
     * @param  int      $userid Student ID.
     * @return string           Correction comment or '-'.
     */
    public static function get_assign_feedback(\cm_info $cm, int $userid): string {
        global $DB;

        $assigngrade = $DB->get_record(
            'assign_grades',
            ['assignment' => $cm->instance, 'userid' => $userid],
            'id',
            IGNORE_MISSING
        );

        if (!$assigngrade) {
            return '-';
        }

        $comment = $DB->get_record(
            'assignfeedback_comments',
            ['assignment' => $cm->instance, 'grade' => $assigngrade->id],
            'commenttext, commentformat',
            IGNORE_MISSING
        );

        if (!$comment || empty($comment->commenttext)) {
            return '-';
        }

        return format_text($comment->commenttext, $comment->commentformat);
    }

    /**
     * Automatic feedback from a quiz based on levels configured by the teacher.
     *
     * Comparison : mingrade <= grade < maxgrade (same as quiz_feedback_for_grade() core).
     *
     * @param  \cm_info $cm     Quiz module.
     * @param  int      $userid Student ID.
     * @return string           Feedback text or '-'.
     */
    public static function get_quiz_feedback(\cm_info $cm, int $userid): string {
        global $DB;

        $quizgrade = $DB->get_record(
            'quiz_grades',
            ['quiz' => $cm->instance, 'userid' => $userid]
        );

        if (!$quizgrade || $quizgrade->grade === null) {
            return '-';
        }

        // The parameter named :grade can only appear once.
        // We use :grade2 for the second occurrence.
        $feedbackrow = $DB->get_record_select(
            'quiz_feedback',
            'quizid = :quizid AND mingrade <= :grade AND :grade2 < maxgrade',
            [
                'quizid' => $cm->instance,
                'grade'  => $quizgrade->grade,
                'grade2' => $quizgrade->grade,
            ]
        );

        if ($feedbackrow && !empty($feedbackrow->feedbacktext)) {
            return format_text($feedbackrow->feedbacktext, $feedbackrow->feedbacktextformat);
        }

        return '-';
    }

    /**
     * Fallback : feedback entered manually in the Moodle notebook.
     *
     * @param  \cm_info $cm     Course module.
     * @param  int      $userid Student ID.
     * @return string           Feedback text or '-'.
     */
    public static function get_gradebook_feedback(\cm_info $cm, int $userid): string {
        global $DB;

        $gradeitem = $DB->get_record('grade_items', [
            'itemtype'     => 'mod',
            'itemmodule'   => $cm->modname,
            'iteminstance' => $cm->instance,
            'courseid'     => $cm->course,
        ]);

        if (!$gradeitem) {
            return '-';
        }

        $grade = $DB->get_record('grade_grades', [
            'itemid' => $gradeitem->id,
            'userid' => $userid,
        ]);

        if ($grade && !empty($grade->feedback)) {
            return format_text($grade->feedback, $grade->feedbackformat);
        }

        return '-';
    }
}
