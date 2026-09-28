<?php
/*
 * OvermindFi events index (mockup 1c): facet column, toolbar, dense table.
 *
 * Built on Themed/Overmind/Events/index.ctp and Elements/Events/index.ctp.
 * The filter bar (search, My/Org events, More filters, mass actions), the
 * card view and the pagination are the upstream elements; only the table
 * is drawn here. Everything the filter-draft engine swaps after an ajax
 * apply lives inside #index-results, the facets reload themselves from
 * events/facetCounts (js/fi/events-index.js).
 */
$this->set('hideHeaderSection', true);
$this->set('additionalJs', ['fi/events-index']);

$canImport = $this->Acl->canAccess('events', 'importEvent');
$canPickTemplate = (
    $this->Acl->canAccess('eventTemplates', 'index')
    && $this->Acl->canAccess('eventTemplates', 'instantiate')
);
$canAdd = $this->Acl->canAccess('events', 'add');
$canExport = $this->Acl->canAccess('events', 'export');
if ($canPickTemplate) {
    echo $this->element('eventTemplates/templatePickerModal');
}

$columns = $columns ?? [];
$showTags = in_array('tags', $columns, true);
$showClusters = in_array('clusters', $columns, true);
$showAttr = in_array('attribute_count', $columns, true);
$showCorr = in_array('correlations', $columns, true);

$viewUrl = $baseurl . '/events/view2/%id%';

$checkboxField = [
    'element' => 'checkbox',
    'data_path' => 'Event.id',
    'publish_path' => 'Event.published',
];
$actionsField = [
    'name' => __('Actions'),
    'element' => 'row_actions',
    'data_path' => 'Event.id',
    'publish_path' => 'Event.published',
    'card_section' => 'extra',
    'display_in' => ['table', 'card'],
    'actions' => [
        [
            'type' => 'navigate',
            'label' => __('View'),
            'icon' => 'eye',
            'url' => $viewUrl,
        ],
        [
            'type' => 'modal',
            'label' => __('Edit'),
            'icon' => 'pen-to-square',
            'url' => $baseurl . '/events/edit/%id%',
            'requirement' => 'check_edit_rights',
        ],
        [
            'type' => 'modal',
            'label' => __('Delete'),
            'icon' => 'trash',
            'url' => $baseurl . '/events/delete/%id%',
            'class' => 'text-danger',
            'requirement' => 'check_edit_rights',
        ],
        [
            'type' => 'divider',
            'url' => '#',
            'requirement' => 'check_publish_rights',
        ],
        [
            'type' => 'toggle',
            'label_on' => __('Unpublish'),
            'label_off' => __('Publish'),
            'icon_on' => 'eye-slash',
            'icon_off' => 'upload',
            'url' => $baseurl . '/events/%action%/%id%',
            'publish_path' => 'Event.published',
            'requirement' => 'check_publish_rights',
        ],
    ],
];

