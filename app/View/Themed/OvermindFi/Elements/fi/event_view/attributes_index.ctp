<?php
/*
 * OvermindFi event view: the attribute table (mockup 1e). Rendered only by
 * Events/view_attributes.ctp, from the rows EventsController::viewAttributes
 * hands over (flat attribute arrays).
 *
 * Six columns: Category, Type, Value, Tags, IDS, Corr. Everything else
 * Overmind's Elements/Attributes/index.ctp showed as a column (distribution,
 * galaxies, related events, feed hits, sightings, correlation toggle, analyst
 * data, dates) lives in a per-row detail drawer, opened by clicking the row,
 * its Corr. count or "Details" in the row's "⋯" menu. The menu carries every
 * row action upstream had. Filters are the upstream filter_bar, folded
 * behind the tab bar's "Filter" button; selection is the upstream checkbox
 * and multi-select toolbar. Cell widgets reuse the upstream field elements,
 * so their ACL checks and scripts (IDS / correlation toggles, sightings) are
 * theirs.
 */
$eventId = (int)$event['Event']['id'];
$_canModify = !empty($mayModify);
$_canPropose = !empty($me['Role']['perm_add']);
$_canAnalystData = !empty($me['Role']['perm_analyst_data']);
$_canSighting = !empty($isAclSighting);
$_canModifyProposal = !empty($isSiteAdmin) || $_canModify;
// Enrichment / Cortex expansion (misp-modules): offered only when the
// matching services plugin is enabled and the user can modify.
$_enrichmentEnabled = (bool)Configure::read('Plugin.Enrichment_services_enable');
$_cortexEnabled = (bool)Configure::read('Plugin.Cortex_services_enable');
$_hoverEnrich = Configure::read('Plugin.Enrichment_hover_enable') && $_canPropose;
$_hoverClickOnly = (bool)Configure::read('Plugin.Enrichment_hover_popover_only');
// Extended / extending event view: rows can belong to any event of the
// merged set, so each one says where it comes from and wears its origin's
// accent, and the row actions ask the origin event.
$extensionEvents = $extensionEvents ?? [];
$inExtensionView = count($extensionEvents) > 1;
$canTagAttr = $this->Acl->canModifyTag($event);

$origin = function ($row) use ($extensionEvents) {
    return $extensionEvents[(int)($row['event_id'] ?? 0)] ?? null;
};
$_rowMayModify = function ($row) use ($_canModify, $inExtensionView, $origin) {
    return $inExtensionView ? !empty($origin($row)['mayModify']) : $_canModify;
};
$_rowMayTag = function ($row) use ($canTagAttr, $inExtensionView, $origin) {
    return $inExtensionView ? !empty($origin($row)['mayModifyTag']) : $canTagAttr;
};
$live = function ($row) {
    return empty($row['deleted']) && empty($row['is_proposal']);
};

