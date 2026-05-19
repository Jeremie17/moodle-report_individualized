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
 * Unit tests for date_util::format_datetime().
 *
 * @package   report_individualized
 * @copyright 2026 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_individualized\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Test case for date_util.
 *
 * @package   report_individualized
 * @copyright 2026 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_individualized\util\date_util
 */
final class date_util_test extends \advanced_testcase {
    /**
     * Tests that a zero timestamp returns '-'.
     */
    public function test_format_datetime_zero_returns_dash(): void {
        $this->assertEquals('-', date_util::format_datetime(0));
    }

    /**
     * Tests that a negative timestamp returns '-'.
     */
    public function test_format_datetime_negative_returns_dash(): void {
        $this->assertEquals('-', date_util::format_datetime(-1));
    }

    /**
     * Tests that plaintext mode returns a string with no HTML tags.
     */
    public function test_format_datetime_plaintext_has_no_html(): void {
        $ts     = mktime(14, 30, 0, 4, 17, 2026);
        $result = date_util::format_datetime($ts, true);
        $this->assertStringNotContainsString('<br>', $result);
        $this->assertStringNotContainsString('<', $result);
    }

    /**
     * Tests that HTML mode contains a br tag.
     */
    public function test_format_datetime_html_contains_br(): void {
        $ts     = mktime(14, 30, 0, 4, 17, 2026);
        $result = date_util::format_datetime($ts, false);
        $this->assertStringContainsString('<br>', $result);
    }

    /**
     * Tests that the time separator is 'h' and not ':'.
     */
    public function test_format_datetime_uses_h_as_time_separator(): void {
        $ts     = mktime(14, 30, 0, 4, 17, 2026);
        $result = date_util::format_datetime($ts, true);
        $this->assertStringContainsString('h', $result);
        $this->assertStringNotContainsString('14:30', $result);
    }

    /**
     * Tests that plaintext mode contains the year.
     */
    public function test_format_datetime_plaintext_contains_year(): void {
        $ts     = mktime(14, 30, 0, 4, 17, 2026);
        $result = date_util::format_datetime($ts, true);
        $this->assertStringContainsString('2026', $result);
    }
}