// Card view (the upstream one, forced on narrow screens): same fields as
// Overmind's Elements/Events/index.ctp.
$cardFields = [
    $checkboxField + ['card_section' => 'selector'],
    ['name' => __('ID'), 'sort' => 'Event.id', 'data_path' => 'Event.id', 'element' => 'id', 'url' => $viewUrl, 'card_section' => 'top', 'display_in' => ['card']],
    ['name' => __('Distribution'), 'data_path' => 'Event.distribution', 'element' => 'distribution', 'card_section' => 'top', 'display_in' => ['card']],
    ['name' => __('Info'), 'data_path' => 'Event', 'element' => 'event_info', 'card_section' => 'title', 'display_in' => ['card']],
    ['name' => __('Published'), 'sort' => 'Event.published', 'data_path' => 'Event.published', 'element' => 'published', 'card_section' => 'top', 'display_in' => ['card']],
    ['name' => __('Creator Org'), 'sort' => 'Orgc.name', 'data_path' => 'Orgc', 'element' => 'organisation', 'card_section' => 'meta', 'display_in' => ['card']],
    ['name' => __('Owner Org'), 'sort' => 'Org.name', 'data_path' => 'Org', 'element' => 'organisation', 'card_section' => 'meta', 'display_in' => ['card']],
    ['name' => __('Tags'), 'data_path' => 'EventTag', 'element' => 'tag_list', 'card_section' => 'tag', 'display_in' => ['card']],
    ['name' => __('Galaxy'), 'data_path' => 'GalaxyCluster', 'element' => 'galaxy', 'card_section' => 'galaxy', 'display_in' => ['card']],
    ['name' => __('Created'), 'data_path' => 'Event.date', 'element' => 'datetime', 'mode' => 'created', 'card_section' => 'meta', 'display_in' => ['card']],
    ['name' => __('Last Modified'), 'data_path' => 'Event.timestamp', 'element' => 'datetime', 'mode' => 'modified', 'card_section' => 'meta', 'display_in' => ['card']],
    ['name' => __('Contents'), 'data_path' => 'Event', 'element' => 'event_contents', 'card_section' => 'meta', 'display_in' => ['card']],
    $actionsField,
];

$children = [
    [
        'type' => 'search',
        'button' => 'Search',
        'placeholder' => __('Search by info, ID or UUID'),
        'name' => 'eventinfo',
        'mode' => 'event',
        'id_field' => 'eventid',
    ],
    [
        'type' => 'button',
        'label' => __('My events'),
        'icon' => 'misp-icon misp-icon-user1 misp-simple',
        'class' => 'btn btn-outline-secondary',
        'url' => $baseurl . '/events/index/searchemail:' . urlencode($me['email']),
    ],
    [
        'type' => 'button',
        'label' => __('Org events'),
        'icon' => 'misp-icon misp-icon-organisation misp-simple',
        'class' => 'btn btn-outline-secondary',
        'url' => $baseurl . '/events/index/searchorg:' . urlencode($me['org_id']),
    ],
    [
        'type' => 'more_filters',
        'label' => __('More filters'),
        'children' => [
            [
                'type' => 'dropdown',
                'label' => __('Distribution'),
                'name' => 'distribution',
                'options' => [
                    '' => '',
                    '0' => 'Your organisation only',
                    '1' => 'Community',
                    '2' => 'Connected communities',
                    '3' => 'All communities',
                ],
            ],
            [
                'type' => 'dropdown',
                'label' => __('Published'),
                'name' => 'published',
                'options' => ['' => '', '1' => 'Published', '0' => 'Not published'],
            ],
            ['type' => 'dropdown', 'label' => __('Creator Org'), 'name' => 'org', 'options' => $orgOptions],
            ['type' => 'dropdown', 'label' => __('Tags'), 'name' => 'tag', 'options' => $tagOptions],
            ['type' => 'dropdown', 'label' => __('Galaxy'), 'name' => 'galaxy', 'options' => $galaxyOptions],
        ],
    ],
];

$scaffoldData = [
    'data' => $events,
    'cards_per_row' => ['' => 1, 'lg' => 2, 'xxxxl' => 3],
    'filter_bar' => [
        'pull' => 'right',
        'children' => $children,
        'export' => 1,
        'delete' => '/delete',
    ],
    'fields' => $cardFields,
    'primary_id_path' => 'Event.id',
    'row_dblclick_url' => $viewUrl,
];

