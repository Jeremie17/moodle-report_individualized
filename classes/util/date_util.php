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

defined('MOODLE_INTERNAL') || die();

/**
 * Utility class for all date-related operations.
 */
class date_util {
    /**
     * Formate un timestamp en date + heure sur deux lignes.
     *
     * Format affiché :
     *  - Ligne 1 : "14 April 2026" (chiffre + mois + année, sans jour de la semaine)
     *  - Ligne 2 : "14h30" (heure avec h à la place du séparateur :)
     *
     * Le mode $plaintext est utilisé pour le PDF (pas de balise HTML).
     *
     * @param  int  $ts        Timestamp Unix.
     * @param  bool $plaintext Vrai = texte brut sans HTML (export PDF).
     * @return string          Date formatée ou '-'.
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
     * Lit les dates de disponibilité issues des restrictions d'accès Moodle (JSON availability).
     *
     * Parse le JSON course_modules.availability pour extraire les conditions de type "date".
     * Retourne ['from' => timestamp, 'to' => timestamp], chaque valeur vaut 0 si absente.
     *
     * Utilisée par get_configured_opendate_timestamp() et get_configured_closedate_timestamp()
     * pour les modules sans paramètre de disponibilité natif (ex : h5pactivity).
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
     * Retourne le timestamp de la date d'ouverture configurée par l'enseignant.
     *
     * Consulte uniquement les paramètres du module (tables assign, quiz, workshop).
     * Pour h5pactivity, lit les restrictions d'accès (JSON availability).
     * Retourne 0 si aucune date n'est configurée.
     *
     * Utilisée par :
     *  - report_individualized_get_module_availablefrom_timestamp()
     *
     * @param  \cm_info $cm Module de cours.
     * @return int          Timestamp Unix ou 0.
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
     * Retourne le timestamp de la date de fermeture configurée par l'enseignant.
     *
     * Consulte uniquement les paramètres du module (tables assign, quiz).
     * Pour h5pactivity, lit les restrictions d'accès (JSON availability).
     * Le workshop est exclu : ses dates sont gérées dans workshop_util.
     * Retourne 0 si aucune date n'est configurée.
     *
     * Utilisée par :
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
     * @param  \cm_info $cm     Module de cours.
     * @param  int      $userid Identifiant étudiant.
     * @return int              Timestamp Unix ou 0.
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
     * Retourne le timestamp du feedback enseignant sur un devoir (assign).
     *
     * Utilisée par :
     *  - get_module_duedate_timestamp()
     *
     * @param  \cm_info $cm     Module de cours.
     * @param  int      $userid Identifiant étudiant.
     * @return int              Timestamp Unix ou 0.
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
     * Retourne le timestamp de la date d'ouverture effective d'un module.
     *
     * Source unique de vérité pour l'affichage ET le filtrage.
     *
     * @param  \cm_info $cm Module de cours.
     * @return int          Timestamp Unix ou 0.
     */
    public static function get_module_availablefrom_timestamp(\cm_info $cm): int {
        $ts = self::get_configured_opendate_timestamp($cm);
        if ($ts > 0) {
            return $ts;
        }
        return (int)$cm->added;
    }

    /**
     * Retourne la date d'ouverture effective d'un module (formatée sur deux lignes).
     *
     * @param  \cm_info $cm        Module de cours.
     * @param  bool     $plaintext Vrai = texte brut (export PDF).
     * @return string              Date formatée ou '-'.
     */
    public static function get_module_availablefrom(\cm_info $cm, bool $plaintext = false): string {
        $ts = self::get_module_availablefrom_timestamp($cm);
        return self::format_datetime($ts, $plaintext);
    }

    /**
     * Retourne le timestamp de la date de fermeture effective.
     *
     * Chaîne de fallback :
     *  1. Date configurée par l'enseignant
     *  2. Date d'attribution de la note
     *  3. Date du feedback enseignant (assign uniquement)
     *  4. Date de soumission par l'étudiant
     *
     * @param  \cm_info $cm     Module de cours.
     * @param  int      $userid Identifiant étudiant.
     * @return int              Timestamp Unix ou 0.
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
     * Retourne la date de fermeture effective (formatée sur deux lignes).
     *
     * @param  \cm_info $cm        Module de cours.
     * @param  int      $userid    Identifiant étudiant.
     * @param  bool     $plaintext Vrai = texte brut (export PDF).
     * @return string              Date formatée ou '-'.
     */
    public static function get_module_duedate(\cm_info $cm, int $userid = 0, bool $plaintext = false): string {
        $ts = self::get_module_duedate_timestamp($cm, $userid);
        return self::format_datetime($ts, $plaintext);
    }
}
