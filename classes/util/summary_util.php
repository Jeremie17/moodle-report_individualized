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
 * Summary utility for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

use html_writer;

/**
 * Utility class for computing and rendering summary metrics.
 *
 * Computes five metrics : estimated duration, student declared duration,
 * completion rate, average grade, and resources viewed.
 */
class summary_util {
    /**
     * Computes all 5 summary metrics for a set of resources and activities.
     *
     * @param  \cm_info[] $resources        Liste des ressources.
     * @param  \cm_info[] $activities       Liste des activités.
     * @param  \cm_info[] $timefeedbackcms  Feedbacks TIME de la portée (section ou cours).
     * @param  int        $userid           Identifiant étudiant.
     * @param  int        $datefrom         Timestamp début (0 = pas de limite).
     * @param  int        $dateto           Timestamp fin   (0 = pas de limite).
     * @return array {
     *     profestimated    : string,
     *     studentestimated : string,
     *     completionrate   : array|null  {done, total, pct},
     *     avggrade         : array|null  {avg, max, count},
     *     resourcesviewed  : array|null  {viewed, total},
     * }
     */
    public static function compute(
        array $resources,
        array $activities,
        array $timefeedbackcms,
        int $userid,
        int $datefrom = 0,
        int $dateto = 0
    ): array {
        $workshopcmids = [];
        foreach ($activities as $cm) {
            if ($cm->modname === 'workshop') {
                $workshopcmids[] = $cm->id;
            }
        }

        return [
            'profestimated'    => duration_util::get_section_estimated_total(
                array_merge($resources, $activities),
                $workshopcmids
            ),
            'studentestimated' => self::sum_student_duration($timefeedbackcms, $userid),
            'completionrate'   => self::compute_completion_rate($activities, $userid),
            'avggrade'         => self::compute_avg_grade($activities, $userid),
            'resourcesviewed'  => self::compute_resources_viewed($resources, $userid, $datefrom, $dateto),
        ];
    }

    /**
     * Somme les durées déclarées étudiant sur plusieurs feedbacks TIME.
     *
     * @param  \cm_info[] $timefeedbackcms Liste des feedbacks TIME de la portée.
     * @param  int        $userid          Identifiant étudiant.
     * @return string                      Durée totale formatée ou '-'.
     */
    private static function sum_student_duration(array $timefeedbackcms, int $userid): string {
        if (empty($timefeedbackcms)) {
            return '-';
        }
        $total = 0;
        foreach ($timefeedbackcms as $cm) {
            $total += duration_util::get_student_duration_minutes($cm, $userid);
        }
        return $total > 0 ? duration_util::format_duration($total) : '-';
    }

    /**
     * Calcule le taux de complétion des activités.
     *
     * Une activité est "complétée" selon la logique de completion_util::is_complete().
     * Retourne null si aucune activité dans la liste.
     *
     * @param  \cm_info[] $activities Liste des activités.
     * @param  int        $userid     Identifiant étudiant.
     * @return array|null             {done, total, pct} ou null.
     */
    private static function compute_completion_rate(array $activities, int $userid): ?array {
        global $DB;

        if (empty($activities)) {
            return null;
        }
        $total = 0;
        $done  = 0;
        foreach ($activities as $cm) {
            if ($cm->modname === 'workshop') {
                // Workshop compte pour 2 : soumission + évaluation.
                $total += 2;
                // Soumission.
                if (
                    $DB->record_exists('workshop_submissions', [
                    'workshopid' => $cm->instance,
                    'authorid'   => $userid,
                    ])
                ) {
                    $done++;
                }
                // Assessment (student has assessed at least one peer).
                if (
                    $DB->record_exists('workshop_assessments', [
                    'reviewerid' => $userid,
                    ])
                ) {
                    $done++;
                }
            } else {
                $total++;
                if (completion_util::is_complete($cm, $userid)) {
                    $done++;
                }
            }
        }
        return [
            'done'  => $done,
            'total' => $total,
            'pct'   => (int)round($done / $total * 100),
        ];
    }

    /**
     * Calcule la note moyenne sur les activités notées.
     *
     * Lit tous les grade_items du module (soumission + évaluation pour le workshop).
     * et grade_grades. Retourne null si aucune note disponible.
     *
     * @param  \cm_info[] $activities Liste des activités.
     * @param  int        $userid     Identifiant étudiant.
     * @return array|null             {avg, max, count} ou null.
     */
    private static function compute_avg_grade(array $activities, int $userid): ?array {
        global $DB;

        if (empty($activities)) {
            return null;
        }

        $totalgrade = 0.0;
        $totalmax   = 0.0;
        $count      = 0;

        foreach ($activities as $cm) {
            // On récupère tous les grade items du module.
            // Pour les modules standard : un seul item (itemnumber=0).
            // Pour le workshop : deux items (soumission + évaluation), les deux comptent.
            $gradeitems = $DB->get_records('grade_items', [
                'itemtype'     => 'mod',
                'itemmodule'   => $cm->modname,
                'iteminstance' => $cm->instance,
            ], 'itemnumber ASC', 'id, grademax');

            foreach ($gradeitems as $gradeitem) {
                if ((float)$gradeitem->grademax <= 0) {
                    continue;
                }

                $grade = $DB->get_record('grade_grades', [
                    'itemid' => $gradeitem->id,
                    'userid' => $userid,
                ], 'finalgrade', IGNORE_MISSING);

                if (!$grade || $grade->finalgrade === null) {
                    continue;
                }

                $totalgrade += (float)$grade->finalgrade;
                $totalmax   += (float)$gradeitem->grademax;
                $count++;
            }
        }

        if ($count === 0 || $totalmax <= 0) {
            return null;
        }

        // On normalise tout sur /20 pour une lisibilité cohérente.
        // Formule : (somme des notes brutes / somme des notes max) × 20.
        // Ex : 8/10 + 12/15 = 20/25 → 20/25 × 20 = 16/20.
        $avgon20 = round(($totalgrade / $totalmax) * 20, 1);

        return [
            'avg'   => $avgon20,
            'max'   => 20,
            'count' => $count,
        ];
    }

