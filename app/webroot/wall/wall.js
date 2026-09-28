/*
 * MISP SOC wall. Each [data-panel] element is filled from
 * GET <base>/dashboards/wall.json?panel=<key> and refreshed every 60 s on
 * its own; a failed refresh keeps the last good content. All data goes
 * into the DOM through textContent / attributes, never innerHTML.
 */
(function () {
    'use strict';

    var REFRESH = 60000;
    var SVGNS = 'http://www.w3.org/2000/svg';
    var LS_URL = 'misp-wall-url';
    var LS_KEY = 'misp-wall-key';

    // ---- settings (localStorage may be unavailable) -----------------------
    function store(name, value) {
        try {
            if (value === undefined) {
                return localStorage.getItem(name) || '';
            }
            if (value === '') {
                localStorage.removeItem(name);
            } else {
                localStorage.setItem(name, value);
            }
        } catch (e) { /* private mode etc.: session only */ }
        return '';
    }
    // The wall lives at <MISP>/wall/, so MISP is the parent directory.
    var defaultUrl = new URL('..', location.href).href.replace(/\/$/, '');
    function baseUrl() {
        return (store(LS_URL) || defaultUrl).replace(/\/+$/, '');
    }

    function request(key, base, apiKey) {
        var opts = {
            headers: {'Accept': 'application/json'},
            credentials: apiKey ? 'omit' : 'same-origin',
            redirect: 'manual',
            cache: 'no-store'
        };
        if (apiKey) {
            opts.headers.Authorization = apiKey;
        }
        return fetch(base + '/dashboards/wall.json?panel=' + encodeURIComponent(key), opts)
            .then(function (res) {
                if (res.type === 'opaqueredirect' || res.status === 401 || res.status === 403) {
                    var err = new Error('auth');
                    err.status = res.status || 401;
                    throw err;
                }
                if (!res.ok) {
                    var e = new Error('http');
                    e.status = res.status;
                    throw e;
                }
                return res.json();
            });
    }

    // ---- DOM helpers ------------------------------------------------------
    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }
        return node;
    }
    function svg(tag, attrs) {
        var node = document.createElementNS(SVGNS, tag);
        Object.keys(attrs || {}).forEach(function (k) {
            node.setAttribute(k, attrs[k]);
        });
        return node;
    }
    function fmt(n) {
        return n === null || n === undefined ? '—' : Number(n).toLocaleString('en-US');
    }
    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }
    function hms(d) {
        return pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds());
    }
    var skew = 0; // server clock - local clock, ms
    function age(ts) {
        var s = Math.max(0, (Date.now() + skew) / 1000 - ts);
        if (s < 60) {
            return 'now';
        }
        if (s < 3600) {
            return Math.floor(s / 60) + 'm';
        }
        return s < 86400 ? Math.floor(s / 3600) + 'h' : Math.floor(s / 86400) + 'd';
    }
    function empty(text) {
        return el('div', 'w-empty', text);
    }
    // Readable text on a tag colour (tag colours are data; validated server-side).
    function chip(tag) {
        var node = el('span', 'w-chip', tag.name);
        if (tag.colour) {
            var hex = tag.colour.length === 4
                ? tag.colour.replace(/^#(.)(.)(.)$/, '#$1$1$2$2$3$3') : tag.colour;
            var r = parseInt(hex.substr(1, 2), 16), g = parseInt(hex.substr(3, 2), 16), b = parseInt(hex.substr(5, 2), 16);
            node.style.background = hex;
            node.style.color = (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#161616' : '#ffffff';
        }
        return node;
    }
    // Hide the trailing rows that do not fit the panel body (re-run on resize).
    function fitRows(body, list) {
        var rows = Array.prototype.slice.call(list.children);
        rows.forEach(function (row) { row.hidden = false; });
        var limit = body.getBoundingClientRect().bottom + 1;
        rows.forEach(function (row) {
            if (row.getBoundingClientRect().bottom > limit) {
                row.hidden = true;
            }
        });
    }
    var fitters = {};
    window.addEventListener('resize', function () {
        Object.keys(fitters).forEach(function (k) { fitters[k](); });
    });

    // ---- radar (inline SVG) ----------------------------------------------
    // series: [{values, stroke, fill, dash}]; scale 'sqrt' keeps one big
    // axis from flattening the rest. Drawn at the measured pixel size (so
    // labels keep the page's type size) by root.draw, once in the DOM and
    // again on resize.
    function radar(axes, series, scale, legend) {
        var root = el('div', 'w-radar');
        var plot = el('div', 'w-radar-plot');
        root.appendChild(plot);
        var key = el('div', 'w-legend');
        legend.forEach(function (item) {
            key.appendChild(el('span', item[1], item[0]));
        });
        root.appendChild(key);
        root.draw = function () {
            var W = plot.clientWidth, H = plot.clientHeight, n = axes.length;
            var fontPx = parseFloat(getComputedStyle(document.documentElement).fontSize) * 0.8;
            var longest = Math.max.apply(null, axes.map(function (a) { return a.length; }));
            var labelW = longest * fontPx * 0.56 + 8;
            var R = Math.max(10, Math.min((W - 2 * labelW) / 2, H / 2 - fontPx * 1.5));
            var cx = W / 2, cy = H / 2;
            var s = svg('svg', {width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, role: 'img'});
            s.style.fontSize = fontPx + 'px';
            var max = 0;
            series.forEach(function (ser) {
                ser.values.forEach(function (v) { max = Math.max(max, Number(v) || 0); });
            });
            var f = scale === 'sqrt' ? Math.sqrt : function (x) { return x; };
            var norm = function (v) { return max > 0 ? f(Math.max(0, Number(v) || 0)) / f(max) : 0; };
            var pt = function (i, r) {
                var a = -Math.PI / 2 + i * 2 * Math.PI / n;
                return [cx + Math.cos(a) * r, cy + Math.sin(a) * r];
            };
            [0.25, 0.5, 0.75, 1].forEach(function (k) {
                var p = [];
                for (var i = 0; i < n; i++) {
                    p.push(pt(i, R * k).join(','));
                }
                s.appendChild(svg('polygon', {points: p.join(' '), 'class': 'grid'}));
            });
            axes.forEach(function (label, i) {
                var e = pt(i, R);
                s.appendChild(svg('line', {x1: cx, y1: cy, x2: e[0], y2: e[1], 'class': 'grid'}));
                var l = pt(i, R + fontPx * 0.7);
                var dx = l[0] - cx, dy = l[1] - cy;
                var t = svg('text', {
                    x: l[0].toFixed(1),
                    y: (l[1] + (dy < -R * 0.5 ? -2 : (dy > R * 0.5 ? fontPx : fontPx * 0.35))).toFixed(1),
                    'text-anchor': Math.abs(dx) < R * 0.2 ? 'middle' : (dx > 0 ? 'start' : 'end')
                });
                t.textContent = label;
                s.appendChild(t);
            });
            series.forEach(function (ser) {
                var p = ser.values.map(function (v, i) {
                    return pt(i, R * norm(v)).map(function (c) { return c.toFixed(1); }).join(',');
                });
                s.appendChild(svg('polygon', {
                    points: p.join(' '), fill: ser.fill || 'none', stroke: ser.stroke,
                    'stroke-width': 1.5, 'stroke-dasharray': ser.dash ? '4 3' : 'none', 'stroke-linejoin': 'round'
                }));
            });
            var title = svg('title', {});
            title.textContent = axes.map(function (a, i) {
                return a + ': ' + series.map(function (ser) { return fmt(ser.values[i]); }).join(' / ');
            }).join('\n');
            s.appendChild(title);
            plot.replaceChildren(s);
        };
        return root;
    }

    function hbars(rows, label, cls) {
        var list = el('div', 'w-hlist' + (cls ? ' ' + cls : ''));
        var max = Math.max.apply(null, [1].concat(rows.map(function (r) { return r.count; })));
        rows.forEach(function (r) {
            var row = el('div', 'w-hrow');
            var name = el('span', '', r[label]);
            name.title = r[label];
            var track = el('span', 'w-track');
            var bar = el('i');
            bar.style.width = (r.count / max * 100).toFixed(1) + '%';
            track.appendChild(bar);
            row.appendChild(name);
            row.appendChild(track);
            row.appendChild(el('span', 'w-num', fmt(r.count)));
            list.appendChild(row);
        });
        return list;
    }

    function table(cls, cols, heads, rows) {
        var t = el('table', cls);
        var cg = el('colgroup');
        cols.forEach(function (c) { cg.appendChild(el('col', c)); });
        t.appendChild(cg);
        var tr = el('tr');
        heads.forEach(function (h) { tr.appendChild(el('th', h[1] || '', h[0])); });
        el('thead').appendChild(tr);
        t.appendChild(tr.parentNode);
        var tb = el('tbody');
        rows.forEach(function (cells) {
            var row = el('tr');
            cells.forEach(function (c) {
                var td = el('td', c[1] || '', c[0]);
                if (c[2]) {
                    td.title = c[2];
                }
                row.appendChild(td);
            });
            tb.appendChild(row);
        });
        t.appendChild(tb);
        return t;
    }

    function rankTable(rows, head) {
        return table('w-rank', ['c-rank', 'c-name', 'c-n'], [['Rank'], [head], ['Events', 'w-num']],
            rows.map(function (r, i) {
                return [[pad(i + 1)], [r.label, '', r.label], [fmt(r.count), 'w-num']];
            }));
    }

    // ---- panel renderers: (data, body) -> node | undefined ------------------
    var TONES = {events: 'rise-bad', high: 'rise-bad', sightings: 'rise-bad', proposals: 'rise-bad', correlations: 'pct'};
    var render = {
        stats: function (d, body) {
            if (d.org) {
                document.getElementById('w-subtitle').textContent = d.org + ' threat picture';
            }
            var frag = document.createDocumentFragment();
            (d.stats || []).forEach(function (s) {
                var cell = el('div', 'w-stat');
                cell.appendChild(el('div', 'w-stat-label', s.label));
                var line = el('div', 'w-stat-line');
                line.appendChild(el('span', 'w-stat-value', fmt(s.value)));
                if (s.of !== undefined && s.of !== null) {
                    line.appendChild(el('span', 'w-stat-of', '/' + fmt(s.of)));
                }
                var tone = TONES[s.key];
                if (tone && s.value !== null && s.previous !== null && s.previous !== undefined && s.value !== s.previous) {
                    var diff = s.value - s.previous;
                    var arrow = diff > 0 ? '↑' : '↓';
                    var badge;
                    if (tone === 'pct') {
                        badge = el('span', 'w-badge', (s.previous > 0 ? Math.round(Math.abs(diff) / s.previous * 100) + '%' : fmt(Math.abs(diff))) + ' ' + arrow);
                    } else {
                        badge = el('span', 'w-badge ' + (diff > 0 ? 'is-bad' : 'is-good'), fmt(Math.abs(diff)) + ' ' + arrow);
                    }
                    badge.title = 'Previous 24h: ' + fmt(s.previous);
                    line.appendChild(badge);
                }
                cell.appendChild(line);
                frag.appendChild(cell);
            });
            body.replaceChildren(frag);
        },
        threats: function (d, body) {
            if (!d.rows.length) {
                return empty('No High or Medium threat events in the last 7 days.');
            }
            var list = el('div', 'w-events');
            d.rows.forEach(function (e) {
                var card = el('div', 'w-event');
                var top = el('div', 'w-event-top');
                var high = e.level === 1;
                top.appendChild(el('span', 'w-level ' + (high ? 'is-high' : 'is-medium'), high ? 'High' : 'Medium'));
                top.appendChild(el('span', 'w-age', age(e.ts)));
                card.appendChild(top);
                var info = el('div', 'w-event-info', e.info);
                info.title = e.info;
                card.appendChild(info);
                var foot = el('div', 'w-event-foot');
                var meta = el('div', 'w-event-meta');
                meta.appendChild(el('span', '', e.org));
                if (e.tlp) {
                    meta.appendChild(chip(e.tlp));
                }
                foot.appendChild(meta);
                foot.appendChild(el('span', 'w-id', '#' + e.id));
                card.appendChild(foot);
                list.appendChild(card);
            });
            return list;
        },
        ingest: function (d) {
            var v = d.values || [];
            if (!v.length) {
                return empty('No attributes in the last 48 hours.');
            }
            var sorted = v.slice().sort(function (a, b) { return a - b; });
            var mid = sorted.length >> 1;
            var median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            var max = Math.max(1, sorted[sorted.length - 1]);
            var root = el('div', 'w-hours');
            var bars = el('div', 'w-hbars');
            v.forEach(function (n, i) {
                var bar = el('i', median > 0 && n > 3 * median ? 'is-spike' : '');
                bar.style.height = (n / max * 100).toFixed(1) + '%';
                var t = new Date((d.start + i * d.bucket) * 1000);
                bar.title = t.toISOString().slice(5, 13).replace('T', ' ') + ':00 UTC · ' + fmt(n);
                bars.appendChild(bar);
            });
            root.appendChild(bars);
            var axis = el('div', 'w-axis');
            axis.appendChild(el('span', '', '48h ago'));
            axis.appendChild(el('span', '', 'now'));
            root.appendChild(axis);
            return root;
        },
        ioc: function (d) {
            return radar(d.axes, [
                {values: d.week_avg, stroke: '#cfcfcf', dash: true},
                {values: d.day, stroke: '#3a9ad9', fill: 'rgba(0,119,179,.35)'}
            ], 'sqrt', [['Last 24h', 'is-blue'], ['7-day average', 'is-grey is-dash']]);
        },
        sources: function (d) {
            return d.rows.length ? hbars(d.rows, 'org') : empty('No IDS attributes in the last 24 hours.');
        },
        indicators: function (d, body) {
            if (!d.rows.length) {
                return empty('No IDS indicators in the last 7 days.');
            }
            var t = table('w-ioc', ['c-type', 'c-value', 'c-event', 'c-age'],
                [['Type'], ['Value'], ['Event'], ['Age', 'w-num']],
                d.rows.map(function (r) {
                    var ev = '#' + r.event_id + (r.org ? ' · ' + r.org : '');
                    return [[r.type, '', r.type], [r.value, 'w-mono', r.value], [ev, '', ev], [age(r.ts), 'w-age']];
                }));
            return t;
        },
        tactics: function (d) {
            return radar(d.axes, [
                {values: d.previous, stroke: '#cfcfcf', dash: true},
                {values: d.current, stroke: '#da4f49', fill: 'rgba(218,79,73,.3)'}
            ], 'linear', [['Last 28 days', 'is-red'], ['Previous 28 days', 'is-grey is-dash']]);
        },
        category: function (d) {
            return d.rows.length ? hbars(d.rows, 'category', 'is-grey') : empty('No attributes in the last 24 hours.');
        },
        tlp: function (d) {
            var levels = [['red', '--tlp-red'], ['amber', '--tlp-amber'], ['green', '--tlp-green'], ['clear', '--tlp-clear']];
            var root = el('div');
            var bar = el('div', 'w-tlpbar');
            var key = el('div', 'w-tlpkey');
            levels.forEach(function (l) {
                var n = Number(d[l[0]]) || 0;
                var seg = el('i');
                seg.style.flex = n + ' 1 0';
                seg.style.background = 'var(' + l[1] + ')';
                seg.title = 'tlp:' + l[0] + ' · ' + fmt(n);
                bar.appendChild(seg);
                var k = el('span', '', 'tlp:' + l[0]);
                k.style.setProperty('--c', 'var(' + l[1] + ')');
                k.appendChild(el('b', '', fmt(n)));
                key.appendChild(k);
            });
            root.appendChild(bar);
            root.appendChild(key);
            return root;
        },
        actors: function (d) {
            return d.rows.length ? rankTable(d.rows, 'Threat actor') : empty('No threat actors tagged in the last 7 days.');
        },
        tags: function (d) {
            return d.rows.length
                ? rankTable(d.rows.map(function (r) { return {label: r.name, count: r.count}; }), 'Tag')
                : empty('No tagged events in the last 24 hours.');
        },
        sync: function (d) {
            if (!d.rows.length) {
                return empty('No enabled feeds or sync servers.');
            }
            var list = el('div', 'w-sync');
            var words = {ok: 'OK', warn: 'No successful run in 7 days', danger: 'Last run failed'};
            d.rows.forEach(function (r) {
                var row = el('div', 'w-syncrow is-' + (words[r.status] ? r.status : 'warn'));
                var name = el('span', '', r.name);
                name.title = (r.kind === 'server' ? 'Sync server' : 'Feed') + ' · ' + (words[r.status] || words.warn);
                row.appendChild(name);
                row.appendChild(el('span', 'w-age', r.last ? age(r.last) : '—'));
                list.appendChild(row);
            });
            return list;
        },
        ticker: function (d, body) {
            var track = body.querySelector('.w-ticker-track');
            var viewport = track.parentNode;
            var set = function () {
                var s = el('span', '', '');
                d.rows.forEach(function (e) {
                    s.appendChild(el('span', 'w-id', '#' + e.id + ' '));
                    s.appendChild(el('span', '', e.info));
                    s.appendChild(el('span', 'w-sep', '·'));
                    s.appendChild(el('span', 'w-org', e.org));
                    s.appendChild(el('span', 'w-sep', '·'));
                });
                return s;
            };
            track.replaceChildren(set());
            if (!d.rows.length) {
                return;
            }
            // Each half must be at least as wide as the viewport, or a short
            // list leaves a gap: repeat the set, then double it so the
            // -50% scroll loops seamlessly.
            var reps = Math.max(1, Math.ceil(viewport.clientWidth / Math.max(1, track.scrollWidth)));
            var frag = document.createDocumentFragment();
            for (var i = 0; i < reps * 2; i++) {
                frag.appendChild(set());
            }
            track.replaceChildren(frag);
            // Constant speed (~60 px/s) whatever the length.
            track.style.setProperty('--dur', Math.max(20, Math.round(track.scrollWidth / 2 / 60)) + 's');
        }
    };

    // ---- status + loop -------------------------------------------------
    var statusEl = document.getElementById('w-status');
    var results = {};   // panel key -> 'ok' | 'fail' | 'auth'
    var lastOk = null;
    function setStatus() {
        var states = Object.keys(results).map(function (k) { return results[k]; });
        var cls, text;
        if (states.indexOf('auth') !== -1) {
            cls = 'is-auth';
            text = 'Set MISP URL and API key';
        } else if (!states.length) {
            cls = 'is-wait';
            text = 'Connecting…';
        } else if (states.indexOf('fail') === -1) {
            cls = 'is-ok';
            text = 'Live · updated ' + hms(lastOk);
        } else {
            cls = states.indexOf('ok') === -1 ? 'is-down' : 'is-warn';
            text = (cls === 'is-down' ? 'Offline' : 'Partly stale')
                + (lastOk ? ' · last update ' + hms(lastOk) : '');
        }
        statusEl.className = 'w-status ' + cls;
        statusEl.lastChild.textContent = text;
    }

    var timers = {};
    var authPrompted = false;
    function load(key) {
        var node = document.querySelector('[data-panel="' + key + '"]');
        var body = node.querySelector('.w-body') || node;
        clearTimeout(timers[key]);
        return request(key, baseUrl(), store(LS_KEY)).then(function (json) {
            skew = json.now * 1000 - Date.now();
            var out = render[key](json.data || {}, body);
            if (out) {
                body.replaceChildren(out);
                if (out.draw) {
                    fitters[key] = out.draw;
                } else if (key === 'threats' || key === 'indicators' || key === 'sync') {
                    var list = out.tagName === 'TABLE' ? out.tBodies[0] : out;
                    fitters[key] = function () { fitRows(body, list); };
                } else {
                    delete fitters[key];
                }
                if (fitters[key]) {
                    fitters[key]();
                }
            }
            node.classList.remove('is-stale');
            results[key] = 'ok';
            lastOk = new Date();
        }, function (err) {
            if (err.status === 404) {
                // Not for this viewer (e.g. feeds and sync is site-admin only).
                node.hidden = true;
                delete results[key];
                return 'stop';
            }
            node.classList.add('is-stale');
            results[key] = err.message === 'auth' ? 'auth' : 'fail';
            if (results[key] === 'auth' && !authPrompted) {
                authPrompted = true;
                openSettings();
            }
        }).then(function (stop) {
            setStatus();
            if (stop !== 'stop') {
                timers[key] = setTimeout(function () { load(key); }, REFRESH);
            }
        });
    }
    function loadAll() {
        fitters = {};
        results = {};
        authPrompted = false;
        Object.keys(render).forEach(function (key, i) {
            var node = document.querySelector('[data-panel="' + key + '"]');
            node.hidden = false;
            setTimeout(function () { load(key); }, i * 120);
        });
    }

    // ---- clock ------------------------------------------------------------
    function tick() {
        var now = new Date(Date.now() + skew);
        document.getElementById('w-clock').textContent = hms(now);
        document.getElementById('w-date').textContent = now.getUTCFullYear() + '.' + pad(now.getUTCMonth() + 1) + '.' + pad(now.getUTCDate());
    }
    tick();
    setInterval(tick, 1000);

    // ---- settings dialog ------------------------------------------------
    var dialog = document.getElementById('w-settings');
    var urlInput = document.getElementById('w-set-url');
    var keyInput = document.getElementById('w-set-key');
    var testOut = document.getElementById('w-test');
    function openSettings() {
        urlInput.value = baseUrl();
        // The stored key is never shown back; empty means "keep it".
        keyInput.value = '';
        keyInput.placeholder = store(LS_KEY)
            ? 'A key is stored — type a new one to replace it'
            : 'Leave empty to use your MISP login session';
        testOut.textContent = '';
        testOut.className = 'w-test';
        if (!dialog.open) {
            dialog.showModal();
        }
    }
    function formUrl() {
        return (urlInput.value.trim() || defaultUrl).replace(/\/+$/, '');
    }
    document.getElementById('w-gear').addEventListener('click', openSettings);
    document.getElementById('w-set-close').addEventListener('click', function () { dialog.close(); });
    document.getElementById('w-set-test').addEventListener('click', function () {
        testOut.className = 'w-test';
        testOut.textContent = 'Testing…';
        request('tlp', formUrl(), keyInput.value.trim() || store(LS_KEY)).then(function () {
            testOut.className = 'w-test is-ok';
            testOut.textContent = 'OK — MISP answered.';
        }, function (err) {
            testOut.className = 'w-test is-bad';
            testOut.textContent = err.status === 401 || err.status === 403
                ? err.status + ' — not authorised: check the API key, or log in to MISP.'
                : (err.status ? 'HTTP ' + err.status : 'Cannot reach MISP at this URL (network or CORS).');
        });
    });
    document.getElementById('w-set-clear').addEventListener('click', function () {
        store(LS_URL, '');
        store(LS_KEY, '');
        openSettings();
        testOut.textContent = 'Cleared.';
        loadAll();
    });
    document.getElementById('w-settings-form').addEventListener('submit', function () {
        var url = formUrl();
        store(LS_URL, url === defaultUrl ? '' : url);
        if (keyInput.value.trim()) {
            store(LS_KEY, keyInput.value.trim());
        }
        keyInput.value = '';
        loadAll();
    });

    loadAll();
})();
