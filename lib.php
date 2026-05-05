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
 * Moodle hooks and fragment callbacks for report_individualized.
 *
 * This file contains only entrypoints required by Moodle core conventions:
 *  - Navigation hooks (profile + course)
 *  - Fragment callback shell (delegates to report_fragment class)
 *
 * All rendering logic lives in classes/output/report_fragment.php.
 * All business logic lives in classes/util/.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// =============================================================================
// HOOKS DE NAVIGATION MOODLE
// =============================================================================

/**
 * Ajoute un lien dans la navigation du profil utilisateur.
 *
 * @param  core_user\output\myprofile\tree $tree          Arbre de navigation du profil.
 * @param  stdClass                        $user          Utilisateur consulté.
 * @param  bool                            $iscurrentuser Vrai si c'est son propre profil.
 * @param  stdClass|null                   $course        Cours en contexte ou null.
 * @return void
 */
function report_individualized_myprofile_navigation(
    core_user\output\myprofile\tree $tree,
    stdClass $user,
    bool $iscurrentuser,
    ?stdClass $course
): void {
    if (!has_capability('report/individualized:view', context_system::instance())) {
        return;
    }
    if ($iscurrentuser) {
        return;
    }
    $url  = new moodle_url('/report/individualized/index.php', ['userid' => $user->id]);
    $node = new core_user\output\myprofile\node(
        'reports',
        'report_individualized',
        get_string('pluginname', 'report_individualized'),
        null,
        $url,
        null
    );
    $tree->add_node($node);
}

/**
 * Ajoute un lien dans la navigation d'un cours.
 *
 * @param  navigation_node $navigation Noeud de navigation du cours.
 * @param  stdClass        $course     Cours courant.
 * @param  context         $context    Contexte du cours.
 * @return void
 */
function report_individualized_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context $context
): void {
    if (!has_capability('report/individualized:view', $context)) {
        return;
    }
    $url = new moodle_url('/report/individualized/index.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('pluginname', 'report_individualized'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        null,
        new pix_icon('i/report', '')
    );
}

// =============================================================================
// FRAGMENT AJAX
// =============================================================================

/**
 * Fragment callback : rend le contenu du rapport via AJAX.
 *
 * Moodle exige que les callbacks de fragment soient définis dans lib.php
 * et suivent la convention pluginname_output_fragment_fragmentname().
 * Ce shell délègue immédiatement à report_fragment::render() pour garder
 * lib.php léger et la logique dans classes/.
 *
 * @param  array $args Paramètres transmis par core/fragment (JS).
 * @return string      HTML du rapport.
 */
function report_individualized_output_fragment_report(array $args): string {
    return \report_individualized\output\report_fragment::render($args);
}
