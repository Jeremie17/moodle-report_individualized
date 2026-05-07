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
 * Workshop utility for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

/**
 * Utility class for workshop-specific report data.
 *
 * Moodle crée deux grade items pour chaque workshop :
 *  - itemnumber 0 : travail remis (évalué par pairs/prof)
 *  - itemnumber 1 : qualité des évaluations par pair faites par l'étudiant
 */
class workshop_util
{
    /**
     * Retourne les éléments note+feedback+dates+complétion d'un atelier.
     *
     * Le setting 'workshop_feedback_type' filtre les lignes :
     *  - 'both'       (défaut) : les deux
     *  - 'submission'           : itemnumber 0 uniquement
     *  - 'assessment'           : itemnumber 1 uniquement
     *
     * Chaque élément retourné :
     *  - label          : nom issu du carnet de notes
     *  - gradestr       : note ou '-'
     *  - feedbackstr    : feedback ou '-'
     *  - closedatestr   : trace de fermeture (action étudiant)
     *  - duedatestr     : date de fermeture configurée par le prof
     *  - completionicon : icône ✓/✗
     *
     * @param  \cm_info $cm        Module workshop.
     * @param  int      $userid    Identifiant étudiant.
     * @param  int      $courseid  Identifiant du cours.
     * @param  bool     $plaintext Vrai = texte brut (export PDF).
     * @return array
     */
    public static function get_workshop_items(
        \cm_info $cm,
        int $userid,
        int $courseid,
        bool $plaintext = false
    ): array {
        global $DB;

        $feedbacktype = get_config('report_individualized', 'workshop_feedback_type');
        if ($feedbacktype === false) {
            $feedbacktype = 'both';
        }

        $workshop = $DB->get_record(
            'workshop',
            ['id' => $cm->instance],
            'submissionend, assessmentend'
        );

        $gradeitems = $DB->get_records(
            'grade_items',
            [
                'itemtype'     => 'mod',
                'itemmodule'   => 'workshop',
                'iteminstance' => $cm->instance,
                'courseid'     => $courseid,
            ],
            'itemnumber ASC'
        );

        $items = [];

        foreach ($gradeitems as $gradeitem) {
            $itemnumber = (int)$gradeitem->itemnumber;

            if ($feedbacktype === 'submission' && $itemnumber !== 0) {
                continue;
            }
            if ($feedbacktype === 'assessment' && $itemnumber !== 1) {
                continue;
            }

            // Grade.
            $gradestr = '-';
            $grade    = $DB->get_record('grade_grades', [
                'itemid' => $gradeitem->id,
                'userid' => $userid,
            ]);
            if ($grade && $grade->finalgrade !== null) {
                $gradestr = round($grade->finalgrade, 2)
                    . ' / ' . round($gradeitem->grademax, 2);
            }

            // Label.
            $label = !empty($gradeitem->itemname)
                ? $gradeitem->itemname
                : format_string($cm->name);

            // Feedback.
            if ($itemnumber === 0) {
                $feedbackstr = self::get_submission_feedback($cm, $userid, $plaintext);
            } else {
                $feedbackstr = self::get_assessment_feedback($cm, $userid, $plaintext);
            }

            // Closing trace, completion and due date.
            if ($itemnumber === 0) {
                $submission = $DB->get_record(
                    'workshop_submissions',
                    ['workshopid' => $cm->instance, 'authorid' => $userid],
                    'timecreated',
                    IGNORE_MISSING
                );
                $closedatestr = ($submission && (int)$submission->timecreated > 0)
                    ? date_util::format_datetime((int)$submission->timecreated, $plaintext)
                    : '-';

                $done = ($submission !== false && $submission !== null);

                // Duedate : submissionend → note prof → soumission étudiant.
                $duedatestr = '-';
                if ($workshop && (int)$workshop->submissionend > 0) {
                    $duedatestr = date_util::format_datetime((int)$workshop->submissionend, $plaintext);
                } else {
                    $gi0 = $DB->get_record('grade_items', [
                        'itemtype'     => 'mod',
                        'itemmodule'   => 'workshop',
                        'iteminstance' => $cm->instance,
                        'courseid'     => $courseid,
                        'itemnumber'   => 0,
                    ], 'id', IGNORE_MISSING);
                    if ($gi0) {
                        $gr0 = $DB->get_record('grade_grades', [
                            'itemid' => $gi0->id,
                            'userid' => $userid,
                        ], 'finalgrade, timemodified', IGNORE_MISSING);
                        if ($gr0 && $gr0->finalgrade !== null && (int)$gr0->timemodified > 0) {
                            $duedatestr = date_util::format_datetime((int)$gr0->timemodified, $plaintext);
                        }
                    }
                    if ($duedatestr === '-' && $submission && (int)$submission->timecreated > 0) {
                        $duedatestr = date_util::format_datetime((int)$submission->timecreated, $plaintext);
                    }
                }
            } else {
                $sql = 'SELECT MAX(wa.timemodified) AS tmod
                          FROM {workshop_assessments} wa
                          JOIN {workshop_submissions} ws ON ws.id = wa.submissionid
                         WHERE wa.reviewerid = :uid
                           AND ws.workshopid = :wid';
                $rec  = $DB->get_record_sql($sql, ['uid' => $userid, 'wid' => $cm->instance]);
                $closedatestr = ($rec && !empty($rec->tmod))
                    ? date_util::format_datetime((int)$rec->tmod, $plaintext)
                    : '-';

                $done = $DB->record_exists_select(
                    'workshop_assessments',
                    'reviewerid = :uid AND submissionid IN (
                        SELECT id FROM {workshop_submissions} WHERE workshopid = :wid
                    )',
                    ['uid' => $userid, 'wid' => $cm->instance]
                );

                // Duedate : assessmentend → note prof → évaluation étudiant.
                $duedatestr = '-';
                if ($workshop && (int)$workshop->assessmentend > 0) {
                    $duedatestr = date_util::format_datetime((int)$workshop->assessmentend, $plaintext);
                } else {
                    $gi1 = $DB->get_record('grade_items', [
                        'itemtype'     => 'mod',
                        'itemmodule'   => 'workshop',
                        'iteminstance' => $cm->instance,
                        'courseid'     => $courseid,
                        'itemnumber'   => 1,
                    ], 'id', IGNORE_MISSING);
                    if ($gi1) {
                        $gr1 = $DB->get_record('grade_grades', [
                            'itemid' => $gi1->id,
                            'userid' => $userid,
                        ], 'finalgrade, timemodified', IGNORE_MISSING);
                        if ($gr1 && $gr1->finalgrade !== null && (int)$gr1->timemodified > 0) {
                            $duedatestr = date_util::format_datetime((int)$gr1->timemodified, $plaintext);
                        }
                    }
                    if ($duedatestr === '-' && $rec && !empty($rec->tmod)) {
                        $duedatestr = date_util::format_datetime((int)$rec->tmod, $plaintext);
                    }
                }
            }

            $items[] = [
                'label'          => $label,
                'gradestr'       => $gradestr,
                'feedbackstr'    => $feedbackstr,
                'closedatestr'   => $closedatestr,
                'duedatestr'     => $duedatestr,
                'completionicon' => completion_util::render_icon($done, $plaintext),
                'isassessment'   => $itemnumber === 1,
            ];
        }

