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
 * Pagination: the fragment returns only PERPAGE CMs at a time.
 * A "+" button is appended when data-hasmore="1".
 * Subsequent pages are appended to the container (not replaced).
 * The offset passed to each request = data-nextoffset from the previous page.
 *
 * Student selector: the native <select> is hidden and replaced by a custom
 * searchable input + dropdown list built in vanilla JS.
 *
 * @module    report_individualized/filters
 * @copyright 2026 Ifrass
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
     * Collect current filter values from the DOM.
     *
     * @param {HTMLSelectElement} categorySelect Category selector.
     * @param {HTMLSelectElement} userSelect     Student selector (hidden native select).
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
     * Updates the href of the sticky PDF button with the current filter values.
     *
     * @param {object} params {userid, courseid, categoryid, datefrom, dateto}
     */
    const updatePdfUrl = (params) => {
        const pdfBtn = document.querySelector(
            '.report-individualized-filters a.btn-outline-dark'
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

    /**
     * Refresh the category, student and course selects via the external function.
     *
     * @param {number}            userid         Currently selected user ID.
     * @param {number}            courseid       Currently selected course ID.
     * @param {number}            categoryid     Currently selected category ID.
     * @param {HTMLSelectElement} categorySelect The category <select>.
     * @param {HTMLSelectElement} userSelect     The student <select>.
     * @param {HTMLSelectElement} courseSelect   The course <select>.
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
     * Attach a "+" load-more button if the fragment signals more pages exist.
     * On click, loads the next page and appends it to the container.
     *
     * @param {number}      contextid  Moodle system context ID.
     * @param {HTMLElement} container  The report content container.
     * @param {object}      baseParams Filter params used for the current page.
     */
    function attachLoadMore(contextid, container, baseParams) {
        const wrapper = container.querySelector('.report-individualized-paginated');
        if (!wrapper || wrapper.getAttribute('data-hasmore') !== '1') {
            return;
        }
        const nextoffset = parseInt(wrapper.getAttribute('data-nextoffset')) || 0;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-primary mt-3 mb-4 d-block mx-auto report-individualized-loadmore';
        btn.textContent = '+';
        container.appendChild(btn);

        btn.addEventListener('click', function () {
            btn.disabled = true;

            const nextParams = Object.assign({}, baseParams, { offset: String(nextoffset) });

            Fragment.loadFragment('report_individualized', 'report', contextid, nextParams)
                .then(function (html) {
                    btn.remove();

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    const newWrapper = tempDiv.querySelector('.report-individualized-paginated');
                    const source = newWrapper || tempDiv;

                    while (source.firstChild) {
                        container.appendChild(source.firstChild);
                    }

                    if (newWrapper && newWrapper.getAttribute('data-hasmore') === '1') {
                        wrapper.setAttribute('data-hasmore', '1');
                        wrapper.setAttribute('data-totalcms', newWrapper.getAttribute('data-totalcms'));
                        wrapper.setAttribute('data-nextoffset', newWrapper.getAttribute('data-nextoffset'));
                        attachLoadMore(contextid, container, baseParams);
                    }

                    return;
                })
                .catch(function (err) {
                    btn.disabled = false;
                    btn.textContent = '+';
                    Notification.exception(err);
                });
        });
    }

    /**
     * Load report for the given params, ignoring stale responses.
     * Replaces container content and attaches a load-more button when needed.
     *
     * @param {number}      contextid Moodle system context ID.
     * @param {HTMLElement} container The div wrapping the report tables.
     * @param {object}      params    {userid, courseid, categoryid, datefrom, dateto}
     * @param {number}      requestId Snapshot of currentRequestId at call time.
     */
    function loadReport(contextid, container, params, requestId, loadingText) {
    container.classList.add('report-individualized-loading');

    const spinnerWrapper = document.createElement('div');
    spinnerWrapper.className = 'report-individualized-spinner d-flex flex-column align-items-center py-5';

    const spinner = document.createElement('div');
    spinner.className = 'spinner-border text-primary mb-3';
    spinner.setAttribute('role', 'status');
    spinner.setAttribute('aria-hidden', 'true');

    const spinnerText = document.createElement('p');
    spinnerText.className = 'text-muted';
    spinnerText.textContent = loadingText || '';

    spinnerWrapper.appendChild(spinner);
    spinnerWrapper.appendChild(spinnerText);
    container.innerHTML = '';
    container.appendChild(spinnerWrapper);

        Fragment.loadFragment('report_individualized', 'report', contextid, params)
            .then(function (html) {
                if (requestId !== currentRequestId) {
                    return;
                }
                container.innerHTML = html;
                container.classList.remove('report-individualized-loading');
                attachLoadMore(contextid, container, params);
                return;
            })
            .catch(function (err) {
                if (requestId !== currentRequestId) {
                    return;
                }
                container.classList.remove('report-individualized-loading');
                Notification.exception(err);
            });
    }

    /**
     * Replace the native student <select> with a custom searchable combobox.
     * The native select is hidden but stays in the DOM as the source of truth.
     * Typing in the input filters the visible list in real time.
     * Clicking an item sets the native select value and triggers handleChange.
     *
     * @param {HTMLSelectElement} userSelect    The native student select element.
     * @param {string}            placeholder   Translated placeholder string from PHP.
     * @param {Function}          handleChange  Callback to fire on selection.
     */
    function initUserSearch(userSelect, placeholder, handleChange) {
        userSelect.style.display = 'none';

        const allOptions = Array.from(userSelect.options).map(function (o) {
            return { value: o.value, text: o.textContent };
        });

        const wrapper = document.createElement('div');
        wrapper.className = 'report-individualized-user-search-wrapper position-relative me-3';

        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = placeholder;
        searchInput.className = 'form-control report-individualized-user-search';
        searchInput.autocomplete = 'off';

        const listbox = document.createElement('ul');
        listbox.className = 'report-individualized-user-listbox list-unstyled border rounded bg-white position-absolute w-100 mb-0 d-none';

        wrapper.appendChild(searchInput);
        wrapper.appendChild(listbox);
        userSelect.parentNode.insertBefore(wrapper, userSelect);

        /**
         * Rebuild the visible listbox, filtering by query string.
         *
         * @param {string} query Search string entered by the user.
         */
        function renderList(query) {
            listbox.innerHTML = '';
            allOptions.forEach(function (opt) {
                if (query && opt.value !== '0' && !opt.text.toLowerCase().includes(query.toLowerCase())) {
                    return;
                }
                const li = document.createElement('li');
                li.textContent = opt.text;
                li.dataset.value = opt.value;
                li.className = 'px-3 py-1 report-individualized-user-option';
                li.addEventListener('mouseenter', function () {
                    li.classList.add('report-individualized-user-option--hover');
                });
                li.addEventListener('mouseleave', function () {
                    li.classList.remove('report-individualized-user-option--hover');
                });
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    searchInput.value = opt.value === '0' ? '' : opt.text;
                    userSelect.value = opt.value;
                    listbox.classList.add('d-none');
                    handleChange();
                });
                listbox.appendChild(li);
            });
        }

        searchInput.addEventListener('focus', function () {
            renderList(searchInput.value);
            listbox.classList.remove('d-none');
        });

        searchInput.addEventListener('input', function () {
            renderList(searchInput.value);
            listbox.classList.remove('d-none');
        });

        searchInput.addEventListener('blur', function () {
            listbox.classList.add('d-none');
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            const first = listbox.querySelector('li');
            if (!first) {
                return;
            }
            searchInput.value = first.dataset.value === '0' ? '' : first.textContent;
            userSelect.value = first.dataset.value;
            listbox.classList.add('d-none');
            handleChange();
        });
    }

    let isUpdatingSelects = false;
    let currentRequestId = 0;
    let debounceTimer = null;

    /**
     * Initialise the AJAX filter behaviour.
     * Called by index.php via $PAGE->requires->js_call_amd().
     *
     * @param {number} contextid   Moodle system context ID.
     * @param {string} placeholder Translated placeholder for the student search input.
     */
    function init(contextid, placeholder, loadingText) {
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

        /**
         * Fired on every filter change after a 300 ms debounce.
         * Increments the request ID to discard stale responses.
         */
        function handleChange() {
            if (isUpdatingSelects) {
                return;
            }
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                currentRequestId++;
                const requestId = currentRequestId;
                const params = getParams(categorySelect, userSelect, courseSelect, dateFrom, dateTo);
                updatePdfUrl(params);
                loadReport(contextid, container, params, requestId, loadingText);
                refreshFilters(
                    params.userid, params.courseid, params.categoryid,
                    categorySelect, userSelect, courseSelect
                );
            }, 300);
        }

        initUserSearch(userSelect, placeholder, handleChange);

        categorySelect.addEventListener('change', handleChange);
        courseSelect.addEventListener('change', handleChange);

        // Auto-trigger on page load only when at least one filter is already set.
        // When no filter is active, the PHP-rendered placeholder stays in the container.
        const initialParams = getParams(categorySelect, userSelect, courseSelect, dateFrom, dateTo);
        const hasFilter = initialParams.userid > 0 || initialParams.courseid > 0 ||
            initialParams.categoryid > 0 || initialParams.datefrom !== '' || initialParams.dateto !== '';
        if (hasFilter) {
            currentRequestId++;
            const initRequestId = currentRequestId;
            updatePdfUrl(initialParams);
            loadReport(contextid, container, initialParams, initRequestId, loadingText);
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                handleChange();
            });
        }

        if (resetLink) {
            resetLink.addEventListener('click', function (e) {
                e.preventDefault();
                categorySelect.value = '0';
                userSelect.value = '0';
                const userSearchInput = document.querySelector('.report-individualized-user-search');
                if (userSearchInput) {
                    userSearchInput.value = '';
                }
                courseSelect.value = '0';
                if (dateFrom) { dateFrom.value = ''; }
                if (dateTo) { dateTo.value = ''; }
                container.innerHTML = '';
            });
        }
    }
    return { init };
});
