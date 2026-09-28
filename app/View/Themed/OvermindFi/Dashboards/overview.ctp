<?php
/*
 * OvermindFi Overview (mockup 1a), rendered by
 * DashboardsController::__fiOverview() at /dashboards. The customisable
 * dashboard v2 board lives at /dashboards?board=1.
 *
 * This is the shell: header, filters and empty panels. Each panel body
 * carries data-fi-panel=<url> and is filled from
 * Dashboards/overview_panel.ctp by the loader at the bottom.
 *
 * Vars: $filters (range, scope, distribution, published — validated),
 * $ranges (list of range keys), $panels (panel keys the viewer may see).
 */
$base = $baseurl . '/dashboards';
$defaults = ['range' => '30d', 'scope' => 'all', 'distribution' => '', 'published' => ''];
$url = function (array $change = []) use ($filters, $defaults, $base) {
    $query = [];
    foreach (array_merge($filters, $change) as $key => $value) {
        if ($value !== ($defaults[$key] ?? '')) {
            $query[$key] = $value;
        }
    }
    return $base . (empty($query) ? '' : '?' . http_build_query($query));
};
$rangeLabel = [
    '24h' => __('last 24 hours'),
    '7d' => __('last 7 days'),
    '30d' => __('last 30 days'),
    '90d' => __('last 90 days'),
][$filters['range']];
$selects = [
    'scope' => [__('Scope'), [
        'all' => __('All communities'),
        'org' => __('My org'),
    ]],
    'distribution' => [__('Distribution'), [
        '' => __('Any'),
        '0' => __('Your organisation only'),
        '1' => __('This community only'),
        '2' => __('Connected communities'),
        '3' => __('All communities'),
        '4' => __('Sharing group'),
    ]],
    'published' => [__('Published'), [
        '1' => __('Yes'),
        '0' => __('No'),
        '' => __('Any'),
    ]],
];
$panel = function ($key, $title, $note = '', $class = '') use ($panels, $url) {
    if (!in_array($key, $panels, true)) {
        return '';
    }
    $head = '';
    if ($title !== '') {
        $head = '<div class="fi-panel-title">' . h($title)
            . ($note !== '' ? '<span class="fi-panel-note">' . h($note) . '</span>' : '')
            . '</div>';
    }
    return '<section class="fi-panel fi-ov-panel ' . h($class) . '">' . $head
        . '<div class="fi-ov-body" data-fi-panel="' . h($url(['panel' => $key])) . '" aria-busy="true">'
        . '<div class="fi-ov-loading">' . h(__('Loading…')) . '</div></div></section>';
};
?>
<div class="fi-ov">
    <header class="fi-ov-head">
        <div class="fi-ov-head-left">
            <h1 class="fi-ov-title"><?= __('Overview') ?></h1>
            <form class="fi-ov-filters" method="get" action="<?= h($base) ?>">
<?php if ($filters['range'] !== $defaults['range']): ?>
                <input type="hidden" name="range" value="<?= h($filters['range']) ?>">
<?php endif; ?>
<?php foreach ($selects as $name => $select): ?>
                <label class="fi-ov-filter">
                    <span><?= h($select[0]) ?></span>
                    <select name="<?= h($name) ?>">
<?php foreach ($select[1] as $value => $label): ?>
                        <option value="<?= h($value) ?>"<?= (string)$value === $filters[$name] ? ' selected' : '' ?>><?= h($label) ?></option>
<?php endforeach; ?>
                    </select>
                </label>
<?php endforeach; ?>
                <noscript><button type="submit" class="fi-ov-apply"><?= __('Apply') ?></button></noscript>
            </form>
        </div>
        <div class="fi-ov-head-right">
            <a class="fi-ov-customize" href="<?= h($base . '?board=1') ?>"><?= __('Customize dashboard') ?></a>
            <nav class="fi-ov-ranges" aria-label="<?= h(__('Time range')) ?>">
<?php foreach ($ranges as $range): ?>
                <a href="<?= h($url(['range' => $range])) ?>"<?= $range === $filters['range'] ? ' class="is-active" aria-current="true"' : '' ?>><?= h($range) ?></a>
<?php endforeach; ?>
            </nav>
        </div>
    </header>

    <?= $panel('stats', '', '', 'fi-ov-statstrip') ?>

    <div class="fi-ov-grid-main">
        <?= $panel('origin', __('Activity by attributed origin'), __('threat-actor cluster meta.country · %s', $filters['range']), 'fi-ov-map') ?>
        <div class="fi-ov-side">
            <?= $panel('ingest', __('Attribute ingest'), ($filters['range'] === '24h' ? __('per hour') : __('per day')) . ' · ' . __('forecast dashed')) ?>
            <?= $panel('actors', __('Top threat actors'), '', 'fi-ov-rankpanel') ?>
        </div>
    </div>

    <div class="fi-ov-grid-bottom">
        <?= $panel('tags', __('Top tags'), '', 'fi-ov-rankpanel') ?>
        <?= $panel('sync', __('Feeds and sync'), __('last pull')) ?>
        <?= $panel('tactics', __('ATT&CK tactics observed'), $rangeLabel) ?>
    </div>
</div>
<script>
(function () {
    // Filters apply on change; the range pills are plain links.
    var form = document.querySelector('.fi-ov-filters');
    form.addEventListener('change', function () { form.submit(); });

    document.querySelectorAll('[data-fi-panel]').forEach(function (body) {
        fetch(body.getAttribute('data-fi-panel'), {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(response.status);
            }
            return response.text();
        }).then(function (html) {
            body.innerHTML = html;
        }).catch(function () {
            body.innerHTML = '<div class="fi-ov-empty"></div>';
            body.firstChild.textContent = <?= json_encode(__('Could not load this panel.')) ?>;
        }).then(function () {
            body.removeAttribute('aria-busy');
        });
    });
})();
</script>