        return $items;
    }

    /**
     * Feedback reçu sur la soumission de l'étudiant dans un atelier.
     *
     * Chemin : workshop_submissions (workshopid + authorid)
     *        → workshop_assessments (submissionid) → feedbackauthor
     *
     * @param  \cm_info $cm        Module workshop.
     * @param  int      $userid    Auteur de la soumission.
     * @param  bool     $plaintext Vrai = texte brut.
     * @return string
     */
    public static function get_submission_feedback(
        \cm_info $cm,
        int $userid,
        bool $plaintext = false
    ): string {
        global $DB;

        $submission = $DB->get_record(
            'workshop_submissions',
            ['workshopid' => $cm->instance, 'authorid' => $userid],
            'id',
            IGNORE_MISSING
        );

        if (!$submission) {
            return '-';
        }

        $assessments = $DB->get_records(
            'workshop_assessments',
            ['submissionid' => $submission->id],
            '',
            'feedbackauthor, feedbackauthorformat'
        );

        $parts = [];
        foreach ($assessments as $a) {
            if (!empty($a->feedbackauthor)) {
                $text    = format_text($a->feedbackauthor, $a->feedbackauthorformat);
                $parts[] = $plaintext ? strip_tags($text) : $text;
            }
        }

        return !empty($parts)
            ? implode($plaintext ? ' | ' : '<br/>', $parts)
            : '-';
    }

    /**
     * Feedback de l'enseignant sur la qualité des évaluations par pair.
     *
     * Chemin : workshop_assessments (reviewerid = userid)
     *          JOIN workshop_submissions ON submissionid → feedbackreviewer
     *
     * @param  \cm_info $cm        Module workshop.
     * @param  int      $userid    Évaluateur.
     * @param  bool     $plaintext Vrai = texte brut.
     * @return string
     */
    public static function get_assessment_feedback(
        \cm_info $cm,
        int $userid,
        bool $plaintext = false
    ): string {
        global $DB;

        $sql = 'SELECT wa.feedbackreviewer, wa.feedbackreviewerformat
                  FROM {workshop_assessments} wa
                  JOIN {workshop_submissions} ws ON ws.id = wa.submissionid
                 WHERE wa.reviewerid = :uid
                   AND ws.workshopid = :wid';

        $records = $DB->get_records_sql($sql, ['uid' => $userid, 'wid' => $cm->instance]);

        $parts = [];
        foreach ($records as $rec) {
            if (!empty($rec->feedbackreviewer)) {
                $text = format_text($rec->feedbackreviewer, $rec->feedbackreviewerformat);

                $parts[] = $plaintext ? strip_tags($text) : $text;
            }
        }

        return !empty($parts)
            ? implode($plaintext ? ' | ' : '<br/>', $parts)
            : '-';
    }
}
