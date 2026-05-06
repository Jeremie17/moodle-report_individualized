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
 * Capability definitions for report_individualized.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Capability principale : voir le rapport d'un étudiant.
    'report/individualized:view' => [

        // Texte affiché dans la page de gestion des rôles.
        'captype'      => 'read',

        // Niveau de contexte où cette capability s'applique.
        // CONTEXT_SYSTEM = administration globale du site.
        'contextlevel' => CONTEXT_SYSTEM,

        // Héritage : quels archetypes de rôles ont cette capability par défaut.
        'archetypes'   => [
            'manager'        => CAP_ALLOW, // Gestionnaire : oui
            'coursecreator'  => CAP_ALLOW, // Créateur de cours : oui
            'editingteacher' => CAP_ALLOW, // Editing teacher: yes.
            'teacher'        => CAP_ALLOW, // Non-editing teacher: yes.
            'student'        => CAP_PREVENT, // Student: no (cannot view other students).
            'guest'          => CAP_PREVENT, // Guest: no.
        ],
        // clonepermissionsfrom: if the capability does not yet exist on a custom role,
        // it inherits permissions from this core capability,
        // avoiding the need to reconfigure all roles manually.
        'clonepermissionsfrom' => 'moodle/site:viewreports',
    ],
];
