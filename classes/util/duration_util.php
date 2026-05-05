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
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Utility class for duration formatting and retrieval.
 */
class duration_util {
    /**
     * Durée forfaitaire (en minutes) attribuée aux lignes workshop d'évaluation.
     *
     * Décision interne : une évaluation par pair est estimée à 10 minutes,
     * quel que soit ce que le prof a saisi pour la durée globale de l'atelier.
     */
    public const WORKSHOP_ASSESSMENT_DURATION_MIN = 10;

    /**
     * Formate une durée en minutes en chaîne lisible.
     *
     * < 60 min → "X min"
     * heure pile → "Xh"
     * heure + minutes → "XhYY" (ex. 90 → "1h30")
     *
     * @param  int    $minutes Durée en minutes.
     * @return string          Durée formatée.
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
     * Retourne la durée estimée par l'enseignant pour un module.
     *
     * La valeur est lue depuis mdl_customfield_data (champ 'duree_estimee').
     * L'instanceid correspond à cm->id.
     *
     * Pour les lignes workshop de type évaluation, une durée forfaitaire de
     * WORKSHOP_ASSESSMENT_DURATION_MIN minutes s'applique, indépendamment de
     * ce que le prof a saisi.
     *
     * @param  \cm_info $cm            Module de cours.
     * @param  bool     $isassessment  Vrai si la ligne est une évaluation workshop.
     * @return string                  Durée formatée ou '-'.
     */
    public static function get_estimated_duration(\cm_info $cm, bool $isassessment = false): string {
        // Durée forfaitaire pour les évaluations par pair.
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
     * Calcule la somme des durées estimées par le prof pour une section.
     *
     * Pour les activités workshop, la durée de la ligne évaluation est comptée
     * à WORKSHOP_ASSESSMENT_DURATION_MIN minutes (durée forfaitaire interne).
     * La ligne soumission utilise la valeur du custom field.
     *
     * Retourne '-' si aucune valeur numérique n'est trouvée.
     *
     * @param  \cm_info[] $cms          Liste des modules de la section.
     * @param  int[]      $workshopcmids IDs des modules workshop de la section.
     * @return string                   Durée totale formatée ou '-'.
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

        // Additionne la durée forfaitaire des lignes évaluation workshop.
        // Chaque workshop a 1 ligne soumission + 1 ligne évaluation.
        // La ligne évaluation vaut toujours WORKSHOP_ASSESSMENT_DURATION_MIN.
        $total += count($workshopcmids) * self::WORKSHOP_ASSESSMENT_DURATION_MIN;

        // Pour les autres modules (+ soumission workshop), on lit customfield_data.
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
     * Retourne la durée déclarée par l'étudiant via une activité feedback "TIME".
     *
     * Convention : le prof crée une activité de type "feedback" avec un idnumber
     * commençant par "TIME". L'étudiant répond avec une durée en minutes.
     * On accepte :
     *  - Une valeur entièrement numérique ("90")
     *  - Une valeur commençant par un nombre ("90 minutes", "90min")
     *  - Une valeur libre ("1h30") — retournée telle quelle
     *
     * @param  \cm_info $cm     Module feedback.
     * @param  int      $userid Identifiant étudiant.
     * @return string           Durée formatée ou '-'.
     */

    /**
     * Retourne la durée déclarée par l'étudiant en minutes brutes (entier).
     *
     * Même logique que get_student_duration() mais retourne un int au lieu d'une
     * chaîne formatée. Utilisé par summary_util pour sommer sur plusieurs sections.
     *
     * @param  \cm_info $cm     Module feedback TIME.
     * @param  int      $userid Identifiant étudiant.
     * @return int              Durée en minutes, 0 si non renseignée.
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
     * Retourne la durée déclarée par l'étudiant pour un module, formatée.
     *
     * @param  \cm_info $cm     Module de cours.
     * @param  int      $userid Identifiant étudiant.
     * @return string           Durée formatée ou '-'.
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
            // Valeur entièrement numérique.
            if (is_numeric($v) && (int)$v > 0) {
                return self::format_duration((int)$v);
            }
            // Valeur commençant par un nombre (ex. "90 minutes").
            if (preg_match('/^(\d+)/', $v, $matches) && (int)$matches[1] > 0) {
                return self::format_duration((int)$matches[1]);
            }
            // Valeur libre non convertible — retournée telle quelle.
            return $v;
        }

        return '-';
    }
}
