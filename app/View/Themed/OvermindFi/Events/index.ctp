<?php
/*
 * OvermindFi events index (mockup 1c): facet column, one search box, compact
 * filter chips, dense table. Own markup; the engines are upstream's:
 *
 *  - search box + "More filters" panel -> initScaffoldFilterDraft()
 *    (mispOvermind.js), wired in js/fi/events-index.js with the config
 *    upstream's filter_bar.ctp would pass; the panel is upstream's
 *    filter_panel element.
 *  - mass select -> .item-checkbox / #select_all / #multiSelectToolbar,
 *    read by mispOvermind.js (updateMultiSelectToolbar, multiSelectItems).
 *  - facets -> events/facetCounts (js/fi/events-index.js).
 *
 * Everything an ajax apply replaces lives in #index-results, plus the two
 * nodes listed in the draft's `swap` (.fi-ev-chips, .fi-ev-range).
 * No card view: narrow screens get a stacked row layout from the CSS.
 */
App::uses('IndexFilterDraft', 'Tools');

$this->set('hideHeaderSection', true);
$this->set('additionalJs', ['fi/events-index']);

$canImport = $this->Acl->canAccess('events', 'importEvent');
$canPickTemplate = (
    $this->Acl->canAccess('eventTemplates', 'index')
    && $this->Acl->canAccess('eventTemplates', 'instantiate')
);
$canAdd = $this->Acl->canAccess('events', 'add');
$canExport = $this->Acl->canAccess('events', 'export');
$canPickColumns = $this->Acl->canAccess('userSettings', 'eventIndexColumnToggle');
if ($canPickTemplate) {
    echo $this->element('eventTemplates/templatePickerModal');
}

$columns = $columns ?? [];
$show = [
    'tags' => in_array('tags', $columns, true),
    'clusters' => in_array('clusters', $columns, true),
    'attr' => in_array('attribute_count', $columns, true),
    'corr' => in_array('correlations', $columns, true),
];
$viewUrl = $baseurl . '/events/view2/%id%';

/* ── the URL state ─────────────────────────────────────────────────────
 * Named args, keyed lower-case (the controller reads them that way).
 * $urlWith() is events/index with some filters set/dropped and the rest
 * (sort included) kept; a new filter always goes back to page 1. */
$named = [];
foreach (($this->request->params['named'] ?? []) as $key => $value) {
    $named[strtolower($key)] = $value;
}
$get = function ($key) use ($named) {
    $value = $named[$key] ?? null;
    return is_array($value) ? implode('|', $value) : $value;
};
$urlWith = function (array $set) use ($named) {
    $args = $named;
    unset($args['page']);
    foreach ($set as $key => $value) {
        if ($value === null || $value === '') {
            unset($args[$key]);
        } else {
            $args[$key] = $value;
        }
    }
    return $this->Html->url(['controller' => 'events', 'action' => 'index'] + $args);
};
$pieces = function ($value) {
    return $value === null || $value === '' ? [] : explode('|', (string)$value);
};

/* ── filter chips ──────────────────────────────────────────────────── */
$chips = [];

// TLP: the tlp:* part of searchtag, any other tags in it are kept.
$tagPieces = $pieces($get('searchtag'));
$tlpOn = array_values(array_filter($tagPieces, function ($p) {
    return stripos($p, 'tlp:') === 0;
}));
$otherTags = array_values(array_diff($tagPieces, $tlpOn));
$tlpOptions = [[
    'label' => __('Any'),
    'url' => $urlWith(['searchtag' => implode('|', $otherTags)]),
    'active' => !$tlpOn,
]];
foreach (['red', 'amber+strict', 'amber', 'green', 'clear', 'white'] as $level) {
    $tlpOptions[] = [
        'label' => $level,
        'url' => $urlWith(['searchtag' => implode('|', array_merge($otherTags, ['tlp:' . $level]))]),
        'active' => in_array('tlp:' . $level, array_map('strtolower', $tlpOn), true),
    ];
}
$chips[] = [
    'label' => __('TLP'),
    'value' => $tlpOn ? implode(', ', array_map(function ($p) {
        return substr($p, 4);
    }, $tlpOn)) : __('Any'),
    'set' => (bool)$tlpOn,
    'options' => $tlpOptions,
];

