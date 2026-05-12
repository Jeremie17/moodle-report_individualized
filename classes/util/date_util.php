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
 * Date utility functions for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

/**
 * Utility class for all date-related operations.
 */
class date_util {
    /**
     * Formats a timestamp as a date and time on two lines.
     *
     * Displayed format:
     *  - Line 1: "14 April 2026" (number + month + year, without day of the week)
     *  - Line 2: "14:30" (time with "h" instead of the separator :)
     *
     * The $plaintext mode is used for PDFs (no HTML tags).
     *
     * @param  int  $ts        Timestamp Unix.
     * @param  bool $plaintext True = Plain text without HTML (PDF export).
     * @return string          Formatted date or '-'.
     */
    public static function format_datetime(int $ts, bool $plaintext = false): string {
        if ($ts <= 0) {
            return '-';
        }
        // Format: %d = day number, %B = month name, %Y = full year.
        $datepart = userdate($ts, '%d %B %Y');
        // Hour (24h) and minutes format.
        $timepart = str_replace(':', 'h', userdate($ts, '%H:%M'));

        if ($plaintext) {
            return $datepart . ' ' . $timepart;
        }
        return $datepart . '<br>' . $timepart;
    }

    /**
     * Reads the availability dates resulting from Moodle access restrictions(JSON availability).
     *
     * Parse the JSON course_modules.availability to extract conditions of type "date".
     * Return ['from' => timestamp, 'to' => timestamp], each value is 0 if absent.
     *
     * Used by get_configured_opendate_timestamp() and get_configured_closedate_timestamp()
     * for modules without native availability settings (ex : h5pactivity).
     *
     * @param  \cm_info $cm Module de cours.
     * @return int[]        ['from' => int, 'to' => int].
     */
    private static function get_availability_dates(\cm_info $cm): array {
        $result = ['from' => 0, 'to' => 0];
        if (empty($cm->availability)) {
            return $result;
        }
        $avail = json_decode($cm->availability);
        if (!$avail || empty($avail->c)) {
            return $result;
        }
        foreach ($avail->c as $cond) {
            if (!isset($cond->type) || $cond->type !== 'date') {
                continue;
            }
            if ($cond->d === '>=' && !empty($cond->t) && (int)$cond->t > 0) {
                $result['from'] = (int)$cond->t;
            }
            if ($cond->d === '<' && !empty($cond->t) && (int)$cond->t > 0) {
                $result['to'] = (int)$cond->t;
            }
        }
        return $result;
    }

    /**
     * Return the timestamp of the opening date set by the teacher.
     *
     * View only the module settings (tables assign, quiz, workshop).
     * For h5pactivity, reads the access restrictions (JSON availability).
     * Return 0 if no date is configured.
     *
     * Used by :
     *  - report_individualized_get_module_availablefrom_timestamp()
     *
     * @param  \cm_info $cm Course module.
     * @return int          Timestamp Unix or 0.
     */
    public static function get_configured_opendate_timestamp(\cm_info $cm): int {
        global $DB;

        switch ($cm->modname) {
            case 'assign':
                $rec = $DB->get_record('assign', ['id' => $cm->instance], 'allowsubmissionsfromdate');
                if ($rec && (int)$rec->allowsubmissionsfromdate > 0) {
                    return (int)$rec->allowsubmissionsfromdate;
                }
                break;
            case 'quiz':
                $rec = $DB->get_record('quiz', ['id' => $cm->instance], 'timeopen');
                if ($rec && (int)$rec->timeopen > 0) {
                    return (int)$rec->timeopen;
                }
                break;
            case 'workshop':
                $rec = $DB->get_record('workshop', ['id' => $cm->instance], 'submissionstart');
                if ($rec && (int)$rec->submissionstart > 0) {
                    return (int)$rec->submissionstart;
                }
                break;
            case 'h5pactivity':
                $dates = self::get_availability_dates($cm);
                if ($dates['from'] > 0) {
                    return $dates['from'];
                }
                break;
        }

        return 0;
    }

    /**
     * Returns the timestamp of the closing date configured by the teacher.
     *
     * Only checks module settings (assign tables, quiz).
     * For h5pactivity, reads access restrictions (JSON availability).
     * The workshop is excluded: its dates are managed in workshop_util.
     * Returns 0 if no dates are configured.
     *
     * Used by :
     *  - get_module_duedate_timestamp()
     *
     * @param  \cm_info $cm Module de cours.
     * @return int          Timestamp Unix ou 0.
     */
    public static function get_configured_closedate_timestamp(\cm_info $cm): int {
        global $DB;

        switch ($cm->modname) {
            case 'assign':
                $rec = $DB->get_record('assign', ['id' => $cm->instance], 'duedate');
                if ($rec && (int)$rec->duedate > 0) {
                    return (int)$rec->duedate;
                }
                break;
            case 'quiz':
                $rec = $DB->get_record('quiz', ['id' => $cm->instance], 'timeclose');
                if ($rec && (int)$rec->timeclose > 0) {
                    return (int)$rec->timeclose;
                }
                break;
            case 'h5pactivity':
                $dates = self::get_availability_dates($cm);
                if ($dates['to'] > 0) {
                    return $dates['to'];
                }
                break;
        }

        return 0;
    }

