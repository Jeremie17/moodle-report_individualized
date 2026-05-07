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
 * Plugin version and other meta-data.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Version du plugin au format AAAAMMJJXX (XX = incrément du jour).
$plugin->version   = 2026050701;

// Version minimale de Moodle requise (2025100600 = Moodle 5.1).
$plugin->requires  = 2025100600;

// Nom frankenstyle : type_nom — DOIT correspondre exactement au dossier.
$plugin->component = 'report_individualized';

// Numéro de version humainement lisible.
$plugin->release   = '0.4.0';

// Niveau de maturité : MATURITY_ALPHA, MATURITY_BETA, MATURITY_RC, MATURITY_STABLE.
$plugin->maturity  = MATURITY_BETA;