// Threat level; the facet column does multi-select, this picks one.
$threatNames = [1 => __('High'), 2 => __('Medium'), 3 => __('Low'), 4 => __('Undefined')];
$threatOn = $pieces($get('searchthreatlevel'));
$threatOptions = [['label' => __('Any'), 'url' => $urlWith(['searchthreatlevel' => null]), 'active' => !$threatOn]];
foreach ($threatNames as $threatId => $name) {
    $threatOptions[] = [
        'label' => $name,
        'url' => $urlWith(['searchthreatlevel' => (string)$threatId]),
        'active' => $threatOn === [(string)$threatId],
    ];
}
$chips[] = [
    'label' => __('Threat'),
    'value' => $threatOn ? implode(', ', array_map(function ($p) use ($threatNames) {
        $not = strpos($p, '!') === 0;
        $bare = ltrim($p, '!');
        return ($not ? '!' : '') . ($threatNames[(int)$bare] ?? $bare);
    }, $threatOn)) : __('Any'),
    'set' => (bool)$threatOn,
    'options' => $threatOptions,
];

// Period: presets on the event date, plus a custom range.
$dateFrom = (string)$get('searchdatefrom');
$dateUntil = (string)$get('searchdateuntil');
$periodValue = __('Any time');
$periodOptions = [[
    'label' => __('Any time'),
    'url' => $urlWith(['searchdatefrom' => null, 'searchdateuntil' => null]),
    'active' => $dateFrom === '' && $dateUntil === '',
]];
$presets = [
    '-7 days' => __('Last 7 days'),
    '-30 days' => __('Last 30 days'),
    '-90 days' => __('Last 90 days'),
    '-1 year' => __('Last 12 months'),
];
foreach ($presets as $delta => $label) {
    $from = date('Y-m-d', strtotime($delta));
    $active = $dateFrom === $from && $dateUntil === '';
    if ($active) {
        $periodValue = $label;
    }
    $periodOptions[] = [
        'label' => $label,
        'url' => $urlWith(['searchdatefrom' => $from, 'searchdateuntil' => null]),
        'active' => $active,
    ];
}
if (($dateFrom !== '' || $dateUntil !== '') && $periodValue === __('Any time')) {
    $periodValue = $dateUntil === ''
        ? __('Since %s', $dateFrom)
        : ($dateFrom === '' ? __('Until %s', $dateUntil) : $dateFrom . ' – ' . $dateUntil);
}
$chips[] = [
    'label' => __('Period'),
    'value' => $periodValue,
    'set' => $dateFrom !== '' || $dateUntil !== '',
    'options' => $periodOptions,
    'range' => ['from' => $dateFrom, 'until' => $dateUntil],
];

// Scope: upstream's "My events" / "Org events" buttons.
$mine = ($get('searchemail') !== null && strcasecmp($get('searchemail'), $me['email']) === 0);
$ours = ((string)$get('searchorg') === (string)$me['org_id']);
$chips[] = [
    'label' => __('Scope'),
    'value' => $mine ? __('My events') : ($ours ? __('Org events') : __('All')),
    'set' => $mine || $ours,
    'options' => [
        [
            'label' => __('All'),
            'url' => $urlWith(['searchemail' => null, 'searchorg' => $ours ? null : $get('searchorg')]),
            'active' => !$mine && !$ours,
        ],
        ['label' => __('My events'), 'url' => $urlWith(['searchemail' => $me['email']]), 'active' => $mine],
        ['label' => __('Org events'), 'url' => $urlWith(['searchorg' => (string)$me['org_id']]), 'active' => $ours],
    ],
];

/* ── "More filters": upstream's advanced controls, same keys ────────── */
$searchTerm = $get('searcheventinfo') ?? $get('searcheventid') ?? '';
$panelFields = [
    ['name' => 'distribution', 'label' => __('Distribution'), 'options' => [
        '' => '',
        '0' => 'Your organisation only',
        '1' => 'Community',
        '2' => 'Connected communities',
        '3' => 'All communities',
    ]],
    ['name' => 'published', 'label' => __('Published'), 'options' => [
        '' => '', '1' => 'Published', '0' => 'Not published',
    ]],
    ['name' => 'org', 'label' => __('Creator Org'), 'options' => $orgOptions],
    ['name' => 'tag', 'label' => __('Tags'), 'options' => $tagOptions],
    ['name' => 'galaxy', 'label' => __('Galaxy'), 'options' => $galaxyOptions],
];
foreach ($panelFields as $i => $field) {
    $panelFields[$i] += [
        'type' => 'select',
        'value' => (string)$get('search' . $field['name']),
        'col' => 4,
    ];
}
$advId = 'fiEvMoreFilters';
$draftConfig = [
    'advId' => $advId,
    'base' => $baseurl . '/events/index',
    'itemPath' => '/events/index',
    'mode' => 'event',
    'transport' => 'path',
    'searchField' => 'eventinfo',
    'idField' => 'eventid',
    'ownedKeys' => array_merge(
        ['eventinfo', 'eventid'],
        array_column($panelFields, 'name'),
        ['sort', 'direction', 'page', 'limit']
    ),
    'results' => '#index-results',
    'swap' => ['.fi-ev-chips', '.fi-ev-range'],
];
// Upstream's badge: everything that filters, search and scope included.
$activeTotal = count(array_filter($named, function ($v, $k) {
    return strpos($k, 'search') === 0 && $v !== '' && $v !== null;
}, ARRAY_FILTER_USE_BOTH));