/* ── row "⋯" menu: Overmind's row actions plus the ones its columns held ── */
$checkboxField = ['element' => 'checkbox', 'data_path' => 'Attribute.id'];
$actionsField = [
    'element' => 'row_actions',
    'data_path' => 'Attribute.id',
    'actions' => [
        [
            'type' => 'js',
            'label' => __('Details'),
            'icon' => 'fas fa-angles-down',
            'onclick' => 'fiEvwToggleRow(this);',
        ],
        [
            'type' => 'modal',
            'label' => __('Edit'),
            'icon' => 'pen-to-square',
            'url' => $baseurl . '/attributes/edit/%id%',
            'requirement' => function ($row) use ($_rowMayModify, $live) {
                return $_rowMayModify($row) && $live($row);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Propose change'),
            'icon' => 'comment-dots',
            'url' => $baseurl . '/shadow_attributes/edit/%id%',
            'requirement' => function ($row) use ($_canPropose, $live) {
                return $_canPropose && $live($row);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Add tag'),
            'icon' => 'misp-icon misp-icon-tag misp-simple',
            'url' => $baseurl . '/attributes/editAttributeTags/%id%',
            'size' => 'xl',
            'requirement' => function ($row) use ($_rowMayTag, $live) {
                return $_rowMayTag($row) && $live($row);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Add galaxy cluster'),
            'icon' => 'misp-icon misp-icon-galaxy misp-simple',
            'url' => $baseurl . '/attributes/editAttributeGalaxies/%id%',
            'size' => 'xl',
            'requirement' => function ($row) use ($_rowMayTag, $live) {
                return $_rowMayTag($row) && $live($row);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Sightings…'),
            'icon' => 'misp-icon misp-icon-sighting misp-simple',
            'url' => $baseurl . '/sightings/advanced/%id%/attribute',
            'size' => 'lg',
            'requirement' => function ($row) use ($_canSighting, $live) {
                return $_canSighting && $live($row);
            },
        ],
        [
            'type' => 'divider',
            'requirement' => function ($row) use ($_rowMayModify, $_enrichmentEnabled, $_cortexEnabled, $live) {
                return $_rowMayModify($row) && ($_enrichmentEnabled || $_cortexEnabled) && $live($row);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Enrich'),
            'icon' => 'fas fa-wand-magic-sparkles text-enrichment',
            'url' => $baseurl . '/events/queryEnrichment/%id%/0/Enrichment/Attribute',
            'requirement' => function ($row) use ($_rowMayModify, $_enrichmentEnabled, $live) {
                return $_rowMayModify($row) && $_enrichmentEnabled && $live($row);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Enrich (Cortex)'),
            'icon' => 'eye',
            'url' => $baseurl . '/events/queryEnrichment/%id%/0/Cortex/Attribute',
            'requirement' => function ($row) use ($_rowMayModify, $_cortexEnabled, $live) {
                return $_rowMayModify($row) && $_cortexEnabled && $live($row);
            },
        ],
        [
            'type' => 'divider',
            'requirement' => function ($row) use ($_canAnalystData, $live) {
                return $_canAnalystData && $live($row);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Add note'),
            'icon' => 'text-primary misp-icon misp-icon-analyst-note misp-simple',
            'url' => $baseurl . '/analystData/add/Note/%uuid%/Attribute',
            'url_params_data_paths' => ['uuid' => 'uuid'],
            'requirement' => function ($row) use ($_canAnalystData, $live) {
                return $_canAnalystData && $live($row);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Add opinion'),
            'icon' => 'text-success misp-icon misp-icon-analyst-opinion misp-simple',
            'url' => $baseurl . '/analystData/add/Opinion/%uuid%/Attribute',
            'url_params_data_paths' => ['uuid' => 'uuid'],
            'requirement' => function ($row) use ($_canAnalystData, $live) {
                return $_canAnalystData && $live($row);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Add relationship'),
            'icon' => 'text-correlation fas fa-diagram-project',
            'url' => $baseurl . '/analystData/add/Relationship/%uuid%/Attribute',
            'url_params_data_paths' => ['uuid' => 'uuid'],
            'requirement' => function ($row) use ($_canAnalystData, $live) {
                return $_canAnalystData && $live($row);
            },
        ],
        ['type' => 'divider'],
        [
            'type' => 'copy',
            'label' => __('Copy UUID'),
            'icon' => 'copy',
            'data_path' => 'uuid',
            'copy_message' => __('UUID copied to clipboard'),
        ],
        [
            'type' => 'modal',
            'label' => __('Restore'),
            'icon' => 'rotate-left',
            'url' => $baseurl . '/attributes/restore/%id%',
            'class' => 'text-success',
            'requirement' => function ($row) use ($_rowMayModify) {
                return $_rowMayModify($row) && !empty($row['deleted']) && empty($row['is_proposal']);
            },
        ],
        [
            'type' => 'modal',
            'label' => __('Delete'),
            'icon' => 'trash',
            'url' => $baseurl . '/attributes/delete/%id%',
            'class' => 'text-danger',
            'requirement' => function ($row) use ($_rowMayModify, $live) {
                return $_rowMayModify($row) && $live($row);
            },
        ],
    ],
];

/* ── detail-drawer widgets: the upstream field elements ── */
$field = function ($element, $row, array $config = []) {
    return trim($this->element('genericElementsBS5/IndexTable/Fields/' . $element, [
        'field' => $config + ['data_path' => ''],
        'row' => $row,
        'viewMode' => 'table',
    ]));
};
$tagListField = [
    'data_path' => 'AttributeTag',
    'add_tag' => $_rowMayTag,
    'add_tag_url' => $baseurl . '/attributes/editAttributeTags/%id%',
    'add_relationship_url' => $baseurl . '/attributes/editAttributeTagRelationships/%id%',
];
$galaxyField = [
    'data_path' => 'Galaxy',
    'add_galaxy' => $_rowMayTag,
    'add_galaxy_url' => $baseurl . '/attributes/editAttributeGalaxies/%id%',
    'add_galaxy_relationship_url' => $baseurl . '/attributes/editAttributeGalaxyRelationships/%id%',
];
$analystField = [
    'note_path' => 'Note',
    'opinion_path' => 'Opinion',
    'relationship_path' => 'Relationship',
    'relationship_inbound_path' => 'RelationshipInbound',
    'uuid_path' => 'uuid',
    'object_type' => 'Attribute',
];

// Accept / discard buttons for a pending proposal (upstream attribute_value.ctp).
$proposalActions = function ($pid) use ($_canModifyProposal, $baseurl) {
    if (!$_canModifyProposal) {
        return '';
    }
    $pid = (int)$pid;
    return sprintf(
        '<button type="button" class="fi-evw-prop-btn is-accept" title="%s" onclick="acceptProposal(%d)"><i class="fas fa-check"></i></button>'
        . '<button type="button" class="fi-evw-prop-btn" title="%s" onclick="openModal(\'%s/shadow_attributes/discard/%d\', \'sm\')"><i class="fas fa-times"></i></button>',
        h(__('Accept proposal')), $pid, h(__('Discard proposal')), h($baseurl), $pid
    );
};
// What a proposed edit changes against the live attribute.
$proposalDiffs = function ($p, $a) {
    $diffs = [];
    foreach (['category' => __('Category'), 'type' => __('Type'), 'value' => __('Value'), 'comment' => __('Comment')] as $key => $label) {
        if ((string)($p[$key] ?? '') !== (string)($a[$key] ?? '')) {
            $diffs[] = [$label, (string)($a[$key] ?? ''), (string)($p[$key] ?? '')];
        }
    }
    if ((int)($p['to_ids'] ?? 0) !== (int)($a['to_ids'] ?? 0)) {
        $diffs[] = [__('IDS'), !empty($a['to_ids']) ? __('yes') : __('no'), !empty($p['to_ids']) ? __('yes') : __('no')];
    }
    return $diffs;
};

/* ── "Filter" drawer: Overmind's event-view filter bar ── */
$named = $this->request->params['named'] ?? [];
$currentDeleted = (int)($named['deleted'] ?? 0);
$currentProposal = (int)($named['proposal'] ?? 0);
$filtersActive = 0;
foreach (['searchFor', 'category', 'type', 'deleted', 'proposal', 'warninglist'] as $key) {
    if (!empty($named[$key])) {
        $filtersActive++;
    }
}
// Fallback hrefs; the real toggles are wired by view_attributes.ctp.
// deleted:2 is "only the soft-deleted ones".
$attrBaseUrl = $baseurl . '/events/viewAttributes/' . $eventId . ($extensionSuffix ?? '');
$deletedUrl = $attrBaseUrl
    . ($currentDeleted ? '' : '/deleted:2')
    . ($currentProposal ? '/proposal:' . $currentProposal : '');
$proposalUrl = $attrBaseUrl
    . ($currentDeleted ? '/deleted:' . $currentDeleted : '')
    . ($currentProposal ? '' : '/proposal:1');
$filterBar = [
    'pull' => 'right',
    'skip_pagination' => true,
    'children' => [
        [
            // The tab filters on `searchFor:`; naming it lets the bar
            // render the term back into the box.
            'type' => 'search',
            'button' => 'Search',
            'placeholder' => __('Filter by attribute value'),
            'mode' => 'legacy',
            'name' => 'searchFor',
        ],
        [
            // viewAttributes only supports category and type.
            'type' => 'more_filters',
            'label' => __('More filters'),
            'children' => [
                ['type' => 'dropdown', 'label' => __('Category'), 'name' => 'category', 'options' => ['' => ''] + ($categoryOptions ?? [])],
                ['type' => 'dropdown', 'label' => __('Type'), 'name' => 'type', 'options' => ['' => ''] + ($typeOptions ?? [])],
            ],
        ],
        [
            'type' => 'button',
            'url' => $proposalUrl,
            'class' => 'btn attr-proposal-toggle ' . ($currentProposal ? 'btn-warning' : 'btn-outline-warning'),
            'icon' => 'fas fa-comment-dots',
            'label' => __('Proposals') . (!empty($proposalCount) ? ' (' . (int)$proposalCount . ')' : ''),
        ],
        [
            'type' => 'button',
            'url' => $deletedUrl,
            'class' => 'btn attr-deleted-toggle ' . ($currentDeleted ? 'btn-danger' : 'btn-outline-danger'),
            'icon' => 'fas fa-trash',
            'label' => __('Deleted') . (!empty($deletedCount) ? ' (' . (int)$deletedCount . ')' : ''),
        ],
    ],
];

$sortHeader = function ($key, $label) {
    return $this->Paginator->sort($key, h($label), ['escape' => false]);
};
$maxTags = 3;
$colCount = 8;
?>
<div class="fi-evw-attrs" style="<?= h('--fi-yes:' . json_encode(__('Yes')) . ';--fi-no:' . json_encode(__('No'))) ?>">

    <?php /* "Filter" drawer: the upstream bar, unchanged, folded until asked for. */ ?>
    <div class="fi-evw-filter<?= !empty($filtersActive) ? ' is-open' : '' ?>"
         data-active-filters="<?= (int)($filtersActive ?? 0) ?>">
        <?= $this->element('genericElementsBS5/IndexTable/filter_bar', [
            'scaffold_data' => ['filter_bar' => $filterBar],
            'item_url' => '/attributes',
        ]) ?>
    </div>

    <div id="index-results" class="index-results">
        <div class="fi-panel fi-evw-attr-panel">
            <?= $this->element('genericElementsBS5/IndexTable/multi_select_toolbar', [
                'filter_bar' => ['soft_delete' => '/deleteSelection'],
                'item_url' => '/attributes',
            ]) ?>

            <?php if (empty($attributes)): ?>
                <div class="fi-evw-attr-empty fi-faint">
                    <i class="fas fa-inbox"></i><?= __('No attributes to display') ?>
                </div>
            <?php else: ?>
            <table class="fi-evw-table">
                <colgroup>
                    <col class="fi-evw-c-sel"><col class="fi-evw-c-cat"><col class="fi-evw-c-type">
                    <col><col class="fi-evw-c-tags"><col class="fi-evw-c-ids">
                    <col class="fi-evw-c-corr"><col class="fi-evw-c-act">
                </colgroup>
                <thead class="checkbox-index">
                    <tr>
                        <th class="fi-evw-c-sel">
                            <input id="select_all" class="select_all form-check-input" type="checkbox"
                                   onclick="toggleAllAttributeCheckboxes(this);"
                                   aria-label="<?= __('Select all') ?>">
                        </th>
                        <th class="fi-evw-c-cat"><?= $sortHeader('category', __('Category')) ?></th>
                        <th class="fi-evw-c-type"><?= $sortHeader('type', __('Type')) ?></th>
                        <th><?= $sortHeader('value', __('Value')) ?></th>
                        <th class="fi-evw-c-tags"><?= __('Tags') ?></th>
                        <th class="fi-evw-c-ids"><?= $sortHeader('to_ids', __('IDS')) ?></th>
                        <th class="fi-evw-c-corr"><?= __('Corr.') ?></th>
                        <th class="fi-evw-c-act"><span class="visually-hidden"><?= __('Actions') ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($attributes as $k => $row):
                    $id = (int)($row['id'] ?? 0);
                    $isProposal = !empty($row['is_proposal']);
                    $isDeleted = !empty($row['deleted']);
                    $rowOrigin = $inExtensionView ? $origin($row) : null;

                    $classes = ['fi-evw-row'];
                    $style = '';
                    if ($isProposal) {
                        $classes[] = 'attr-proposal-row';
                    } elseif ($isDeleted) {
                        $classes[] = 'attr-deleted';
                    }
                    if ($rowOrigin !== null && $rowOrigin['role'] !== 'self') {
                        $classes[] = 'evt-extension-row';
                        $style = sprintf(
                            '--extension-tint:%s;--extension-accent:%s;',
                            $rowOrigin['palette']['sectionBg'],
                            $rowOrigin['palette']['badgeBorder']
                        );
                    }

                    // Correlated events, deduplicated as relatedEvents.ctp does.
                    $relatedIds = [];
                    foreach ($row['RelatedAttribute'] ?? [] as $ra) {
                        $relatedIds[$ra['Event']['id'] ?? ($ra['id'] ?? '')] = true;
                    }
                    unset($relatedIds['']);
                    $corrCount = count($relatedIds);

                    $tags = [];
                    foreach ($row['AttributeTag'] ?? [] as $at) {
                        if (!empty($at['Tag']) && empty($at['Tag']['is_galaxy'])) {
                            $tags[] = $at;
                        }
                    }
                    $mayTag = $_rowMayTag($row) && !$isDeleted && !$isProposal && $id;
                    $hoverId = ($_hoverEnrich && !$isProposal && $id) ? $id : null;
                    $editable = $_rowMayModify($row) && !$isDeleted && !$isProposal && $id;
                ?>
                    <tr class="<?= h(implode(' ', $classes)) ?>" data-row-id="<?= h($k) ?>"
                        data-primary-id="<?= $id ?>" tabindex="0" aria-expanded="false"
                        <?= $editable ? 'data-edit-url="' . h($baseurl . '/attributes/edit/' . $id) . '"' : '' ?>
                        <?= $style !== '' ? 'style="' . h($style) . '"' : '' ?>>
                        <td class="fi-evw-c-sel">
                            <?= $this->element('genericElementsBS5/IndexTable/Fields/checkbox', [
                                'field' => $checkboxField,
                                'row' => $row,
                            ]) ?>
                        </td>
                        <td class="fi-evw-c-cat"><?= h($row['category'] ?? '') ?></td>
                        <td class="fi-evw-c-type"><?= h($row['type'] ?? '') ?></td>
                        <td class="fi-evw-c-value">
                            <div class="fi-evw-val">
                                <?php if ($isProposal): ?>
                                    <span class="fi-evw-flag is-proposal"><?= __('Proposal') ?></span>
                                <?php elseif ($isDeleted): ?>
                                    <span class="fi-evw-flag is-deleted"><?= __('Deleted') ?></span>
                                <?php endif; ?>
                                <?php if ($hoverId && !$_hoverClickOnly): ?>
                                    <span class="fi-evw-value om-hover-enrichment"
                                          data-hover-enrichment-id="<?= $hoverId ?>"
                                          data-hover-trigger="hover"><?= h($row['value'] ?? '') ?></span>
                                <?php else: ?>
                                    <span class="fi-evw-value" title="<?= h($row['value'] ?? '') ?>"><?= h($row['value'] ?? '') ?></span>
                                    <?php if ($hoverId): ?>
                                        <i class="fas fa-magnifying-glass-plus fi-evw-hover-btn om-hover-enrichment"
                                           role="button" tabindex="0"
                                           data-hover-enrichment-id="<?= $hoverId ?>"
                                           data-hover-trigger="click"
                                           title="<?= __('Look up enrichment') ?>"></i>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($row['warnings'])): ?>
                                    <i class="fas fa-triangle-exclamation fi-evw-warn"
                                       title="<?= h(__('Warninglist hit: %s', implode(', ', array_unique(array_column($row['warnings'], 'warninglist_name'))))) ?>"></i>
                                <?php endif; ?>
                                <?php if ($rowOrigin !== null && $rowOrigin['role'] !== 'self'): ?>
                                    <?= $this->element('Events/View/extension_origin', [
                                        'event_id' => (int)$row['event_id'],
                                        'compact' => true,
                                        'class' => 'fi-evw-origin',
                                    ]) ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($row['comment'])): ?>
                                <div class="fi-evw-comment" title="<?= h($row['comment']) ?>"><?= h($row['comment']) ?></div>
                            <?php endif; ?>
                            <?php if ($isProposal): ?>
                                <div class="fi-evw-prop">
                                    <?php if (!empty($row['proposal_org_name'])): ?>
                                        <span class="fi-faint"><?= h(__('by %s', $row['proposal_org_name'])) ?></span>
                                    <?php endif; ?>
                                    <?= $proposalActions($row['proposal_id'] ?? $id) ?>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($row['ShadowAttribute'] ?? [] as $p):
                                $isDeleteProposal = !empty($p['proposal_to_delete']);
                                $diffs = $isDeleteProposal ? [] : $proposalDiffs($p, $row); ?>
                                <div class="fi-evw-prop">
                                    <span class="fi-evw-flag is-proposal">
                                        <?= $isDeleteProposal ? __('Deletion proposed') : __('Proposed change') ?>
                                    </span>
                                    <?php if ($isDeleteProposal): ?>
                                        <span class="text-danger"><?= __('Remove this attribute') ?></span>
                                    <?php elseif (empty($diffs)): ?>
                                        <span class="fi-mono"><?= h($p['value'] ?? '') ?></span>
                                    <?php else: foreach ($diffs as [$label, $old, $new]): ?>
                                        <span>
                                            <span class="fi-faint"><?= h($label) ?>:</span>
                                            <?php if ($old !== ''): ?><del class="fi-faint"><?= h($old) ?></del> &rarr;<?php endif; ?>
                                            <strong><?= h($new) ?></strong>
                                        </span>
                                    <?php endforeach; endif; ?>
                                    <span class="fi-faint"><?= h(__('by %s', $p['org_name'] ?? ($p['org_id'] ?? ''))) ?></span>
                                    <?= $proposalActions($p['id']) ?>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td class="fi-evw-c-tags">
                            <div class="tag-container fi-evw-tags">
                                <?php foreach ($tags as $i => $at):
                                    $tag = $at['Tag'];
                                    $colour = !empty($tag['colour']) ? $tag['colour'] : '#0088cc'; ?>
                                    <span class="fi-evw-tag<?= $i >= $maxTags ? ' d-none extra-tag' : '' ?>"
                                          title="<?= h(trim(($at['relationship_type'] ?? '') . ' ' . $tag['name'])) ?>"
                                          style="background-color:<?= h($colour) ?>;color:<?= h($this->TextColour->getTextColour($colour)) ?>;">
                                        <?php if (!empty($at['local'])): ?><i class="fas fa-user"></i><?php endif; ?>
                                        <?= h($tag['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($tags) > $maxTags): ?>
                                    <span class="fi-evw-tag fi-evw-tag-more" role="button" tabindex="0"
                                          onclick="toggleTags(this)">+<?= count($tags) - $maxTags ?></span>
                                <?php endif; ?>
                                <?php if ($mayTag): ?>
                                    <button type="button" class="fi-evw-tag-add"
                                            title="<?= __('Add a tag') ?>" aria-label="<?= __('Add a tag') ?>"
                                            onclick="openModal('<?= h($baseurl . '/attributes/editAttributeTags/' . $id) ?>', 'xl');">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="fi-evw-c-ids">
                            <?php if (!$isProposal): ?>
                                <?= $this->element('genericElementsBS5/IndexTable/Fields/ids', [
                                    'field' => ['data_path' => 'to_ids'],
                                    'row' => $row,
                                    'viewMode' => 'table',
                                ]) ?>
                            <?php endif; ?>
                        </td>
                        <td class="fi-evw-c-corr">
                            <button type="button" class="fi-evw-corr fi-num<?= $corrCount ? '' : ' is-zero' ?>"
                                    data-fi-evw-expand
                                    title="<?= h(__n('%s correlated event', '%s correlated events', $corrCount, $corrCount)) ?>"><?= $corrCount ?></button>
                        </td>
                        <td class="fi-evw-c-act">
                            <?php if (!$isProposal): ?>
                                <?= $this->element('genericElementsBS5/IndexTable/Fields/row_actions', [
                                    'field' => $actionsField,
                                    'row' => $row,
                                ]) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="fi-evw-detail" hidden>
                        <td colspan="<?= $colCount ?>">
                            <?php
                            $items = [];
                            $dist = (int)($row['distribution'] ?? 0);
                            $items[__('Distribution')] = $field('distribution', $row, ['data_path' => 'distribution'])
                                . ($dist === 4 && !empty($row['SharingGroup']['name'])
                                    ? ' <span class="fi-dim">' . h($row['SharingGroup']['name']) . '</span>' : '');
                            if (!empty($row['timestamp'])) {
                                $items[__('Last modified')] = '<span class="fi-mono">'
                                    . h(date('Y-m-d H:i:s', (int)$row['timestamp'])) . '</span>';
                            }
                            if (!empty($row['first_seen']) || !empty($row['last_seen'])) {
                                $items[__('First / last seen')] = '<span class="fi-mono">'
                                    . h(($row['first_seen'] ?: '–') . ' → ' . ($row['last_seen'] ?: '–')) . '</span>';
                            }
                            if (!empty($row['uuid'])) {
                                $items[__('UUID')] = '<span class="fi-mono">' . h($row['uuid']) . '</span>'
                                    . ' <button type="button" class="fi-evw-copy" title="' . h(__('Copy UUID')) . '"'
                                    . ' onclick="copyValueToClipboard(\'' . h($row['uuid']) . '\', \'' . h(__('UUID copied to clipboard')) . '\')">'
                                    . '<i class="fas fa-copy"></i></button>';
                            }
                            if ($inExtensionView) {
                                $items[__('Event')] = $this->element('Events/View/extension_origin', [
                                    'event_id' => (int)($row['event_id'] ?? 0),
                                ]);
                            }
                            if (!$isProposal) {
                                $items[__('Correlation')] = $field('correlate', $row, ['data_path' => 'disable_correlation']);
                                $items[__('Correlated events')] = $field('relatedEvents', $row);
                                $items[__('Feed hits')] = $field('feedHits', ['Attribute' => $row]);
                                $items[__('Sightings')] = $field('sightings', $row, ['sightings' => ['data' => [], 'csv' => []]]);
                                $items[__('Analyst data')] = $field('analyst_data_badges', $row, $analystField);
                                $items[__('Tags')] = $field('tag_list', $row, $tagListField);
                                $items[__('Galaxies')] = $field('galaxy', $row, $galaxyField);
                            }
                            if (!empty($row['warnings'])) {
                                $links = [];
                                foreach ($row['warnings'] as $w) {
                                    $links[(int)$w['warninglist_id']] = sprintf(
                                        '<a href="%s/warninglists/view/%d">%s</a>',
                                        h($baseurl), (int)$w['warninglist_id'], h($w['warninglist_name'])
                                    );
                                }
                                $items[__('Warninglists')] = implode(', ', $links);
                            }
                            ?>
                            <dl class="fi-evw-dgrid">
                                <?php foreach ($items as $label => $html): if (trim(strip_tags($html, '<i><button><img><input><canvas>')) === '') continue; ?>
                                    <div class="<?= in_array($label, [__('Tags'), __('Galaxies'), __('Correlated events'), __('Feed hits')], true) ? 'is-wide' : '' ?>">
                                        <dt><?= h($label) ?></dt>
                                        <dd><?= $html ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="fi-evw-pagination">
            <?= $this->element('genericElementsBS5/IndexTable/pagination') ?>
        </div>
    </div>
</div>