    /**
     * Retourne le timestamp auquel la note finale a été attribuée à l'étudiant.
     *
     * Utilisée par :
     *  - get_module_duedate_timestamp()
     *
     * @param  \cm_info $cm     Course module.
     * @param  int      $userid Student ID.
     * @return int              Timestamp Unix or 0.
     */
    public static function get_grade_timemodified(\cm_info $cm, int $userid): int {
        global $DB;

        $gradeitem = $DB->get_record('grade_items', [
            'itemtype'     => 'mod',
            'itemmodule'   => $cm->modname,
            'iteminstance' => $cm->instance,
            'courseid'     => $cm->course,
        ]);

        if (!$gradeitem) {
            return 0;
        }

        $grade = $DB->get_record('grade_grades', [
            'itemid' => $gradeitem->id,
            'userid' => $userid,
        ], 'finalgrade, timemodified');

        if ($grade && $grade->finalgrade !== null && (int)$grade->timemodified > 0) {
            return (int)$grade->timemodified;
        }

        return 0;
    }

    /**
     * Returns the timestamp of the teacher feedback on an assignment.
     *
     * Used by :
     *  - get_module_duedate_timestamp()
     *
     * @param  \cm_info $cm     Course module.
     * @param  int      $userid Student ID.
     * @return int              Timestamp Unix or 0.
     */
    public static function get_assign_feedback_timestamp(\cm_info $cm, int $userid): int {
        global $DB;

        if ($cm->modname !== 'assign') {
            return 0;
        }

        $assigngrade = $DB->get_record(
            'assign_grades',
            ['assignment' => $cm->instance, 'userid' => $userid],
            'id, timemodified',
            IGNORE_MISSING
        );

        if (!$assigngrade) {
            return 0;
        }

        $comment = $DB->get_record(
            'assignfeedback_comments',
            ['assignment' => $cm->instance, 'grade' => $assigngrade->id],
            'commenttext',
            IGNORE_MISSING
        );

        if ($comment && !empty($comment->commenttext) && (int)$assigngrade->timemodified > 0) {
            return (int)$assigngrade->timemodified;
        }

        return 0;
    }

    /**
     * Returns the timestamp of the effective opening date of a module.
     *
     * Single source of truth for display AND filtering.
     *
     * @param  \cm_info $cm Course module.
     * @return int          Timestamp Unix or 0.
     */
    public static function get_module_availablefrom_timestamp(\cm_info $cm): int {
        $ts = self::get_configured_opendate_timestamp($cm);
        if ($ts > 0) {
            return $ts;
        }
        return (int)$cm->added;
    }

    /**
     * Returns the effective opening date of a module (formatted on two lines).
     *
     * @param  \cm_info $cm        Course module.
     * @param  bool     $plaintext True = plain text (PDF export).
     * @return string              Formatted date or '-'.
     */
    public static function get_module_availablefrom(\cm_info $cm, bool $plaintext = false): string {
        $ts = self::get_module_availablefrom_timestamp($cm);
        return self::format_datetime($ts, $plaintext);
    }

    /**
     * Returns the timestamp of the effective closing date.
     *
     * Fallback chain :
     *  1. Date set by the teacher
     *  2. Date the grade was assigned
     *  3. Date of teacher feedback (assign only)
     *  4. Date the student submitted
     *
     * @param  \cm_info $cm     Course module.
     * @param  int      $userid Student ID.
     * @return int              Timestamp Unix or 0.
     */
    public static function get_module_duedate_timestamp(\cm_info $cm, int $userid = 0): int {
        global $DB;

        $ts = self::get_configured_closedate_timestamp($cm);
        if ($ts > 0) {
            return $ts;
        }

        if ($userid <= 0) {
            return 0;
        }

        $ts = self::get_grade_timemodified($cm, $userid);
        if ($ts > 0) {
            return $ts;
        }

        $ts = self::get_assign_feedback_timestamp($cm, $userid);
        if ($ts > 0) {
            return $ts;
        }

        switch ($cm->modname) {
            case 'assign':
                $sub = $DB->get_record(
                    'assign_submission',
                    ['assignment' => $cm->instance, 'userid' => $userid, 'status' => 'submitted'],
                    'timemodified',
                    IGNORE_MISSING
                );
                if ($sub && (int)$sub->timemodified > 0) {
                    return (int)$sub->timemodified;
                }
                break;
            case 'quiz':
                $attempt = $DB->get_record_select(
                    'quiz_attempts',
                    'quiz = :quiz AND userid = :userid AND state = :state',
                    ['quiz' => $cm->instance, 'userid' => $userid, 'state' => 'finished'],
                    'timefinish',
                    IGNORE_MULTIPLE
                );
                if ($attempt && (int)$attempt->timefinish > 0) {
                    return (int)$attempt->timefinish;
                }
                break;
            case 'h5pactivity':
                $attempt = $DB->get_record_sql(
                    "SELECT timemodified
                       FROM {h5pactivity_attempts}
                      WHERE h5pactivityid = :id AND userid = :userid AND completion = 1
                   ORDER BY attempt DESC
                      LIMIT 1",
                    ['id' => $cm->instance, 'userid' => $userid]
                );
                if ($attempt && (int)$attempt->timemodified > 0) {
                    return (int)$attempt->timemodified;
                }
                break;
        }

        return 0;
    }

    /**
     * Returns the effective closing date (formatted on two lines).
     *
     * @param  \cm_info $cm        Course module.
     * @param  int      $userid    Student ID.
     * @param  bool     $plaintext True = plain text (PDF export).
     * @return string              Formatted date or '-'.
     */
    public static function get_module_duedate(\cm_info $cm, int $userid = 0, bool $plaintext = false): string {
        $ts = self::get_module_duedate_timestamp($cm, $userid);
        return self::format_datetime($ts, $plaintext);
    }
}
