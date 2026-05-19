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
 * Duration utility for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2026 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

/**
 * Utility class for duration formatting and retrieval.
 */
class duration_util {
    /**
     * Fixed time (in minutes) allocated to the workshop evaluation lines.
     *
     * Internal decision: a peer assessment is estimated at 10 minutes,
     * regardless of the instructor's input for the overall workshop duration.
     */
    public const WORKSHOP_ASSESSMENT_DURATION_MIN = 10;

    /**
     * Formats a duration in minutes into a readable string.
     *
     * < 60 min → "X min"
     * on the hour→ "Xh"
     * hour + minutes → "XhYY" (ex. 90 → "1h30")
     *
     * @param  int    $minutes Duration in minutes.
     * @return string          Formatted duration
     */
    public static function format_duration(int $minutes): string {
        if ($minutes <= 0) {
            return '-';
        }
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        $hours     = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        if ($remaining === 0) {
            return $hours . 'h';
        }
        return $hours . 'h' . str_pad((string)$remaining, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Returns the instructor's estimated duration for a module.
     *
     * The value is read from mdl_customfield_data (the 'estimated_duration' field).
     * The instanceid corresponds to cm->id.
     *
     * For workshop lines of the assessment type, a fixed duration of
     * WORKSHOP_ASSESSMENT_DURATION_MIN minutes applies, regardless of
     * what the instructor entered.
     *
     * @param  \cm_info $cm            Course module.
     * @param  bool     $isassessment  True if the line is a workshop evaluation.
     * @return string                  Formatted duration or '-'.
     */
    public static function get_estimated_duration(\cm_info $cm, bool $isassessment = false): string {
        // Fixed duration for peer assessments.
        if ($isassessment) {
            return self::format_duration(self::WORKSHOP_ASSESSMENT_DURATION_MIN);
        }

        global $DB;

        $sql = 'SELECT cd.charvalue
                  FROM {customfield_data} cd
                  JOIN {customfield_field} cf ON cf.id = cd.fieldid
                 WHERE cf.shortname  = :shortname
                   AND cd.instanceid = :cmid';

        $rec = $DB->get_record_sql($sql, [
            'shortname' => 'duree_estimee',
            'cmid'      => $cm->id,
        ], IGNORE_MISSING);

        if (!$rec || empty(trim((string)$rec->charvalue))) {
            return '-';
        }

        $value = trim((string)$rec->charvalue);
        return is_numeric($value) ? self::format_duration((int)$value) : $value;
    }

    /**
     * Calculates the sum of the durations estimated by the teacher for a section.
     *
     * For workshop activities, the duration in the assessment line is counted
     * at WORKSHOP_ASSESSMENT_DURATION_MIN minutes (internal fixed duration).
     * The submission line uses the value from the custom field.
     *
     * Returns '-' if no numeric value is found.
     *
     * @param  \cm_info[] $cms          List of modules in the section.
     * @param  int[]      $workshopcmids Workshop module IDs for the section.
     * @return string                   Total duration formatted or '-'.
     */
    public static function get_section_estimated_total(
        array $cms,
        array $workshopcmids = []
    ): string {
        global $DB;

        if (empty($cms)) {
            return '-';
        }

        $total = 0;

        // Adds the fixed duration of the workshop evaluation lines.
        // Each workshop has 1 submission line + 1 evaluation line.
        // The evaluation line is always equal to WORKSHOP_ASSESSMENT_DURATION_MIN.
        $total += count($workshopcmids) * self::WORKSHOP_ASSESSMENT_DURATION_MIN;

        // For the other modules (+ workshop submission), we read customfield_data.
        $cmids = array_map(fn (\cm_info $cm) => $cm->id, $cms);

        if (!empty($cmids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cmid');

            $sql = "SELECT cd.charvalue
                      FROM {customfield_data} cd
                      JOIN {customfield_field} cf ON cf.id = cd.fieldid
                     WHERE cf.shortname = 'duree_estimee'
                       AND cd.instanceid $insql";

            $records = $DB->get_records_sql($sql, $inparams);

            foreach ($records as $rec) {
                $value = trim((string)$rec->charvalue);
                if (is_numeric($value) && (int)$value > 0) {
                    $total += (int)$value;
                }
            }
        }

        return $total > 0 ? self::format_duration($total) : '-';
    }

    /**
     * Returns the duration declared by the student in raw minutes (integer).
     *
     * Same logic as get_student_duration() but returns an int instead of a
     * Formatted string. Used by summary_util to sum over multiple sections.
     *
     * @param  \cm_info $cm     TIME feedback module.
     * @param  int      $userid Student ID.
     * @return int              Duration in minutes, 0 if not specified.
     */
    public static function get_student_duration_minutes(\cm_info $cm, int $userid): int {
        global $DB;

        $sql = 'SELECT fv.value
                  FROM {feedback_value} fv
                  JOIN {feedback_completed} fc ON fc.id = fv.completed
                 WHERE fc.feedback = :feedbackid
                   AND fc.userid   = :userid';

        $values = $DB->get_records_sql($sql, [
            'feedbackid' => $cm->instance,
            'userid'     => $userid,
        ]);

        foreach ($values as $val) {
            $v = trim((string)$val->value);
            if (empty($v) || $v === '0') {
                continue;
            }
            if (is_numeric($v) && (int)$v > 0) {
                return (int)$v;
            }
            if (preg_match('/^(\d+)/', $v, $matches) && (int)$matches[1] > 0) {
                return (int)$matches[1];
            }
        }
        return 0;
    }

    /**
     * Returns the duration declared by the student for a module, formatted.
     *
     * @param  \cm_info $cm     Course module.
     * @param  int      $userid Student ID.
     * @return string           Formatted duration or '-'.
     */
    public static function get_student_duration(\cm_info $cm, int $userid): string {
        global $DB;

        $sql = 'SELECT fv.value
                  FROM {feedback_value} fv
                  JOIN {feedback_completed} fc ON fc.id = fv.completed
                 WHERE fc.feedback = :feedbackid
                   AND fc.userid   = :userid';

        $values = $DB->get_records_sql($sql, [
            'feedbackid' => $cm->instance,
            'userid'     => $userid,
        ]);

        foreach ($values as $val) {
            $v = trim((string)$val->value);

            if (empty($v) || $v === '0') {
                continue;
            }
            // A completely digital value.
            if (is_numeric($v) && (int)$v > 0) {
                return self::format_duration((int)$v);
            }
            // Value starting with a number (ex: "90 minutes").
            if (preg_match('/^(\d+)/', $v, $matches) && (int)$matches[1] > 0) {
                return self::format_duration((int)$matches[1]);
            }
            // Non-convertible free value — returned as is.
            return $v;
        }

        return '-';
    }
}
