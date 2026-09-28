/*
 * OvermindFi events index: the facet column and row double-click.
 *
 * Facets come from events/facetCounts, called with the filters of the URL
 * the page is showing. Each row is a plain link to events/index with that
 * facet's filter toggled, built with parseIndexUrl()/formatIndexUrl() from
 * mispOvermind.js. The filter-draft engine swaps #index-results in place and
 * pushes the new URL, so a change there is the cue to reload the facets.
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

        // The draft summary and these facets say the same thing; keep the
        // panel folded unless the user opens it.
        var panel = root.querySelector('[data-filter-draft-panel].show');
        if (panel) {
            panel.classList.remove('show');
            var toggle = root.querySelector('[data-bs-target="#' + panel.id + '"]');
            if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
        }

        function pieces(value) {
            return value ? String(value).split('|').filter(Boolean) : [];
        }

        function matches(piece, item) {
            var p = String(piece).toLowerCase();
            return (item.match || [item.value]).some(function (m) {
                return String(m).toLowerCase() === p;
            });
        }

        // The URL's value for search<key>, whatever case the key came in.
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
                    count.className = 'fi-ev-facet-count fi-mono';
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
                    groupsEl.innerHTML = '<div class="fi-ev-facet-loading fi-faint"></div>';
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
            // Delegated, so it survives the swap (index_table's own
            // per-table listener does not).
            results.addEventListener('dblclick', function (e) {
                if (e.target.closest('a, button, input, label, .dropdown')) { return; }
                var tr = e.target.closest('.fi-ev-table tr[data-primary-id]');
                if (!tr) { return; }
                var table = tr.closest('table');
                window.location.href = table.dataset.dblclickUrl.replace('%id%', tr.dataset.primaryId);
            });
        }
        window.addEventListener('popstate', function () { setTimeout(load, 0); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
