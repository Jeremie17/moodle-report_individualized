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
 * Completion utility for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

/**
 * Utility class for completion icons and status.
 */
class completion_util {
    /**
     * Returns true if the activity is completed by the student.
     *
     * Modular logic:
     *  - assign      : existing submission in assign_submission (status='submitted')
     *  - quiz        : attempt completed in quiz_attempts (state='finished')
     *  - workshop    : existing submission in workshop_submissions
     *                  (1 activity = 1 line in the badge, completed as soon as a submission exists)
     *  - h5pactivity : attempt avec completion = 1 in h5pactivity_attempts
     *  - other       : log 'submitted' in logstore_standard_log
     *
     * Used by summary_util to calculate the completion rate.
     *
     * @param  \cm_info $cm     Module de cours.
     * @param  int      $userid Identifiant étudiant.
     * @return bool              Vrai si complété.
     */
    public static function is_complete(\cm_info $cm, int $userid): bool {
        global $DB;

        switch ($cm->modname) {
            case 'assign':
                return $DB->record_exists('assign_submission', [
                    'assignment' => $cm->instance,
                    'userid'     => $userid,
                    'status'     => 'submitted',
                ]);
            case 'quiz':
                return $DB->record_exists('quiz_attempts', [
                    'quiz'   => $cm->instance,
                    'userid' => $userid,
                    'state'  => 'finished',
                ]);
            case 'workshop':
                return $DB->record_exists('workshop_submissions', [
                    'workshopid' => $cm->instance,
                    'authorid'   => $userid,
                ]);
            case 'h5pactivity':
                return $DB->record_exists('h5pactivity_attempts', [
                    'h5pactivityid' => $cm->instance,
                    'userid'        => $userid,
                    'completion'    => 1,
                ]);
            default:
                return $DB->record_exists_select(
                    'logstore_standard_log',
                    'userid = :userid AND contextinstanceid = :cmid AND action = :action',
                    ['userid' => $userid, 'cmid' => $cm->id, 'action' => 'submitted']
                );
        }
    }

    /**
     * Returns the HTML completion icon or plain text depending on the context.

    *
    * ✓ Green if completed, ✗ Red otherwise.

    * In PDF mode ($plaintext = true) returns "Yes" / "No" without HTML tags.
     *
     * @param  bool $done      True if activity is completed.
     * @param  bool $plaintext True = plain text (PDF export).
     * @return string          HTML icon or text.
     */
    public static function render_icon(bool $done, bool $plaintext): string {
        if ($plaintext) {
            return $done ? get_string('yes') : get_string('no');
        }
        return $done
            ? '<span class="text-success" title="' . get_string('yes') . '">&#10003;</span>'
            : '<span class="text-danger"  title="' . get_string('no')  . '">&#10007;</span>';
    }

    /**

    * Returns the completion icon for a standard (non-workshop) activity.
    * Logic per module:
    * - assign: existing submission in assign_submission (status='submitted')
    * - quiz: completed attempt in quiz_attempts (state='finished')
    * - workshop: existing submission in workshop_submissions
    * - h5pactivity: attempt with completion=1 in h5pactivity_attempts.
    * If not completed and rawscore/maxscore available, displays the % under the icon.
    * If no attempt (e.g., video without interactions), displays only a checkmark.
    * - other: log 'submitted' in logstore_standard_log.
     *
     * @param  \cm_info $cm        Module de cours.
     * @param  int      $userid    Identifiant étudiant.
     * @param  bool     $plaintext Vrai = texte brut (export PDF).
     * @return string              Icône HTML ou texte.
     */
    public static function get_completion_icon(
        \cm_info $cm,
        int $userid,
        bool $plaintext = false
    ): string {
        global $DB;

        switch ($cm->modname) {
            case 'assign':
                $done = $DB->record_exists('assign_submission', [
                    'assignment' => $cm->instance,
                    'userid'     => $userid,
                    'status'     => 'submitted',
                ]);
                break;
            case 'quiz':
                $done = $DB->record_exists('quiz_attempts', [
                    'quiz'   => $cm->instance,
                    'userid' => $userid,
                    'state'  => 'finished',
                ]);
                break;
            case 'workshop':
                $done = $DB->record_exists('workshop_submissions', [
                    'workshopid' => $cm->instance,
                    'authorid'   => $userid,
                ]);
                break;
            case 'h5pactivity':
                // Retrieve the student's last attempt.
                $attempt = $DB->get_record_sql(
                    "SELECT completion, rawscore, maxscore
                       FROM {h5pactivity_attempts}
                      WHERE h5pactivityid = :id AND userid = :userid
                   ORDER BY attempt DESC
                      LIMIT 1",
                    ['id' => $cm->instance, 'userid' => $userid]
                );
                // Completed → ✓ green.
                if ($attempt && (int)$attempt->completion === 1) {
                    return self::render_icon(true, $plaintext);
                }
                // Not completed : calculate the % if maxscore is available (exercises with score).
                // For videos without interactions, maxscore = 0 → no %.
                $pct = ($attempt && (float)$attempt->maxscore > 0)
                    ? (int)round((float)$attempt->rawscore / (float)$attempt->maxscore * 100)
                    : null;
                if ($plaintext) {
                    return get_string('no') . ($pct !== null ? ' (' . $pct . '%)' : '');
                }
                return '<span class="text-danger" title="' . get_string('no') . '">&#10007;</span>'
                    . ($pct !== null ? '<br><small>(' . $pct . '%)</small>' : '');
            default:
                $done = $DB->record_exists_select(
                    'logstore_standard_log',
                    'userid = :userid AND contextinstanceid = :cmid AND action = :action',
                    ['userid' => $userid, 'cmid' => $cm->id, 'action' => 'submitted']
                );
                break;
        }

        return self::render_icon($done, $plaintext);
    }
}
