/*
 * OvermindFi event view (Themed/OvermindFi/Events/view2.ctp): tab <-> URL
 * hash, the tab bar's per-tab buttons, the attribute table's row detail
 * drawer, its "Filter" drawer and its selection.
 */
(function () {
    'use strict';

    // Declared by filter_bar.ctp only when a bar carries a mass action the
    // bar itself counts; the attribute table's soft-delete is not one of
    // them, and updateMultiSelectToolbar() reads it unguarded.
    window.selectedItems = window.selectedItems || new Map();

    /* ── tabs ─────────────────────────────────────────────────────────── */

    function tabLink(hash) {
        return document.querySelector('.fi-evw-tabs [data-bs-toggle="tab"][href="' + hash + '"]');
    }
    function showTab(hash) {
        var link = tabLink(hash);
        if (link) { bootstrap.Tab.getOrCreateInstance(link).show(); }
    }
    function currentTabId() {
        var a = document.querySelector('.fi-evw-tabs .nav-view.active[href^="#tab-"]');
        return a ? a.getAttribute('href').replace('#tab-', '') : null;
    }
    function syncTabActions(id) {
        document.querySelectorAll('.fi-evw-tabtools [data-header-tab]').forEach(function (el) {
            el.classList.toggle('d-none', el.getAttribute('data-header-tab') !== id);
        });
    }
    function activateFromHash() {
        if (window.location.hash && tabLink(window.location.hash)) { showTab(window.location.hash); }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.querySelector('.fi-evw')) { return; }
        activateFromHash();
        syncTabActions(currentTabId());
        document.querySelectorAll('.fi-evw-tabs [data-bs-toggle="tab"]').forEach(function (t) {
            t.addEventListener('shown.bs.tab', function (e) {
                var href = e.target.getAttribute('href');
                history.replaceState(null, '', href);
                syncTabActions(href.replace('#tab-', ''));
            });
        });
    });
    window.addEventListener('hashchange', function () {
        activateFromHash();
        syncTabActions(currentTabId());
    });

    /* ── attribute table ──────────────────────────────────────────────── */

    // Clicks that belong to a control inside the row, not to the row.
    function onControl(el) {
        return el.closest('a, button, input, label, select, textarea, [role="button"], .dropdown, .om-hover-enrichment[data-hover-trigger="click"]');
    }

    // Open / close the detail drawer of the row holding `el`.
    window.fiEvwToggleRow = function (el) {
        var row = el.closest('tr.fi-evw-row');
        var detail = row && row.nextElementSibling;
        if (!detail || !detail.classList.contains('fi-evw-detail')) { return; }
        var open = detail.hidden;
        detail.hidden = !open;
        row.classList.toggle('is-open', open);
        row.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    function toggleFilter() {
        showTab('#tab-attributes');
        var container = document.querySelector('#tab-attributes .ajax-tab-content');
        var drawer = container && container.querySelector('.fi-evw-filter');
        if (!drawer) { return; }
        var open = !drawer.classList.contains('is-open');
        drawer.classList.toggle('is-open', open);
        container.__fiFilterOpen = open;
        document.getElementById('fi-evw-filter').setAttribute('aria-expanded', open ? 'true' : 'false');
        var field = open && drawer.querySelector('#filterField');
        if (field) { field.focus(); }
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t.closest) { return; }
        if (t.closest('#fi-evw-filter')) {
            toggleFilter();
            return;
        }
        var expand = t.closest('[data-fi-evw-expand]');
        if (expand) {
            e.preventDefault();
            window.fiEvwToggleRow(expand);
            return;
        }
        var row = t.closest('tr.fi-evw-row');
        // Selecting text in a cell is not a click on the row.
        if (row && !onControl(t) && !String(window.getSelection() || '')) {
            window.fiEvwToggleRow(row);
        }
    });

    // Double-click edits, where the row may be edited.
    document.addEventListener('dblclick', function (e) {
        var row = e.target.closest && e.target.closest('tr.fi-evw-row[data-edit-url]');
        if (!row || onControl(e.target) || typeof openModal !== 'function') { return; }
        window.getSelection && window.getSelection().removeAllRanges();
        openModal(row.getAttribute('data-edit-url'));
    });

    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('tr.fi-evw-row')) {
            e.preventDefault();
            window.fiEvwToggleRow(e.target);
        }
    });

    // Selection: the same bookkeeping as filter_bar.ctp's listener, which
    // is only wired when some bar on the page counts a mass action.
    document.addEventListener('change', function (e) {
        var cb = e.target;
        if (!cb.classList || !cb.classList.contains('item-checkbox') || !cb.closest('.fi-evw-attrs')) { return; }
        if (window.__mispMassActionChangeWired) { return; }
        var id = cb.dataset.itemId;
        if (cb.checked) {
            window.selectedItems.set(id, { id: id, canDelete: cb.dataset.canDelete === '1' });
        } else {
            window.selectedItems.delete(id);
        }
        if (typeof updateMultiSelectToolbar === 'function') { updateMultiSelectToolbar(); }
    });
}());
