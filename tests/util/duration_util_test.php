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
 * Unit tests for duration_util::format_duration().
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

/**
 * Test case for duration_util.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_individualized\util\duration_util
 */
final class duration_util_test extends \advanced_testcase {
    /**
     * Tests that a zero duration returns '-'.
     */
    public function test_format_duration_zero_returns_dash(): void {
        $this->assertEquals('-', duration_util::format_duration(0));
    }

    /**
     * Tests that a negative duration returns '-'.
     */
    public function test_format_duration_negative_returns_dash(): void {
        $this->assertEquals('-', duration_util::format_duration(-5));
    }

    /**
     * Tests formatting of minutes only (less than 60 min).
     */
    public function test_format_duration_minutes_only(): void {
        $this->assertEquals('30 min', duration_util::format_duration(30));
        $this->assertEquals('1 min', duration_util::format_duration(1));
        $this->assertEquals('59 min', duration_util::format_duration(59));
    }

    /**
     * Tests formatting of exact hours (no remaining minutes).
     */
    public function test_format_duration_exact_hours(): void {
        $this->assertEquals('1h', duration_util::format_duration(60));
        $this->assertEquals('2h', duration_util::format_duration(120));
        $this->assertEquals('10h', duration_util::format_duration(600));
    }

    /**
     * Tests mixed hours and minutes formatting with zero-padding.
     */
    public function test_format_duration_hours_and_minutes(): void {
        $this->assertEquals('1h30', duration_util::format_duration(90));
        $this->assertEquals('2h05', duration_util::format_duration(125));
        $this->assertEquals('1h01', duration_util::format_duration(61));
    }
}