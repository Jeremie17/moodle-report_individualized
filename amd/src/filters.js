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
 * AMD module for AJAX filter interactions in report_individualized.
 *
 * Intercepts changes on category/student/course selectors and date inputs,
 * then refreshes filter options and report content without a full page reload.
 *  - Filter options  → core/ajax  → external function get_filter_options
 *  - Report content  → core/fragment → lib.php fragment callback 'report'
 *  - PDF button URL  → updated client-side after every filter change
 *
 * @module    report_individualized/filters
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/ajax',
    'core/fragment',
    'core/notification',
], function (Ajax, Fragment, Notification) {

    'use strict';

    /**
     * Rebuild a <select> element with a new list of options.
     * Restores the previously selected value if it still exists in the new list.
     *
     * @param {HTMLSelectElement} select     The select element to update.
     * @param {Array}             options    Array of {id, name} objects.
     * @param {number}            selectedId Value to restore after rebuild.
     */

    function updateSelect(select, options, selectedId) {
        isUpdatingSelects = true;
        select.innerHTML = '';
        options.forEach(function (opt) {
            const el = document.createElement('option');
            el.value = opt.id;
            el.textContent = opt.name;
            if (parseInt(opt.id) === selectedId) {
                el.selected = true;
            }
            select.appendChild(el);
        });
        isUpdatingSelects = false;
    }

    /**
     * Refresh the report content via the Moodle fragment API.
     * Shows a loading state while the request is in flight.
     *
     * @param {number}      contextid Moodle system context ID.
     * @param {HTMLElement} container The div wrapping the report tables.
     * @param {object}      params    {userid, courseid, categoryid, datefrom, dateto}
     */
    const refreshReport = (contextid, container, params) => {
        container.classList.add('report-individualized-loading');

        Fragment.loadFragment('report_individualized', 'report', contextid, params)
            .then((html) => {
                container.innerHTML = html;
                container.classList.remove('report-individualized-loading');
                return;
            })
            .catch(err => {
                container.classList.remove('report-individualized-loading');
                Notification.exception(err);
            });
    };

    /**
     * Refresh the category, student and course selects via the external function.
     *
     * @param {number}            userid          Currently selected user ID.
     * @param {number}            courseid        Currently selected course ID.
     * @param {number}            categoryid      Currently selected category ID.
     * @param {HTMLSelectElement} categorySelect  The category <select>.
     * @param {HTMLSelectElement} userSelect      The student <select>.
     * @param {HTMLSelectElement} courseSelect    The course <select>.
     */
    const refreshFilters = (userid, courseid, categoryid, categorySelect, userSelect, courseSelect) => {
        Ajax.call([{
            methodname: 'report_individualized_get_filter_options',
            args: { userid, courseid, categoryid },
            done: (data) => {
                updateSelect(categorySelect, data.categories, categoryid);
                updateSelect(userSelect, data.users, userid);
                updateSelect(courseSelect, data.courses, courseid);
            },
            fail: Notification.exception,
        }]);
    };

    /**
     * Collect current filter values from the DOM.
     *
     * @param {HTMLSelectElement} categorySelect Category selector.
     * @param {HTMLSelectElement} userSelect     Student selector.
     * @param {HTMLSelectElement} courseSelect   Course selector.
     * @param {HTMLInputElement}  dateFrom       Date-from input.
     * @param {HTMLInputElement}  dateTo         Date-to input.
     * @returns {object} {userid, courseid, categoryid, datefrom, dateto}
     */
    const getParams = (categorySelect, userSelect, courseSelect, dateFrom, dateTo) => ({
        userid: parseInt(userSelect.value) || 0,
        courseid: parseInt(courseSelect.value) || 0,
        categoryid: parseInt(categorySelect.value) || 0,
        datefrom: dateFrom ? dateFrom.value : '',
        dateto: dateTo ? dateTo.value : '',
    });

    /**
     * Met à jour le href du bouton PDF principal (sticky bar) avec les filtres actifs.
     *
     * @param {object} params {userid, courseid, categoryid, datefrom, dateto}
     */
    const updatePdfUrl = (params) => {
        const pdfBtn = document.querySelector(
            '.report-individualized-filters-inner a.btn-outline-dark'
        );
        if (!pdfBtn) {
            return;
        }
        pdfBtn.style.display = '';

        try {
            const url = new URL(pdfBtn.href, window.location.origin);
            url.searchParams.set('userid', params.userid);
            url.searchParams.set('courseid', params.courseid);
            url.searchParams.set('categoryid', params.categoryid);
            url.searchParams.set('datefrom', params.datefrom);
            url.searchParams.set('dateto', params.dateto);
            pdfBtn.href = url.toString();
        } catch (e) {
            // URL parsing failed — leave href unchanged.
        }
    };

    let isUpdatingSelects = false;
    /**
     * Initialise the AJAX filter behaviour.
     * Called by index.php via $PAGE->requires->js_call_amd().
     *
     * @param {number} contextid  Moodle system context ID.
     */
    function init(contextid) {
        const categorySelect = document.getElementById('categoryid');
        const userSelect = document.getElementById('userid');
        const courseSelect = document.getElementById('courseid');
        const dateFrom = document.getElementById('datefrom');
        const dateTo = document.getElementById('dateto');
        const applyBtn = document.querySelector(
            '.report-individualized-filters form button[type="submit"]'
        );
        const resetLink = document.querySelector(
            '.report-individualized-filters a.btn-secondary'
        );
        const container = document.getElementById('report-individualized-content');

        if (!categorySelect || !userSelect || !courseSelect || !container) {
            return;
        }

        // Valeur initiale du filtre catégorie transmise par PHP.

        /**
         * Déclenché à chaque changement de filtre.
         * Rafraîchit le rapport, le bouton PDF, puis les selects.
         */
        function handleChange() {
            if (isUpdatingSelects) {
                return;
            }
            const params = getParams(categorySelect, userSelect, courseSelect, dateFrom, dateTo);
            updatePdfUrl(params);
            refreshReport(contextid, container, params);
            refreshFilters(
                params.userid, params.courseid, params.categoryid,
                categorySelect, userSelect, courseSelect,
                null
            );
        }

        // --- Catégorie change ---
        categorySelect.addEventListener('change', handleChange);

        // --- Étudiant change ---
        userSelect.addEventListener('change', handleChange);

        // --- Cours change ---
        courseSelect.addEventListener('change', handleChange);

        // --- Bouton Appliquer ---
        if (applyBtn) {
            applyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                handleChange();
            });
        }

        // --- Bouton Réinitialiser ---
        if (resetLink) {
            resetLink.addEventListener('click', function (e) {
                e.preventDefault();
                categorySelect.value = '0';
                userSelect.value = '0';
                courseSelect.value = '0';
                if (dateFrom) { dateFrom.value = ''; }
                if (dateTo) { dateTo.value = ''; }
                if (dateFrom) { dateFrom.value = ''; }
                if (dateTo) { dateTo.value = ''; }
                handleChange();
            });
        }
    }
    return { init };
});