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
     * @param  \cm_info[] $resources        List of resources.
     * @param  \cm_info[] $activities       List of activities.
     * @param  \cm_info[] $timefeedbackcms  Feedback TIME of scope (section or course).
     * @param  int        $userid           Student ID.
     * @param  int        $datefrom         Timestamp beginning (0 = no limit).
     * @param  int        $dateto           Timestamp end   (0 = no limit).
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
     * Sum the durations declared by students across multiple TIME feedback sessions.
     *
     * @param  \cm_info[] $timefeedbackcms List of TIME feedbacks from the scope.
     * @param  int        $userid          Student ID.
     * @return string                      Total duration formatted or '-'.
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
     * Calculate the activity completion rate.
     *
     * An activity is "completed" according to the logic of completion_util::is_complete().
     * Returns null if there is no activity in the list.
     *
     * @param  \cm_info[] $activities List of activities.
     * @param  int        $userid     Student ID.
     * @return array|null             {done, total, pct} or null.
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
                // Workshop counts for 2: submission + evaluation.
                $total += 2;
                // Submit.
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
     * Calculate the average grade for the graded activities.
     *
     * Reads all grade_items of the module (submission + evaluation for the workshop).
     * and grade_grades. Returns null if no grade is available.
     *
     * @param  \cm_info[] $activities List of activities.
     * @param  int        $userid     Student ID.
     * @return array|null             {avg, max, count} or null.
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
            // We retrieve all the grade items from the module.
            // For standard modules: a single item (itemnumber=0).
            // For the workshop: two items (submission + evaluation), both count.
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

        // We normalize everything to /20 for consistent readability.
        // Formula: (sum of raw grades / sum of maximum grades) × 20.
        // Ex : 8/10 + 12/15 = 20/25 → 20/25 × 20 = 16/20.
        $avgon20 = round(($totalgrade / $totalmax) * 20, 1);

        return [
            'avg'   => $avgon20,
            'max'   => 20,
            'count' => $count,
        ];
    }

    /**
     * Calculate the number of resources consulted at least once.
     *
     * @param  \cm_info[] $resources Liste of resources.
     * @param  int        $userid    Student ID.
     * @param  int        $datefrom  Timestamp beginning (for view_stats).
     * @param  int        $dateto    Timestamp end   (for view_stats).
     * @return array|null            {viewed, total} or null.
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
     * Renders metrics in the form of an HTML pill banner.
     *
     * Colors :
     *  - Duration teacher          : grey (cohérent avec les badges existants)
     *  - Duration student      : blue
     *  - Completion 100%     : green / 50-99% : orange  / <50% : rouge
     *  - Average rating        : purple
     *  - Resources viewss     : turquoise
     *
     * @param  array $summary From compute().
     * @return string         Html of the banner or empty string if no metrics.
     */
    public static function render_pills(array $summary): string {
        $pills = '';

        // Estimated duration (teacher).
        if (!empty($summary['profestimated']) && $summary['profestimated'] !== '-') {
            $pills .= html_writer::tag(
                'span',
                get_string('profestimated', 'report_individualized') . ' : ' . $summary['profestimated'],
                ['class' => 'report-individualized-summary-pill report-individualized-summary-duration-prof']
            );
        }

        // Declared student duration.
        if (!empty($summary['studentestimated']) && $summary['studentestimated'] !== '-') {
            $pills .= html_writer::tag(
                'span',
                get_string('studentestimated', 'report_individualized') . ' : ' . $summary['studentestimated'],
                ['class' => 'report-individualized-summary-pill report-individualized-summary-duration-student']
            );
        }

        // Completion rate.
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

        // Average rating.
        if (!empty($summary['avggrade'])) {
            $ag = $summary['avggrade'];
            $pills .= html_writer::tag(
                'span',
                get_string('summary_avggrade', 'report_individualized')
                    . ' : ' . $ag['avg'] . '/' . $ag['max'],
                ['class' => 'report-individualized-summary-pill report-individualized-summary-grade']
            );
        }

        // Resources consulted.
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
     * Renders metrics in plain text for PDF export.
     *
     * @param  array $summary From compute().
     * @return string         Metrics separated by " | ".
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
