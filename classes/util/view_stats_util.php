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
 * @copyright 2026 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

/**
 * Utility class for consultation statistics, module type labels and modality.
 */
class view_stats_util {
    /**
     * Returns the localized name of a course module.
     *
     * @param  string $modname Module machine name (ex. 'assign', 'quiz').
     * @return string          Name in the interface's current language.
     */
    public static function get_modtype_label(string $modname): string {
        if (get_string_manager()->string_exists('modulename', $modname)) {
            return get_string('modulename', $modname);
        }
        return $modname;
    }

    /**
     * Returns the teaching method of a module from the custom fields.
     *
     * @param  \cm_info $cm Course module.
     * @return string       Teaching method or '-'.
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

        // Configdata is a JSON string.
        $config = json_decode($rec->configdata);

        if (!$config || empty($config->options)) {
            return '-';
        }

        // The chain of options is broken up by line breaks.
        $options = preg_split('/\r\n|\r|\n/', $config->options);

        // Moodle stores the index in base-1 → we subtract 1 for PHP (base-0).
        $index = (int)$rec->intvalue - 1;

        return isset($options[$index]) ? trim($options[$index]) : '-';
    }

    /**
     * Construct the label displayed in the "Type / Teaching Method" column.
     *
     * @param  \cm_info    $cm        Course module.
     * @param  string|null $itemlabel Label grade book (workshop only).
     * @param  bool        $plaintext True = plain text (PDF export).
     * @return string                 Multi-line label.
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

        // Workshop case: extract the suffix "(submitted work)" or "(evaluation)"
        // From the gradebook label, placed on the last row.
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

        // Standard case.
        $lines = [$type, $name];
        if ($modality !== '-') {
            $lines[] = $modality;
        }
        return implode($sep, $lines);
    }

    /**
     * Returns the statistics for a module's usage by a student.
     *
     * @param  \cm_info $cm       Course module.
     * @param  int      $userid   Student ID.
     * @param  int      $datefrom Timestamp beginning (0 = no limit).
     * @param  int      $dateto   Timestamp end   (0 = no limit).
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
            'first' => $rec ? (int)$rec->firstview : 0,
            'last'  => $rec ? (int)$rec->lastview : 0,
        ];
    }

    /**
     * Formats a range of consultation dates.
     *
     * @param  array $stats Table from get_view_stats().
     * @param  bool  $plaintext True = plain text (PDF export).
     * @return string           Fromated range.
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