/* ── active-filter chips and range caption ─────────────────────────── */
$named = $this->request->params['named'] ?? [];
$chipLabels = [
    'all' => __('Search'),
    'eventinfo' => __('Info'),
    'eventid' => __('ID'),
    'threatlevel' => __('Threat'),
    'tag' => __('Tag'),
    'tags' => __('Tag'),
    'org' => __('Creator org'),
    'published' => __('State'),
    'hasproposal' => __('Proposals'),
    'distribution' => __('Distribution'),
    'analysis' => __('Analysis'),
    'email' => __('Creator'),
    'galaxy' => __('Galaxy'),
    'datefrom' => __('From'),
    'dateuntil' => __('Until'),
    'attribute' => __('Attribute'),
    'value' => __('Value'),
];
$threatNames = [1 => __('High'), 2 => __('Medium'), 3 => __('Low'), 4 => __('Undefined')];
$chipValue = function ($key, $value) use ($threatNames, $distributionLevels) {
    $map = [];
    if ($key === 'threatlevel') {
        $map = $threatNames;
    } elseif ($key === 'published') {
        $map = ['0' => __('Draft'), '1' => __('Published'), '2' => __('Any')];
    } elseif ($key === 'hasproposal') {
        $map = ['0' => __('None'), '1' => __('Has proposals'), '2' => __('Any')];
    } elseif ($key === 'distribution') {
        $map = $distributionLevels ?? [];
    }
    $out = [];
    foreach (explode('|', (string)$value) as $piece) {
        $not = strpos($piece, '!') === 0;
        $bare = $not ? substr($piece, 1) : $piece;
        $out[] = ($not ? '!' : '') . ($map[$bare] ?? $bare);
    }
    return implode(' | ', $out);
};
$chips = [];
foreach ($named as $rawKey => $value) {
    if (is_array($value) || (string)$value === '' || stripos($rawKey, 'search') !== 0) {
        continue;
    }
    $key = strtolower(substr($rawKey, 6));
    $remaining = $named;
    unset($remaining[$rawKey], $remaining['page']);
    $chips[] = [
        'label' => $chipLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)),
        'value' => $chipValue($key, $value),
        'remove' => $this->Html->url(
            ['controller' => 'events', 'action' => 'index'] + $remaining
        ),
    ];
}

$paging = $this->Paginator->params();
$total = (int)($paging['count'] ?? 0);
$first = $total ? ((int)$paging['page'] - 1) * (int)$paging['limit'] + 1 : 0;
$last = $total ? $first + (int)$paging['current'] - 1 : 0;

/* ── row helpers ───────────────────────────────────────────────────── */
$threatKinds = [1 => 'high', 2 => 'medium', 3 => 'low', 4 => 'undefined'];
$threatIcons = [
    1 => 'circle-exclamation',
    2 => 'triangle-exclamation',
    3 => 'circle-down',
    4 => 'circle-question',
];
$initials = function ($name) {
    $words = preg_split('/[\s\-_]+/u', trim((string)$name), -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $out .= mb_substr($word, 0, 1);
    }
    return mb_strtoupper($out);
};
$sortHeader = function ($field, $label) {
    return $this->Paginator->sort(
        $field,
        '<span class="sortable-header">' . h($label) . '<i class="sort-icon"></i></span>',
        ['escape' => false]
    );
};
$maxTags = 4;
?>

