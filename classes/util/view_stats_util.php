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
 * View statistics utility for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Utility class for consultation statistics, module type labels and modality.
 */
class view_stats_util {
    /**
     * Retourne le nom localisé d'un module de cours.
     *
     * @param  string $modname Nom machine du module (ex. 'assign', 'quiz').
     * @return string          Nom dans la langue courante de l'interface.
     */
    public static function get_modtype_label(string $modname): string {
        if (get_string_manager()->string_exists('modulename', $modname)) {
            return get_string('modulename', $modname);
        }
        return $modname;
    }

    /**
     * Retourne la modalité pédagogique d'un module depuis les custom fields.
     *
     * Étape par étape :
     *  1. On interroge customfield_data (valeur choisie) + customfield_field (définition).
     *  2. La valeur choisie est un entier base-1 stocké dans intvalue.
     *     (ex. 2 = deuxième option de la liste — Moodle indexe à partir de 1, pas 0)
     *  3. La liste des options est dans configdata (JSON, clé "options",
     *     valeurs séparées par \r\n).
     *  4. On décode, on découpe, on retourne l'option à l'index choisi.
     *
     * IGNORE_MISSING = retourne false si aucune ligne, sans lever d'exception.
     * C'est l'équivalent du .find() JS qui retourne undefined plutôt que de planter.
     *
     * @param  \cm_info $cm Module de cours.
     * @return string       Modalité pédagogique ou '-'.
     */
    public static function get_activity_modality(\cm_info $cm): string {
        global $DB;

        $sql = 'SELECT cd.intvalue, cf.configdata
                  FROM {customfield_data} cd
                  JOIN {customfield_field} cf ON cf.id = cd.fieldid
                 WHERE cf.shortname  = :shortname
                   AND cd.instanceid = :cmid';

        $rec = $DB->get_record_sql($sql, [
            'shortname' => 'modalite',
            'cmid'      => $cm->id,
        ], IGNORE_MISSING);

        if (!$rec || (int)$rec->intvalue <= 0) {
            return '-';
        }

        // configdata est une chaîne JSON : {"options":"Recherche personnelle\r\nDébat\r\n..."}
        $config = json_decode($rec->configdata);

        if (!$config || empty($config->options)) {
            return '-';
        }

        // On découpe la chaîne d'options par les sauts de ligne.
        $options = preg_split('/\r\n|\r|\n/', $config->options);

        // Moodle stocke l'index en base-1 → on soustrait 1 pour PHP (base-0).
        $index = (int)$rec->intvalue - 1;

        return isset($options[$index]) ? trim($options[$index]) : '-';
    }

    /**
     * Construit le libellé affiché dans la colonne "Type / Modalité pédagogique".
     *
     * Format HTML (pour le tableau web) :
     *  Ligne 1 : type du module (ex. "Devoir")
     *  Ligne 2 : titre de l'activité/ressource (ex. "Mathématiques")
     *  Ligne 3 : modalité pédagogique si renseignée (ex. "Recherche personnelle")
     *  Ligne 4 : suffixe workshop si présent (ex. "(travail remis)")
     *
     * Format texte brut (pour le PDF) : les lignes sont séparées par " | ".
     *
     * Pour le workshop, $itemlabel contient le nom du grade item Moodle,
     * ex. "Test atelier (travail remis)". On extrait le suffixe entre parenthèses
     * pour le placer sur une ligne dédiée.
     *
     * @param  \cm_info    $cm        Module de cours.
     * @param  string|null $itemlabel Label grade book (workshop uniquement).
     * @param  bool        $plaintext Vrai = texte brut (export PDF).
     * @return string                 Libellé multi-lignes.
     */
    public static function get_activity_label(
        \cm_info $cm,
        ?string $itemlabel = null,
        bool $plaintext = false
    ): string {
        $sep      = $plaintext ? ' | ' : '<br>';
        $type     = self::get_modtype_label($cm->modname);
        $name     = format_string($cm->name);
        $modality = self::get_activity_modality($cm);

        // Cas workshop : extraire le suffixe "(travail remis)" ou "(évaluation)"
        // depuis le label du grade book pour le placer en dernière ligne.
        if ($cm->modname === 'workshop' && $itemlabel !== null) {
            $suffix = '';
            if (preg_match('/(\([^)]+\))\s*$/', $itemlabel, $matches)) {
                $suffix = trim($matches[1]);
            }

            $lines = [$type, $name];
            if ($modality !== '-') {
                $lines[] = $modality;
            }
            if (!empty($suffix)) {
                $lines[] = $suffix;
            }
            return implode($sep, $lines);
        }

        // Cas standard.
        $lines = [$type, $name];
        if ($modality !== '-') {
            $lines[] = $modality;
        }
        return implode($sep, $lines);
    }

    /**
     * Retourne les statistiques de consultation d'un module par un étudiant.
     *
     * @param  \cm_info $cm       Module de cours.
     * @param  int      $userid   Identifiant étudiant.
     * @param  int      $datefrom Timestamp de début (0 = pas de limite).
     * @param  int      $dateto   Timestamp de fin   (0 = pas de limite).
     * @return array              ['count' => int, 'first' => int, 'last' => int]
     */
    public static function get_view_stats(
        \cm_info $cm,
        int $userid,
        int $datefrom = 0,
        int $dateto = 0
    ): array {
        global $DB;

        $params = [
            'userid'   => $userid,
            'cmid'     => $cm->id,
            'action'   => 'viewed',
            'ctxlevel' => CONTEXT_MODULE,
        ];

        $datewhere = '';
        if ($datefrom > 0) {
            $datewhere          .= ' AND timecreated >= :datefrom';
            $params['datefrom']  = $datefrom;
        }
        if ($dateto > 0) {
            $datewhere        .= ' AND timecreated <= :dateto';
            $params['dateto']  = $dateto;
        }

        $sql = 'SELECT COUNT(*)         AS viewcount,
                       MIN(timecreated) AS firstview,
                       MAX(timecreated) AS lastview
                  FROM {logstore_standard_log}
                 WHERE userid            = :userid
                   AND contextinstanceid = :cmid
                   AND contextlevel      = :ctxlevel
                   AND action            = :action'
                 . $datewhere;

        $rec = $DB->get_record_sql($sql, $params);

        return [
            'count' => $rec ? (int)$rec->viewcount : 0,
            'first' => $rec ? (int)$rec->firstview  : 0,
            'last'  => $rec ? (int)$rec->lastview    : 0,
        ];
    }

    /**
     * Formate une plage de dates de consultation.
     *
     * @param  array $stats Tableau issu de get_view_stats().
     * @param  bool  $plaintext Vrai = texte brut (export PDF).
     * @return string           Plage formatée.
     */
    public static function format_view_range(array $stats, bool $plaintext = false): string {
        if ($stats['count'] === 0) {
            return '-';
        }
        $first = date_util::format_datetime($stats['first'], $plaintext);
        if ($stats['count'] === 1) {
            return $first;
        }
        $sep  = $plaintext ? ' → ' : '<br>→<br>';
        $last = date_util::format_datetime($stats['last'], $plaintext);
        return $first . $sep . $last;
    }
}