/* ── range caption ─────────────────────────────────────────────────── */
$paging = $this->Paginator->params();
$total = (int)($paging['count'] ?? 0);
$first = $total ? ((int)$paging['page'] - 1) * (int)$paging['limit'] + 1 : 0;
$last = $total ? $first + (int)$paging['current'] - 1 : 0;
$range = sprintf('%s–%s of %s', number_format($first), number_format($last), number_format($total));

/* ── table helpers ─────────────────────────────────────────────────── */
$sortHeader = function ($field, $label) {
    return $this->Paginator->sort($field, h($label), ['escape' => false]);
};
$columnNames = [
    'tags' => __('Tags'),
    'clusters' => __('Galaxy clusters'),
    'attribute_count' => __('Attribute count'),
    'correlations' => __('Correlations'),
    'proposals' => __('Proposals'),
];
$columnChoices = array_intersect_key($columnNames, array_flip($possibleColumns ?? []));
?>

<div class="fi-ev" id="fiEventsIndex"
     data-index-url="<?= h($baseurl . '/events/index') ?>"
     data-item-path="/events/index"
     data-facet-url="<?= h($baseurl . '/events/facetCounts') ?>"
     data-column-url="<?= h($baseurl . '/userSettings/eventIndexColumnToggle/') ?>">
    <script type="application/json" id="fiEvDraftConfig"><?= json_encode(
        $draftConfig + ['strings' => IndexFilterDraft::strings()],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    ) ?></script>

    <aside class="fi-ev-facets" aria-label="<?= __('Filters') ?>">
        <h1 class="fi-ev-title"><?= __('Events') ?></h1>
        <div class="fi-ev-facet-groups" aria-busy="true">
            <div class="fi-ev-facet-loading"><?= __('Loading filters…') ?></div>
        </div>
    </aside>

    <div class="fi-ev-main">

        <div class="fi-ev-toolbar">
            <label class="fi-ev-search">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <span class="visually-hidden"><?= __('Search events') ?></span>
                <input id="filterField" type="search" autocomplete="off"
                       placeholder="<?= __('Search by info, ID or UUID') ?>"
                       value="<?= h($searchTerm) ?>">
            </label>
            <?php if ($canImport): ?>
                <a class="fi-ev-btn" href="<?= h($baseurl . '/events/importEvent') ?>"
                   onclick="event.preventDefault(); openModal('<?= h($baseurl . '/events/importEvent') ?>');"><?= __('Import') ?></a>
            <?php endif; ?>
            <?php if ($canExport): ?>
                <a class="fi-ev-btn" href="<?= h($baseurl . '/events/export') ?>"><?= __('Export') ?></a>
            <?php endif; ?>
            <?php if ($canAdd || $canPickTemplate): ?>
                <div class="fi-ev-new">
                    <?php if ($canAdd): ?>
                        <a class="fi-ev-btn is-primary" id="add-event-button"
                           href="<?= h($baseurl . '/events/add') ?>"
                           onclick="event.preventDefault(); openModal('<?= h($baseurl . '/events/add') ?>');"><?= __('New event') ?></a>
                    <?php endif; ?>
                    <?php if ($canPickTemplate && $canAdd): ?>
                        <button type="button" class="fi-ev-btn is-primary fi-ev-new-caret" data-bs-toggle="dropdown"
                                aria-expanded="false" aria-label="<?= __('More ways to create an event') ?>">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end fi-ev-menu">
                            <li>
                                <a class="dropdown-item" href="#" id="event-template-picker-button"
                                   onclick="event.preventDefault(); openEventTemplatePicker();">
                                    <i class="fas fa-wand-magic-sparkles"></i><?= __('From template') ?>
                                </a>
                            </li>
                        </ul>
                    <?php elseif ($canPickTemplate): ?>
                        <a class="fi-ev-btn is-primary" href="#" id="event-template-picker-button"
                           onclick="event.preventDefault(); openEventTemplatePicker();"><?= __('From template') ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="fi-ev-filters">
            <div class="fi-ev-chips">
                <?php foreach ($chips as $chip): ?>
                    <?= $this->element('fi/events_index/filter_chip', $chip) ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="fi-ev-more-filters" data-bs-toggle="collapse"
                    data-bs-target="#<?= h($advId) ?>" aria-expanded="false" aria-controls="<?= h($advId) ?>">
                <i class="fas fa-sliders" aria-hidden="true"></i><?= __('More filters') ?>
                <span class="filter-draft-count<?= $activeTotal ? '' : ' d-none' ?>"><?= (int)$activeTotal ?></span>
            </button>
            <span class="fi-ev-range fi-mono"><?= h($range) ?></span>
        </div>

        <div class="fi-ev-more-panel">
            <?= $this->element('genericElementsBS5/IndexTable/filter_panel', [
                'id' => $advId,
                'open' => false,
                'fields' => $panelFields,
                'input_class' => 'topbar-filter',
            ]) ?>
        </div>

        <div id="multiSelectToolbar" class="fi-ev-selbar d-none" role="region" aria-label="<?= __('Selected events') ?>">
            <span><span id="selectedCount">0</span> <?= __('selected') ?></span>
            <button type="button" id="multi-export-button" class="fi-ev-btn"
                    onclick="multiSelectItems('<?= h($baseurl . '/events/restSearchExport') ?>', '')">
                <i class="fas fa-file-export"></i><?= __('Export') ?>
            </button>
            <button type="button" id="multi-delete-button" class="fi-ev-btn is-danger d-none"
                    onclick="multiSelectItems('<?= h($baseurl . '/events/delete') ?>', '/true')">
                <i class="fas fa-trash"></i><?= __('Delete') ?>
            </button>
        </div>

        <div id="index-results" class="index-results">
            <div class="fi-panel fi-ev-panel">
                <?php if (empty($events)): ?>
                    <div class="fi-ev-empty"><?= __('No events match these filters') ?></div>
                <?php else: ?>
                <table class="fi-ev-table" data-dblclick-url="<?= h($viewUrl) ?>">
                    <thead>
                        <tr>
                            <th class="fi-ev-c-id">
                                <input id="select_all" class="select_all form-check-input" type="checkbox"
                                       onclick="toggleAllAttributeCheckboxes(this);"
                                       aria-label="<?= __('Select all') ?>">
                                <?= $sortHeader('Event.id', __('ID')) ?>
                            </th>
                            <th class="fi-ev-c-info"><?= $sortHeader('Event.info', __('Event')) ?></th>
                            <th class="fi-ev-c-org"><?= $sortHeader('Orgc.name', __('Creator org')) ?></th>
                            <th class="fi-ev-c-date"><?= $sortHeader('Event.date', __('Date')) ?></th>
                            <th class="fi-ev-c-threat"><?= $sortHeader('Event.threat_level_id', __('Threat')) ?></th>
                            <?php if ($show['attr']): ?>
                                <th class="fi-ev-c-num"><?= $sortHeader('Event.attribute_count', __('Attr.')) ?></th>
                            <?php endif; ?>
                            <?php if ($show['corr']): ?>
                                <th class="fi-ev-c-num"><?= __('Corr.') ?></th>
                            <?php endif; ?>
                            <th class="fi-ev-c-state">
                                <?= $sortHeader('Event.published', __('State')) ?>
                                <?php if ($canPickColumns && $columnChoices): ?>
                                    <div class="dropdown fi-ev-act">
                                        <button type="button" class="fi-ev-more" data-bs-toggle="dropdown"
                                                aria-expanded="false" title="<?= __('Columns') ?>"
                                                aria-label="<?= __('Choose columns') ?>">
                                            <i class="fas fa-table-columns"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end fi-ev-menu">
                                            <h6 class="dropdown-header"><?= __('Columns') ?></h6>
                                            <?php foreach ($columnChoices as $column => $label):
                                                $on = in_array($column, $columns, true); ?>
                                                <button type="button" class="dropdown-item" data-fi-ev-column="<?= h($column) ?>"
                                                        aria-pressed="<?= $on ? 'true' : 'false' ?>">
                                                    <i class="fas fa-check<?= $on ? '' : ' invisible' ?>"></i><?= h($label) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $k => $event): ?>
                            <?= $this->element('fi/events_index/row', [
                                'event' => $event,
                                'k' => $k,
                                'viewUrl' => $viewUrl,
                                'show' => $show,
                            ]) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <?php if ((int)($paging['pageCount'] ?? 0) > 1): ?>
                <div class="fi-ev-pager">
                    <?= $this->element('genericElementsBS5/IndexTable/pagination_nav', [
                        'maxPages' => 7,
                        'size' => 'sm',
                    ]) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
