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
 * Unit tests for category_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Test case for category_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_individualized\util\category_util
 */
final class category_util_test extends \advanced_testcase {
    /**
     * Sets up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    // Get_category_path tests.

    /**
     * Tests that a zero category ID returns an empty string.
     */
    public function test_get_category_path_zero_returns_empty(): void {
        $this->assertEquals('', category_util::get_category_path(0));
    }

    /**
     * Tests that a negative category ID returns an empty string.
     */
    public function test_get_category_path_negative_returns_empty(): void {
        $this->assertEquals('', category_util::get_category_path(-1));
    }

    /**
     * Tests that a valid category returns a non-empty path string.
     */
    public function test_get_category_path_valid_returns_string(): void {
        $cat    = $this->getDataGenerator()->create_category(['name' => 'Test Category']);
        $result = category_util::get_category_path($cat->id);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('Test Category', $result);
    }

    /**
     * Tests that a child category path includes both parent and child names.
     */
    public function test_get_category_path_child_includes_parent(): void {
        $parent = $this->getDataGenerator()->create_category(['name' => 'Parent']);
        $child  = $this->getDataGenerator()->create_category([
            'name'   => 'Child',
            'parent' => $parent->id,
        ]);

        $result = category_util::get_category_path($child->id);
        $this->assertStringContainsString('Parent', $result);
        $this->assertStringContainsString('Child', $result);
        $this->assertStringContainsString('/', $result);
    }

    /**
     * Tests that depth limit truncates long paths when applied.
     */
    public function test_get_category_path_depth_limit_truncates(): void {
        $parent = $this->getDataGenerator()->create_category(['name' => 'Top']);
        $child  = $this->getDataGenerator()->create_category([
            'name'   => 'Middle',
            'parent' => $parent->id,
        ]);
        $grandchild = $this->getDataGenerator()->create_category([
            'name'   => 'Bottom',
            'parent' => $child->id,
        ]);

        // Full path: "Top / Middle / Bottom".
        set_config('categorydepth', 1, 'report_individualized');
        $result = category_util::get_category_path($grandchild->id, true);

        // With depth=1, only the deepest level should be shown.
        $this->assertStringNotContainsString('Top', $result);
        $this->assertStringContainsString('Bottom', $result);
    }

    // Filter_courses_by_category tests.

    /**
     * Tests that categoryid=0 returns all courses unchanged.
     */
    public function test_filter_courses_zero_returns_all(): void {
        $courses = [
            (object)['id' => 1, 'category' => 5],
            (object)['id' => 2, 'category' => 7],
        ];
        $result = category_util::filter_courses_by_category(0, $courses);
        $this->assertCount(2, $result);
    }

    /**
     * Tests that a non-existent categoryid returns all courses unchanged.
     */
    public function test_filter_courses_invalid_category_returns_all(): void {
        $courses = [
            (object)['id' => 1, 'category' => 5],
            (object)['id' => 2, 'category' => 7],
        ];
        $result = category_util::filter_courses_by_category(99999, $courses);
        $this->assertCount(2, $result);
    }

    /**
     * Tests that filtering by a valid category returns only matching courses.
     */
    public function test_filter_courses_valid_category_filters_correctly(): void {
        $cat1 = $this->getDataGenerator()->create_category();
        $cat2 = $this->getDataGenerator()->create_category();

        $course1 = $this->getDataGenerator()->create_course(['category' => $cat1->id]);
        $course2 = $this->getDataGenerator()->create_course(['category' => $cat2->id]);

        $courses = [$course1, $course2];
        $result  = category_util::filter_courses_by_category($cat1->id, $courses);

        $this->assertCount(1, $result);
        $this->assertEquals($course1->id, $result[0]->id);
    }
}
