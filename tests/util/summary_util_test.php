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
 * Unit tests for summary_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_individualized\util\summary_util
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Test case for summary_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class summary_util_test extends \advanced_testcase {
    /** @var \stdClass Cours de test. */
    private \stdClass $course;

    /** @var \stdClass Étudiant de test. */
    private \stdClass $student;

    /**
     * Initialise un cours et un étudiant réutilisés dans tous les tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator     = $this->getDataGenerator();
        $this->course  = $generator->create_course();
        $this->student = $generator->create_user();
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $this->setUser($this->student);
    }

    /**
     * Retourne le cm_info d'un module à partir de son cmid.
     *
     * @param  int      $cmid Course-module ID.
     * @return \cm_info       Objet cm_info correspondant.
     */
    private function get_cm(int $cmid): \cm_info {
        $modinfo = get_fast_modinfo($this->course, $this->student->id);
        return $modinfo->get_cm($cmid);
    }

    // =========================================================
    // compute() — structure du retour
    // =========================================================

    /**
     * Teste que compute() retourne les 5 clés attendues.
     */
    public function test_compute_returns_expected_keys(): void {
        $result = summary_util::compute([], [], [], $this->student->id);
        $this->assertArrayHasKey('profestimated', $result);
        $this->assertArrayHasKey('studentestimated', $result);
        $this->assertArrayHasKey('completionrate', $result);
        $this->assertArrayHasKey('avggrade', $result);
        $this->assertArrayHasKey('resourcesviewed', $result);
    }

    /**
     * Teste que compute() avec des listes vides retourne null pour les métriques calculées.
     */
    public function test_compute_empty_lists_returns_nulls(): void {
        $result = summary_util::compute([], [], [], $this->student->id);
        $this->assertNull($result['completionrate']);
        $this->assertNull($result['avggrade']);
        $this->assertNull($result['resourcesviewed']);
    }

    // =========================================================
    // compute_completion_rate (via compute)
    // =========================================================

    /**
     * Teste le taux de complétion avec un devoir non soumis → 0/1 (0%).
     */
    public function test_completion_rate_not_submitted(): void {
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $cm     = $this->get_cm($assign->cmid);
        $result = summary_util::compute([], [$cm], [], $this->student->id);

        $cr = $result['completionrate'];
        $this->assertEquals(0, $cr['done']);
        $this->assertEquals(1, $cr['total']);
        $this->assertEquals(0, $cr['pct']);
    }

    /**
     * Teste le taux de complétion avec un devoir soumis → 1/1 (100%).
     */
    public function test_completion_rate_submitted(): void {
        global $DB;
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $DB->insert_record('assign_submission', [
            'assignment'    => $assign->id,
            'userid'        => $this->student->id,
            'status'        => 'submitted',
            'timecreated'   => time(),
            'timemodified'  => time(),
            'attemptnumber' => 0,
            'latest'        => 1,
        ]);
        $cm     = $this->get_cm($assign->cmid);
        $result = summary_util::compute([], [$cm], [], $this->student->id);

        $cr = $result['completionrate'];
        $this->assertEquals(1, $cr['done']);
        $this->assertEquals(1, $cr['total']);
        $this->assertEquals(100, $cr['pct']);
    }

    /**
     * Teste le taux de complétion avec 2 activités dont 1 complétée → 1/2 (50%).
     */
    public function test_completion_rate_partial(): void {
        global $DB;
        $assign1 = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $assign2 = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
        ]);
        $DB->insert_record('assign_submission', [
            'assignment'    => $assign1->id,
            'userid'        => $this->student->id,
            'status'        => 'submitted',
            'timecreated'   => time(),
            'timemodified'  => time(),
            'attemptnumber' => 0,
            'latest'        => 1,
        ]);
        $cm1    = $this->get_cm($assign1->cmid);
        $cm2    = $this->get_cm($assign2->cmid);
        $result = summary_util::compute([], [$cm1, $cm2], [], $this->student->id);

        $cr = $result['completionrate'];
        $this->assertEquals(1, $cr['done']);
        $this->assertEquals(2, $cr['total']);
        $this->assertEquals(50, $cr['pct']);
    }

    // =========================================================
    // compute_resources_viewed (via compute)
    // =========================================================

    /**
     * Teste les ressources vues avec aucune consultation → 0/1.
     */
    public function test_resources_viewed_none_viewed(): void {
        $page   = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
        ]);
        $cm     = $this->get_cm($page->cmid);
        $result = summary_util::compute([$cm], [], [], $this->student->id);

        $rv = $result['resourcesviewed'];
        $this->assertEquals(0, $rv['viewed']);
        $this->assertEquals(1, $rv['total']);
    }

    // =========================================================
    // render_pdf
    // =========================================================

    /**
     * Teste que render_pdf retourne une chaîne vide si aucune métrique.
     */
    public function test_render_pdf_empty_summary(): void {
        $summary = [
            'profestimated'    => '-',
            'studentestimated' => '-',
            'completionrate'   => null,
            'avggrade'         => null,
            'resourcesviewed'  => null,
        ];
        $this->assertEquals('', summary_util::render_pdf($summary));
    }

    /**
     * Teste que render_pdf inclut le taux de complétion formaté.
     */
    public function test_render_pdf_includes_completion_rate(): void {
        $summary = [
            'profestimated'    => '-',
            'studentestimated' => '-',
            'completionrate'   => ['done' => 3, 'total' => 5, 'pct' => 60],
            'avggrade'         => null,
            'resourcesviewed'  => null,
        ];
        $result = summary_util::render_pdf($summary);
        $this->assertStringContainsString('3/5', $result);
        $this->assertStringContainsString('60%', $result);
    }

    /**
     * Teste que render_pdf sépare les métriques par ' | '.
     */
    public function test_render_pdf_separator(): void {
        $summary = [
            'profestimated'    => '30 min',
            'studentestimated' => '-',
            'completionrate'   => ['done' => 1, 'total' => 1, 'pct' => 100],
            'avggrade'         => null,
            'resourcesviewed'  => null,
        ];
        $result = summary_util::render_pdf($summary);
        $this->assertStringContainsString(' | ', $result);
    }
}