<div class="fi-ev" id="fiEventsIndex"
     data-index-url="<?= h($baseurl . '/events/index') ?>"
     data-item-path="/events/index"
     data-facet-url="<?= h($baseurl . '/events/facetCounts') ?>">

    <aside class="fi-ev-facets" aria-label="<?= __('Filters') ?>">
        <div class="fi-ev-title">
            <h1><?= __('Events') ?></h1>
            <span class="fi-mono fi-ev-total" id="headerCountBadge"><?= h(number_format($total)) ?></span>
        </div>
        <div class="fi-ev-facet-groups" aria-busy="true">
            <div class="fi-ev-facet-loading fi-faint"><?= __('Loading filters…') ?></div>
        </div>
    </aside>

    <div class="fi-ev-main">

        <div class="fi-ev-toolbar">
            <div class="fi-ev-filterbar">
                <?= $this->element('genericElementsBS5/IndexTable/filter_bar', [
                    'scaffold_data' => $scaffoldData,
                    'item_url' => '/events',
                ]) ?>
            </div>
            <div class="fi-ev-actions">
                <?php if ($canImport): ?>
                    <a class="btn btn-ghost" href="<?= h($baseurl . '/events/importEvent') ?>"
                       onclick="event.preventDefault(); openModal('<?= h($baseurl . '/events/importEvent') ?>');">
                        <i class="fas fa-file-import"></i><?= __('Import') ?>
                    </a>
                <?php endif; ?>
                <?php if ($canExport): ?>
                    <a class="btn btn-ghost" href="<?= h($baseurl . '/events/export') ?>">
                        <i class="fas fa-file-export"></i><?= __('Export') ?>
                    </a>
                <?php endif; ?>
                <?php if ($canAdd || $canPickTemplate): ?>
                    <div class="btn-group">
                        <?php if ($canAdd): ?>
                            <a class="btn btn-primary" id="add-event-button"
                               href="<?= h($baseurl . '/events/add') ?>"
                               onclick="event.preventDefault(); openModal('<?= h($baseurl . '/events/add') ?>');">
                                <i class="fas fa-plus"></i><?= __('New event') ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($canPickTemplate): ?>
                            <?php if ($canAdd): ?>
                                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="<?= __('More ways to create an event') ?>"></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" id="event-template-picker-button"
                                           onclick="event.preventDefault(); openEventTemplatePicker();">
                                            <i class="fas fa-wand-magic-sparkles me-2"></i><?= __('From template') ?>
                                        </a>
                                    </li>
                                </ul>
                            <?php else: ?>
                                <a class="btn btn-primary" href="#" id="event-template-picker-button"
                                   onclick="event.preventDefault(); openEventTemplatePicker();">
                                    <i class="fas fa-wand-magic-sparkles"></i><?= __('From template') ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="index-results" class="index-results">

            <div class="fi-ev-meta">
                <div class="fi-ev-chips">
                    <?php foreach ($chips as $chip): ?>
                        <span class="fi-ev-chip">
                            <span class="fi-ev-chip-label"><?= h($chip['label']) ?></span>
                            <span class="fi-ev-chip-value">
                                <?= h($chip['value']) ?>
                                <a href="<?= h($chip['remove']) ?>" class="fi-ev-chip-remove"
                                   title="<?= __('Remove filter') ?>" aria-label="<?= __('Remove filter') ?>">
                                    <i class="fas fa-xmark"></i>
                                </a>
                            </span>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($chips) > 1): ?>
                        <a class="fi-ev-chip-clear" href="<?= h($baseurl . '/events/index') ?>"><?= __('Clear all') ?></a>
                    <?php endif; ?>
                </div>
                <span class="fi-mono fi-ev-range">
                    <?= h(sprintf('%s–%s of %s', number_format($first), number_format($last), number_format($total))) ?>
                </span>
            </div>

            <div class="fi-panel fi-ev-panel">
                <div id="tableView">
                    <?php if (empty($events)): ?>
                        <div class="fi-ev-empty fi-faint">
                            <i class="fas fa-inbox"></i><?= __('No events match these filters') ?>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                    <table class="fi-ev-table" data-dblclick-url="<?= h($viewUrl) ?>">
                        <thead class="checkbox-index">
                            <tr>
                                <th class="fi-ev-c-sel">
                                    <input id="select_all" class="select_all form-check-input" type="checkbox"
                                           onclick="toggleAllAttributeCheckboxes(this);"
                                           aria-label="<?= __('Select all') ?>">
                                </th>
                                <th class="fi-ev-c-id pagination_link"><?= $sortHeader('Event.id', __('ID')) ?></th>
                                <th class="fi-ev-c-info"><?= __('Event') ?></th>
                                <th class="fi-ev-c-org pagination_link"><?= $sortHeader('Orgc.name', __('Creator org')) ?></th>
                                <th class="fi-ev-c-date pagination_link"><?= $sortHeader('Event.date', __('Date')) ?></th>
                                <th class="fi-ev-c-threat pagination_link"><?= $sortHeader('Event.threat_level_id', __('Threat')) ?></th>
                                <?php if ($showAttr): ?>
                                    <th class="fi-ev-c-num pagination_link"><?= $sortHeader('Event.attribute_count', __('Attr.')) ?></th>
                                <?php endif; ?>
                                <?php if ($showCorr): ?>
                                    <th class="fi-ev-c-num"><?= __('Corr.') ?></th>
                                <?php endif; ?>
                                <th class="fi-ev-c-state pagination_link"><?= $sortHeader('Event.published', __('State')) ?></th>
                                <th class="fi-ev-c-act"><span class="visually-hidden"><?= __('Actions') ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($events as $k => $event):
                            $e = $event['Event'];
                            $id = (int)$e['id'];
                            $rowUrl = str_replace('%id%', $id, $viewUrl);
                            $threatId = (int)$e['threat_level_id'];
                            $threatLabel = $event['ThreatLevel']['name'] ?? ($threatNames[$threatId] ?? '');
                            $orgc = $event['Orgc'] ?? [];
                            $logo = empty($orgc) ? '' : $this->OrgImg->getOrgLogoV2($orgc, 18, false);
                            $tags = [];
                            if ($showTags) {
                                foreach (($event['EventTag'] ?? []) as $eventTag) {
                                    if (!empty($eventTag['Tag']) && empty($eventTag['Tag']['is_galaxy'])) {
                                        $tags[] = $eventTag;
                                    }
                                }
                            }
                            $clusters = $showClusters ? ($event['GalaxyCluster'] ?? []) : [];
                            $corrCount = (int)($e['correlation_count'] ?? 0);
                            $proposals = (int)($e['proposals_count'] ?? 0);
                            $distribution = (int)$e['distribution'];
                            $distLabel = ($distribution === 4 && !empty($event['SharingGroup']['name']))
                                ? $event['SharingGroup']['name']
                                : ($shortDist[$distribution] ?? '');
                        ?>
                            <tr data-row-id="<?= h($k) ?>" data-primary-id="<?= $id ?>">
                                <td class="fi-ev-c-sel">
                                    <?= $this->element('genericElementsBS5/IndexTable/Fields/checkbox', [
                                        'field' => $checkboxField,
                                        'row' => $event,
                                    ]) ?>
                                </td>
                                <td class="fi-ev-c-id">
                                    <a class="fi-mono" href="<?= h($rowUrl) ?>"><?= $id ?></a>
                                </td>
                                <td class="fi-ev-c-info">
                                    <a class="fi-ev-info" href="<?= h($rowUrl) ?>" title="<?= h($e['info']) ?>"><?= h($e['info']) ?></a>
                                    <?php if (!empty($e['extends_uuid'])):
                                        $extended = $extendedEvents[$e['extends_uuid']] ?? null; ?>
                                        <div class="fi-ev-extends fi-faint">
                                            <?= __('Extends') ?>
                                            <?php if ($extended): ?>
                                                <a href="<?= h($baseurl . '/events/view2/' . $extended['id']) ?>"><?= h($extended['info']) ?></a>
                                            <?php else: ?>
                                                <span class="fi-mono"><?= h($e['extends_uuid']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($tags || $clusters): ?>
                                        <div class="fi-ev-tags tag-container">
                                            <?php foreach ($tags as $i => $eventTag):
                                                $tag = $eventTag['Tag'];
                                                $colour = !empty($tag['colour']) ? $tag['colour'] : '#0088cc'; ?>
                                                <a class="fi-tag<?= $i >= $maxTags ? ' d-none extra-tag' : '' ?>"
                                                   href="<?= h($baseurl . '/events/index/searchtag:' . $tag['id']) ?>"
                                                   title="<?= h($tag['name']) ?>"
                                                   style="background-color:<?= h($colour) ?>;color:<?= h($this->TextColour->getTextColour($colour)) ?>;">
                                                    <?php if (!empty($eventTag['local'])): ?><i class="fas fa-user"></i><?php endif; ?>
                                                    <?= h($tag['name']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                            <?php if (count($tags) > $maxTags): ?>
                                                <span class="fi-tag fi-tag-more" role="button" tabindex="0"
                                                      onclick="toggleTags(this)">+<?= count($tags) - $maxTags ?></span>
                                            <?php endif; ?>
                                            <?php foreach ($clusters as $cluster):
                                                $galaxy = $cluster['Galaxy'] ?? []; ?>
                                                <span class="fi-cluster" title="<?= h($galaxy['name'] ?? '') ?>">
                                                    <i class="fas fa-<?= h($galaxy['icon'] ?? 'globe') ?>"></i>
                                                    <?php if (!empty($cluster['local'])): ?><i class="fas fa-user"></i><?php endif; ?>
                                                    <?= h($cluster['value'] ?? '') ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="fi-ev-c-org">
                                    <?php if (!empty($orgc)): ?>
                                        <a class="fi-ev-org" href="<?= h($baseurl . '/organisations/view/' . $orgc['id']) ?>" title="<?= h($orgc['name']) ?>">
                                            <?php if ($logo !== ''): ?>
                                                <span class="fi-org-tile"><?= $logo ?></span>
                                            <?php else: ?>
                                                <span class="fi-org-tile" aria-hidden="true"><?= h($initials($orgc['name'])) ?></span>
                                            <?php endif; ?>
                                            <span class="fi-ev-org-name"><?= h($orgc['name']) ?></span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="fi-ev-c-date fi-mono"><?= h($e['date']) ?></td>
                                <td class="fi-ev-c-threat">
                                    <span class="fi-threat fi-threat--<?= h($threatKinds[$threatId] ?? 'undefined') ?>">
                                        <i class="fas fa-<?= h($threatIcons[$threatId] ?? 'circle-question') ?>"></i><?= h(ucfirst(strtolower($threatLabel))) ?>
                                    </span>
                                </td>
                                <?php if ($showAttr): ?>
                                    <td class="fi-ev-c-num fi-num">
                                        <?= h(number_format((int)$e['attribute_count'])) ?>
                                        <?php if (!empty($e['object_count'])): ?>
                                            <span class="fi-ev-sub"><?= h(__n('%s object', '%s objects', (int)$e['object_count'], number_format((int)$e['object_count']))) ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($showCorr): ?>
                                    <td class="fi-ev-c-num fi-num fi-dim">
                                        <?php if ($corrCount > 0): ?>
                                            <a href="<?= h($rowUrl) ?>#tab-correlation"><?= h(number_format($corrCount)) ?></a>
                                        <?php else: ?>0<?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="fi-ev-c-state">
                                    <span class="fi-ev-state<?= $e['published'] ? '' : ' is-draft' ?>">
                                        <?= $e['published'] ? __('Published') : __('Draft') ?>
                                    </span>
                                    <?php if ($proposals > 0): ?>
                                        <span class="fi-ev-sub"><?= h(__n('%s proposal', '%s proposals', $proposals, $proposals)) ?></span>
                                    <?php endif; ?>
                                    <span class="fi-ev-sub" title="<?= __('Distribution') ?>"><?= h($distLabel) ?></span>
                                </td>
                                <td class="fi-ev-c-act">
                                    <?= $this->element('genericElementsBS5/IndexTable/Fields/row_actions', [
                                        'field' => $actionsField,
                                        'row' => $event,
                                    ]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="cardView" class="d-none">
                    <?= $this->element('genericElementsBS5/IndexTable/index_card', [
                        'scaffold_data' => ['data' => $scaffoldData],
                    ]) ?>
                </div>
            </div>

            <div class="fi-ev-pagination">
                <?= $this->element('genericElementsBS5/IndexTable/pagination') ?>
            </div>

        </div>
    </div>
</div>
