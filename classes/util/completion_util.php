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

defined('MOODLE_INTERNAL') || die();

/**
 * Utility class for completion icons and status.
 */
class completion_util {
    /**
     * Retourne vrai si l'activité est complétée par l'étudiant.
     *
     * Logique par module :
     *  - assign      : soumission existante dans assign_submission (status='submitted')
     *  - quiz        : tentative terminée dans quiz_attempts (state='finished')
     *  - workshop    : soumission existante dans workshop_submissions
     *                  (1 activité = 1 ligne dans le badge, complétée dès qu'une soumission existe)
     *  - h5pactivity : attempt avec completion=1 dans h5pactivity_attempts
     *  - autres      : log 'submitted' dans logstore_standard_log
     *
     * Utilisé par summary_util pour calculer le taux de complétion.
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
     * Retourne l'icône de complétion HTML ou le texte brut selon le contexte.
     *
     * ✓ vert si fait, ✗ rouge sinon.
     * En mode PDF ($plaintext = true) retourne "Oui" / "Non" sans balise HTML.
     *
     * @param  bool $done      Vrai si l'activité est complétée.
     * @param  bool $plaintext Vrai = texte brut (export PDF).
     * @return string          Icône HTML ou texte.
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
     * Retourne l'icône de complétion pour une activité standard (non-workshop).
     *
     * Logique par module :
     *  - assign      : soumission existante dans assign_submission (status='submitted')
     *  - quiz        : tentative terminée dans quiz_attempts (state='finished')
     *  - workshop    : soumission existante dans workshop_submissions
     *  - h5pactivity : attempt avec completion=1 dans h5pactivity_attempts.
     *                  Si non complété et rawscore/maxscore disponibles, affiche le % sous l'icône.
     *                  Si aucun attempt (ex : vidéo sans interactions), affiche ✗ seul.
     *  - autres      : log 'submitted' dans logstore_standard_log
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
                // Récupère le dernier attempt de l'étudiant.
                $attempt = $DB->get_record_sql(
                    "SELECT completion, rawscore, maxscore
                       FROM {h5pactivity_attempts}
                      WHERE h5pactivityid = :id AND userid = :userid
                   ORDER BY attempt DESC
                      LIMIT 1",
                    ['id' => $cm->instance, 'userid' => $userid]
                );
                // Complété → ✓ vert.
                if ($attempt && (int)$attempt->completion === 1) {
                    return self::render_icon(true, $plaintext);
                }
                // Pas complété : calcule le % si maxscore disponible (exercices avec score).
                // Pour les vidéos sans interactions, maxscore = 0 → pas de %.
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
