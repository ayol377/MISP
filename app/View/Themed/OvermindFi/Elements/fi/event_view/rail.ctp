<?php
/*
 * OvermindFi event view: context rail (stats, correlated events, geography).
 * Everything is read from endpoints the event view already uses, so the
 * ACL/visibility rules are theirs:
 *   IDS count     events/viewAttributes/<id>/toIDS:1/limit:1.json (total)
 *   sightings     events/viewEventSightings/<id>.json
 *   correlations  events/viewRelatedEvents/<id>.json
 * Geography comes from the galaxy clusters already on $event (view2 fetched
 * them through the ACL-aware GalaxyCluster::getClustersByTags()).
 * No sightings delta badge: no endpoint reports one.
 */
$eventId = (int)$event['Event']['id'];
$suffix = $extensionSuffix ?? '';

// Country ISO codes from the country galaxy and the target-information
// (victim) galaxy. Threat-actor meta only names countries, so it is skipped.
$countries = [];
foreach ($event['Galaxy'] ?? [] as $galaxy) {
    $metaKey = [
        'country' => 'ISO',
        'target-information' => 'iso-code',
    ][$galaxy['type'] ?? ''] ?? null;
    if ($metaKey === null) {
        continue;
    }
    foreach ($galaxy['GalaxyCluster'] ?? [] as $cluster) {
        $iso = strtoupper((string)($cluster['meta'][$metaKey][0] ?? ''));
        if (preg_match('/^[A-Z]{2}$/', $iso)) {
            $countries[$iso] = $cluster['value'];
        }
    }
}
?>
<aside class="fi-evw-rail">
    <div class="fi-panel fi-evw-stats">
        <div>
            <div class="fi-evw-stat-label"><?= __('Attributes') ?></div>
            <div class="fi-evw-stat-value">
                <span class="fi-num"><?= number_format((int)($attribute_count ?? 0)) ?></span>
                <span class="fi-evw-stat-max fi-mono" id="fi-evw-ids" hidden>/<?= __('IDS') ?> <span></span></span>
            </div>
        </div>
        <div>
            <div class="fi-evw-stat-label"><?= __('Sightings') ?></div>
            <div class="fi-evw-stat-value">
                <span class="fi-num" id="fi-evw-sightings">&ndash;</span>
                <span class="fi-evw-stat-max fi-mono" id="fi-evw-fp" hidden
                      title="<?= h(__('False positives')) ?>">/<?= __('FP') ?> <span></span></span>
            </div>
        </div>
    </div>

    <div class="fi-panel">
        <div class="fi-panel-title">
            <?= __('Correlated events') ?>
            <span class="fi-panel-note"><?= __('shared values') ?></span>
        </div>
        <table class="fi-evw-rank">
            <thead>
                <tr>
                    <th><?= __('Rank') ?></th>
                    <th><?= __('Event') ?></th>
                    <th class="text-end"><?= __('Shared') ?></th>
                </tr>
            </thead>
            <tbody id="fi-evw-related">
                <tr><td colspan="3" class="fi-faint"><?= __('Loading…') ?></td></tr>
            </tbody>
        </table>
        <button type="button" class="fi-evw-btn fi-evw-btn-text fi-evw-rank-more"
                id="fi-evw-related-more" hidden></button>
    </div>

    <div class="fi-panel">
        <div class="fi-panel-title">
            <?= __('Targeted geography') ?>
            <span class="fi-panel-note"><?= __('country galaxy + victim tags') ?></span>
        </div>
        <?php if (empty($countries)): ?>
            <p class="fi-faint fi-evw-empty mb-0">
                <?= __('No country clusters on this event.') ?>
            </p>
        <?php else: ?>
            <?= $this->element('fi/event_view/geo_map', ['countries' => $countries]) ?>
        <?php endif; ?>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var base = baseurl + '/events/';
    var id = <?= json_encode($eventId) ?>;
    var suffix = <?= json_encode($suffix) ?>;
    var limit = 8;
    var msg = {
        none: <?= json_encode(__('No correlated events.')) ?>,
        fail: <?= json_encode(__('Could not load correlated events.')) ?>,
        all: <?= json_encode(__('Show all (%s)')) ?>,
        less: <?= json_encode(__('Show less')) ?>
    };
    function json(url) {
        return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
    }
    function fmt(n) { return Number(n || 0).toLocaleString(); }
    function reveal(elId, value) {
        var el = document.getElementById(elId);
        el.querySelector('span').textContent = fmt(value);
        el.hidden = false;
    }
    function note(text) {
        list.innerHTML = '<tr><td colspan="3" class="fi-faint">' + escapeHtml(text) + '</td></tr>';
    }

    json(base + 'viewAttributes/' + id + suffix + '/toIDS:1/limit:1.json')
        .then(function (d) { reveal('fi-evw-ids', d.total); })
        .catch(function () {});

    json(base + 'viewEventSightings/' + id + '.json')
        .then(function (d) {
            document.getElementById('fi-evw-sightings').textContent = fmt(d.positive + d.negative);
            if (d.negative) reveal('fi-evw-fp', d.negative);
        })
        .catch(function () {});

    var list = document.getElementById('fi-evw-related');
    var more = document.getElementById('fi-evw-related-more');
    json(base + 'viewRelatedEvents/' + id + '.json')
        .then(function (d) {
            var rows = (d.RelatedEvent || []).map(function (r) { return r.Event; });
            rows.sort(function (a, b) { return b.correlation_count - a.correlation_count; });
            if (!rows.length) {
                note(msg.none);
                return;
            }
            list.textContent = '';
            rows.forEach(function (e, i) {
                var tr = document.createElement('tr');
                if (i >= limit) tr.hidden = true;
                tr.innerHTML = '<td class="fi-mono fi-evw-rank-n">' + String(i + 1).padStart(2, '0') + '</td>'
                    + '<td><a href="' + baseurl + '/events/view2/' + encodeURIComponent(e.id) + '"'
                    + ' title="' + escapeHtml(e.info) + '">'
                    + '#' + escapeHtml(String(e.id)) + ' ' + escapeHtml(e.info) + '</a></td>'
                    + '<td class="text-end">' + fmt(e.correlation_count) + '</td>';
                list.appendChild(tr);
            });
            if (rows.length > limit) {
                var open = false;
                more.textContent = msg.all.replace('%s', rows.length);
                more.hidden = false;
                more.addEventListener('click', function () {
                    open = !open;
                    list.querySelectorAll('tr').forEach(function (tr, i) {
                        tr.hidden = !open && i >= limit;
                    });
                    more.textContent = open ? msg.less : msg.all.replace('%s', rows.length);
                });
            }
        })
        .catch(function () { note(msg.fail); });
});
</script>