    /**
     * Calcule le nombre de ressources consultées au moins une fois.
     *
     * @param  \cm_info[] $resources Liste des ressources.
     * @param  int        $userid    Identifiant étudiant.
     * @param  int        $datefrom  Timestamp début (pour view_stats).
     * @param  int        $dateto    Timestamp fin   (pour view_stats).
     * @return array|null            {viewed, total} ou null.
     */
    private static function compute_resources_viewed(
        array $resources,
        int $userid,
        int $datefrom,
        int $dateto
    ): ?array {
        if (empty($resources)) {
            return null;
        }
        $total  = count($resources);
        $viewed = 0;
        foreach ($resources as $cm) {
            $stats = view_stats_util::get_view_stats($cm, $userid, $datefrom, $dateto);
            if ($stats['count'] > 0) {
                $viewed++;
            }
        }
        return ['viewed' => $viewed, 'total' => $total];
    }

    /**
     * Rend les métriques sous forme de bandeau de pilules HTML.
     *
     * Couleurs :
     *  - Durée prof          : gris (cohérent avec les badges existants)
     *  - Durée étudiant      : bleu
     *  - Complétion 100%     : vert   / 50-99% : orange  / <50% : rouge
     *  - Note moyenne        : violet
     *  - Ressources vues     : turquoise
     *
     * @param  array $summary Issu de compute().
     * @return string         HTML du bandeau ou chaîne vide si aucune métrique.
     */
    public static function render_pills(array $summary): string {
        $pills = '';

        // Durée estimée prof.
        if (!empty($summary['profestimated']) && $summary['profestimated'] !== '-') {
            $pills .= html_writer::tag(
                'span',
                get_string('profestimated', 'report_individualized') . ' : ' . $summary['profestimated'],
                ['class' => 'report-individualized-summary-pill report-individualized-summary-duration-prof']
            );
        }

        // Durée déclarée étudiant.
        if (!empty($summary['studentestimated']) && $summary['studentestimated'] !== '-') {
            $pills .= html_writer::tag(
                'span',
                get_string('studentestimated', 'report_individualized') . ' : ' . $summary['studentestimated'],
                ['class' => 'report-individualized-summary-pill report-individualized-summary-duration-student']
            );
        }

        // Taux de complétion.
        if (!empty($summary['completionrate'])) {
            $cr  = $summary['completionrate'];
            $mod = $cr['pct'] >= 100 ? 'full' : ($cr['pct'] >= 50 ? 'partial' : 'low');
            $pills .= html_writer::tag(
                'span',
                get_string('summary_completionrate', 'report_individualized')
                    . ' : ' . $cr['done'] . '/' . $cr['total'] . ' (' . $cr['pct'] . '%)',
                ['class' => 'report-individualized-summary-pill report-individualized-summary-completion-' . $mod]
            );
        }

        // Note moyenne.
        if (!empty($summary['avggrade'])) {
            $ag = $summary['avggrade'];
            $pills .= html_writer::tag(
                'span',
                get_string('summary_avggrade', 'report_individualized')
                    . ' : ' . $ag['avg'] . '/' . $ag['max'],
                ['class' => 'report-individualized-summary-pill report-individualized-summary-grade']
            );
        }

        // Ressources consultées.
        if (!empty($summary['resourcesviewed'])) {
            $rv = $summary['resourcesviewed'];
            $pills .= html_writer::tag(
                'span',
                get_string('summary_resourcesviewed', 'report_individualized')
                    . ' : ' . $rv['viewed'] . '/' . $rv['total'],
                ['class' => 'report-individualized-summary-pill report-individualized-summary-resources']
            );
        }

        if (empty($pills)) {
            return '';
        }

        return html_writer::div($pills, 'report-individualized-summary-bar d-flex flex-wrap gap-2 mb-3');
    }

    /**
     * Rend les métriques en texte brut pour l'export PDF.
     *
     * @param  array $summary Issu de compute().
     * @return string         Métriques séparées par " | ".
     */
    public static function render_pdf(array $summary): string {
        $parts = [];

        if (!empty($summary['profestimated']) && $summary['profestimated'] !== '-') {
            $parts[] = get_string('profestimated', 'report_individualized')
                . ' : ' . $summary['profestimated'];
        }
        if (!empty($summary['studentestimated']) && $summary['studentestimated'] !== '-') {
            $parts[] = get_string('studentestimated', 'report_individualized')
                . ' : ' . $summary['studentestimated'];
        }
        if (!empty($summary['completionrate'])) {
            $cr      = $summary['completionrate'];
            $parts[] = get_string('summary_completionrate', 'report_individualized')
                . ' : ' . $cr['done'] . '/' . $cr['total'] . ' (' . $cr['pct'] . '%)';
        }
        if (!empty($summary['avggrade'])) {
            $ag      = $summary['avggrade'];
            $parts[] = get_string('summary_avggrade', 'report_individualized')
                . ' : ' . $ag['avg'] . '/' . $ag['max'];
        }
        if (!empty($summary['resourcesviewed'])) {
            $rv      = $summary['resourcesviewed'];
            $parts[] = get_string('summary_resourcesviewed', 'report_individualized')
                . ' : ' . $rv['viewed'] . '/' . $rv['total'];
        }

        return implode(' | ', $parts);
    }
}
