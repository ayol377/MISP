/*
 * OvermindFi events index (Themed/OvermindFi/Events/index.ctp).
 *
 *  - facets: loaded from events/facetCounts with the filters of the URL the
 *    page shows; each row is a link to events/index with that facet
 *    toggled (parseIndexUrl()/formatIndexUrl() from mispOvermind.js).
 *  - search box + "More filters": upstream's draft engine,
 *    initScaffoldFilterDraft(), with the config the template embeds. It
 *    swaps #index-results in place and pushes the URL, which is the cue to
 *    reload the facets.
 *  - the Period chip's custom range, the column picker, row double-click.
 */
(function () {
    'use strict';

    function init() {
        var root = document.getElementById('fiEventsIndex');
        if (!root || typeof parseIndexUrl !== 'function') { return; }
        var groupsEl = root.querySelector('.fi-ev-facet-groups');
        var results = document.getElementById('index-results');
        var itemPath = root.dataset.itemPath;
        var indexUrl = root.dataset.indexUrl;
        var facetUrl = root.dataset.facetUrl;
        var loadedFor = null;
        var inFlight = null;

        /* ── filter draft (search box + More filters panel) ─────────── */
        // After every other DOMContentLoaded handler, so the panel's
        // selects are TomSelects already (initTopbarFilterSelects()).
        setTimeout(function () {
            var cfgEl = document.getElementById('fiEvDraftConfig');
            if (!cfgEl || typeof initScaffoldFilterDraft !== 'function') { return; }
            var cfg = JSON.parse(cfgEl.textContent);
            cfg.scope = document;
            cfg.ajaxContainer = null;
            initScaffoldFilterDraft(root, cfg);
        }, 0);

        /* ── facets ─────────────────────────────────────────────────── */
        function pieces(value) {
            return value ? String(value).split('|').filter(Boolean) : [];
        }

        function matches(piece, item) {
            var p = String(piece).toLowerCase();
            return (item.match || [item.value]).some(function (m) {
                return String(m).toLowerCase() === p;
            });
        }

        // The URL's key for search<key>, whatever case it came in.
        function namedKey(named, key) {
            var wanted = 'search' + key;
            var found = Object.keys(named).find(function (k) { return k.toLowerCase() === wanted; });
            return found || wanted;
        }

        function toggled(parts, item) {
            var named = Object.assign({}, parts.named);
            delete named.page;
            var key = namedKey(named, item.key);
            var current = pieces(named[key]);
            var at = current.findIndex(function (p) { return matches(p, item); });
            if (at !== -1) {
                current.splice(at, 1);
            } else if (item.multi) {
                current.push(item.value);
            } else {
                current = [item.value];
            }
            if (current.length) { named[key] = current.join('|'); } else { delete named[key]; }
            return formatIndexUrl(indexUrl, { positional: parts.positional, named: named });
        }

        function isActive(parts, item) {
            return pieces(parts.named[namedKey(parts.named, item.key)])
                .some(function (p) { return matches(p, item); });
        }

        function render(data, parts) {
            groupsEl.textContent = '';
            (data.groups || []).forEach(function (group) {
                var box = document.createElement('div');
                box.className = 'fi-ev-facet';
                var label = document.createElement('div');
                label.className = 'fi-ev-facet-label';
                label.textContent = group.label;
                box.appendChild(label);
                group.items.forEach(function (item) {
                    var active = isActive(parts, item);
                    var row = document.createElement('a');
                    row.className = 'fi-ev-facet-row' + (active ? ' is-active' : '')
                        + (item.count === 0 && !active ? ' is-empty' : '');
                    row.href = toggled(parts, item);
                    row.setAttribute('aria-pressed', active ? 'true' : 'false');
                    var dot = document.createElement('span');
                    dot.className = 'fi-ev-dot';
                    if (item.color) { dot.style.backgroundColor = item.color; }
                    var name = document.createElement('span');
                    name.className = 'fi-ev-facet-name';
                    name.textContent = item.label;
                    name.title = item.label;
                    var count = document.createElement('span');
                    count.className = 'fi-ev-facet-count';
                    count.textContent = Number(item.count).toLocaleString('en-US');
                    row.append(dot, name, count);
                    box.appendChild(row);
                });
                groupsEl.appendChild(box);
            });
        }

        function load() {
            var here = window.location.pathname;
            if (here === loadedFor) { return; }
            loadedFor = here;
            var parts = parseIndexUrl(here, itemPath);
            var named = {};
            Object.keys(parts.named).forEach(function (k) {
                if (k.toLowerCase().indexOf('search') === 0) { named[k] = parts.named[k]; }
            });
            if (inFlight) { inFlight.abort(); }
            inFlight = new AbortController();
            groupsEl.setAttribute('aria-busy', 'true');
            groupsEl.classList.add('is-loading');
            fetch(formatIndexUrl(facetUrl, { named: named }), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: inFlight.signal,
            })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    return r.json();
                })
                .then(function (data) { render(data, parts); })
                .catch(function (error) {
                    if (error.name === 'AbortError') { return; }
                    loadedFor = null;
                    groupsEl.innerHTML = '<div class="fi-ev-facet-loading"></div>';
                    groupsEl.firstChild.textContent = 'Filters could not be loaded.';
                })
                .finally(function () {
                    groupsEl.removeAttribute('aria-busy');
                    groupsEl.classList.remove('is-loading');
                });
        }

        load();
        if (results) {
            // Also fires for the busy overlay; load() skips an unchanged URL.
            new MutationObserver(load).observe(results, { childList: true });
            // Delegated, so it survives the ajax swap.
            results.addEventListener('dblclick', function (e) {
                if (e.target.closest('a, button, input, label, .dropdown')) { return; }
                var tr = e.target.closest('.fi-ev-table tr[data-primary-id]');
                if (!tr) { return; }
                var table = tr.closest('table');
                window.location.href = table.dataset.dblclickUrl.replace('%id%', tr.dataset.primaryId);
            });
        }
        window.addEventListener('popstate', function () { setTimeout(load, 0); });

        /* ── Period chip: custom date range ─────────────────────────── */
        root.addEventListener('submit', function (e) {
            var form = e.target.closest('[data-fi-ev-period]');
            if (!form) { return; }
            e.preventDefault();
            var parts = parseIndexUrl(window.location.pathname, itemPath);
            var named = parts.named;
            delete named.page;
            ['datefrom', 'dateuntil'].forEach(function (k) {
                delete named[namedKey(named, k)];
                var value = form.elements[k].value;
                if (value) { named['search' + k] = value; }
            });
            window.location.href = formatIndexUrl(indexUrl, { positional: parts.positional, named: named });
        });

        /* ── column picker (the event_index_hide_columns user setting) ─ */
        root.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-fi-ev-column]');
            if (!btn) { return; }
            btn.disabled = true;
            fetch(root.dataset.columnUrl + encodeURIComponent(btn.dataset.fiEvColumn), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': typeof getCsrfToken === 'function' ? getCsrfToken() : (window.csrfToken || ''),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    window.location.reload();
                })
                .catch(function () {
                    btn.disabled = false;
                    if (typeof showToast === 'function') { showToast('The columns could not be changed.', 'danger'); }
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
