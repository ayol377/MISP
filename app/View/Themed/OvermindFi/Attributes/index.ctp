<?php
/*
 * OvermindFi: attributes/index. A search (the POST from attributes/search, or
 * a later page of it carrying its search_token) gets the fi results screen;
 * the plain attribute list keeps Overmind's filterable index untouched.
 */
$named = $this->request->params['named'] ?? [];
$isSearch = $this->request->is('post')
    || !empty($named['search_token'])
    || !empty($this->request->query['search_token']);

if (!$isSearch || empty($search_token)) {
    echo $this->element('Attributes/index', [
        'attributes' => $attributes,
        'show_event_id' => true,
        'show_filters' => true,
    ]);
    return;
}

$this->set('headerTitle', __('Search results'));
$this->set('headerDescription', __('Attributes matching your search.'));

$isSiteAdmin = !empty($isSiteAdmin);
$canAdd = !empty($me['Role']['perm_add']);
$enrichment = (bool)Configure::read('Plugin.Enrichment_services_enable');
$cortex = (bool)Configure::read('Plugin.Cortex_services_enable');

// Every link the paginator builds (pages, sort headers) keeps the search.
$this->Paginator->options(['url' => ['search_token' => $search_token]]);

// ---- Summary -------------------------------------------------------------
$rows = array_column($attributes, 'Attribute');
$attrCount = count($rows);
$eventCount = count(array_unique(array_column($rows, 'event_id')));
$warnCount = count(array_filter($rows, function ($a) {
    return !empty($a['warnings']);
}));
$paging = $this->Paginator->params();
$page = (int)($paging['page'] ?? 1);
$excluded = !empty($params['enforceWarninglist']);

$summary = __n('%s attribute', '%s attributes', $attrCount, $attrCount)
    . ' ' . __n('across %s event', 'across %s events', $eventCount, $eventCount);
$summary .= $page > 1 || $this->Paginator->hasNext()
    ? ' ' . __('on page %s.', $page)
    : '.';
if ($excluded) {
    $summary .= ' ' . __('Values on a warninglist were excluded.');
} elseif ($warnCount > 0) {
    $summary .= ' ' . __n(
        '%s value matches a warninglist.',
        '%s values match a warninglist.',
        $warnCount,
        $warnCount
    );
}

// ---- Row permissions ------------------------------------------------------
$mayModify = function ($row) {
    return $this->Acl->canModifyEvent($row);
};
$live = function ($row) {
    return empty($row['Attribute']['deleted']);
};
// Proposals go to events someone else created, as upstream offers them.
$mayPropose = function ($row) use ($isSiteAdmin, $canAdd, $me, $live) {
    return $canAdd && $live($row)
        && ($isSiteAdmin || $row['Event']['orgc_id'] != $me['org_id']);
};

$fields = [
    [
        'element' => 'checkbox',
        'data_path' => 'Attribute.id',
    ],
    [
        'name' => __('Event'),
        'sort' => 'Attribute.event_id',
        'element' => 'custom',
        'class' => 'fi-as-event',
        'function' => function ($row) use ($baseurl) {
            $a = $row['Attribute'];
            return sprintf(
                '<a class="fi-mono" href="%s">#%s</a><div class="fi-as-info" title="%s">%s</div>',
                h($baseurl . '/events/view/' . $a['event_id'] . '/focus:' . $a['uuid']),
                h($a['event_id']),
                h($row['Event']['info'] ?? ''),
                h($row['Event']['info'] ?? '')
            );
        },
    ],
    [
        'name' => __('Org'),
        'sort' => 'Event.orgc_id',
        'data_path' => 'Event.Orgc',
        'element' => 'organisation',
    ],
    [
        'name' => __('Category'),
        'sort' => 'Attribute.category',
        'data_path' => 'Attribute.category',
        'element' => 'category',
    ],
    [
        'name' => __('Type'),
        'sort' => 'Attribute.type',
        'data_path' => 'Attribute.type',
        'element' => 'type',
    ],
    [
        'name' => __('Value'),
        'sort' => 'Attribute.value',
        'data_path' => 'Attribute',
        'element' => 'attribute_value',
    ],
    [
        'name' => __('Tags'),
        'data_path' => 'Attribute.AttributeTag',
        'element' => 'tag_list',
        'add_tag' => function ($row) {
            return $this->Acl->canModifyTag($row);
        },
        'add_tag_url' => $baseurl . '/attributes/editAttributeTags/%id%',
        'add_tag_id_path' => 'Attribute.id',
        'add_relationship_url' => $baseurl . '/attributes/editAttributeTagRelationships/%id%',
    ],
    [
        'name' => __('Galaxies'),
        'data_path' => 'Attribute.Galaxy',
        'element' => 'galaxy',
        'add_galaxy' => function ($row) {
            return $this->Acl->canModifyTag($row);
        },
        'add_galaxy_url' => $baseurl . '/attributes/editAttributeGalaxies/%id%',
        'add_galaxy_id_path' => 'Attribute.id',
        'add_galaxy_relationship_url' => $baseurl . '/attributes/editAttributeGalaxyRelationships/%id%',
    ],
    [
        'name' => __('Comment'),
        'element' => 'custom',
        'class' => 'fi-as-comment',
        'function' => function ($row) {
            return h($row['Attribute']['comment'] ?? '');
        },
    ],
    [
        'name' => __('IDS'),
        'data_path' => 'Attribute.to_ids',
        'element' => 'ids',
    ],
    [
        'name' => __('Correlate'),
        'data_path' => 'Attribute.disable_correlation',
        'element' => 'correlate',
    ],
    [
        'name' => __('Related events'),
        'element' => 'relatedEvents',
    ],
    [
        'name' => __('Feed hits'),
        'element' => 'feedHits',
    ],
    [
        'name' => __('Sightings'),
        'element' => 'sightings',
        'sightings' => $sightingsData ?? ['data' => [], 'csv' => []],
    ],
    [
        'name' => __('Date'),
        'sort' => 'Attribute.timestamp',
        'element' => 'custom',
        'class' => 'fi-as-date',
        'function' => function ($row) {
            $ts = (int)($row['Attribute']['timestamp'] ?? 0);
            return $ts ? '<span class="fi-mono">' . h(date('Y-m-d', $ts)) . '</span>' : '';
        },
    ],
    [
        'name' => __('Actions'),
        'element' => 'row_actions',
        'data_path' => 'Attribute.id',
        'actions' => [
            [
                'type' => 'copy',
                'label' => __('Copy UUID'),
                'icon' => 'copy',
                'data_path' => 'Attribute.uuid',
                'copy_message' => __('UUID copied to clipboard'),
            ],
            [
                'type' => 'navigate',
                'label' => __('View event'),
                'icon' => 'eye',
                'url' => $baseurl . '/events/view/%event%/focus:%uuid%',
                'url_params_data_paths' => ['event' => 'Attribute.event_id', 'uuid' => 'Attribute.uuid'],
            ],
            [
                'type' => 'divider',
                'requirement' => function ($row) use ($mayModify, $mayPropose, $live, $enrichment, $cortex) {
                    return $mayPropose($row)
                        || ($live($row) && $mayModify($row) && ($enrichment || $cortex));
                },
            ],
            [
                'type' => 'modal',
                'label' => __('Enrich'),
                'icon' => 'fas fa-wand-magic-sparkles text-enrichment',
                'url' => $baseurl . '/events/queryEnrichment/%id%/0/Enrichment/Attribute',
                'requirement' => function ($row) use ($mayModify, $live, $enrichment) {
                    return $enrichment && $live($row) && $mayModify($row);
                },
            ],
            [
                'type' => 'modal',
                'label' => __('Enrich (Cortex)'),
                'icon' => 'eye',
                'url' => $baseurl . '/events/queryEnrichment/%id%/0/Cortex/Attribute',
                'requirement' => function ($row) use ($mayModify, $live, $cortex) {
                    return $cortex && $live($row) && $mayModify($row);
                },
            ],
            [
                'type' => 'modal',
                'label' => __('Propose change'),
                'icon' => 'comment-dots',
                'url' => $baseurl . '/shadow_attributes/edit/%id%',
                'requirement' => $mayPropose,
            ],
            [
                'type' => 'js',
                'label' => __('Propose deletion'),
                'icon' => 'trash-can',
                'onclick' => 'fiProposeDeletion(%id%)',
                'requirement' => $mayPropose,
            ],
            [
                'type' => 'modal',
                'label' => __('Propose enrichment'),
                'icon' => 'fas fa-wand-magic-sparkles text-enrichment',
                'url' => $baseurl . '/events/queryEnrichment/%id%/0/Enrichment/ShadowAttribute',
                'requirement' => function ($row) use ($mayPropose, $enrichment) {
                    return $enrichment && $mayPropose($row);
                },
            ],
            [
                'type' => 'modal',
                'label' => __('Propose enrichment (Cortex)'),
                'icon' => 'eye',
                'url' => $baseurl . '/events/queryEnrichment/%id%/0/Cortex/ShadowAttribute',
                'requirement' => function ($row) use ($mayPropose, $cortex) {
                    return $cortex && $mayPropose($row);
                },
            ],
            [
                'type' => 'divider',
                'requirement' => $mayModify,
            ],
            [
                'type' => 'modal',
                'label' => __('Edit'),
                'icon' => 'pen-to-square',
                'url' => $baseurl . '/attributes/edit/%id%',
                'requirement' => function ($row) use ($mayModify, $live) {
                    return $live($row) && $mayModify($row);
                },
            ],
            [
                // Upstream offers a soft delete once the event has been
                // published, a permanent one before; the modal can flip it.
                'type' => 'modal',
                'label' => __('Delete'),
                'icon' => 'trash',
                'url' => $baseurl . '/attributes/delete/%id%%hard%',
                'url_params_data_paths' => ['hard' => 'Attribute.fi_hard'],
                'class' => 'text-danger',
                'requirement' => function ($row) use ($mayModify, $live) {
                    return $live($row) && $mayModify($row);
                },
            ],
        ],
    ],
];

// row_actions only substitutes data paths, so precompute the delete suffix.
foreach ($attributes as &$attribute) {
    $attribute['Attribute']['fi_hard'] = empty($attribute['Event']['publish_timestamp']) ? '/true' : '';
}
unset($attribute);

$hasPrev = $this->Paginator->hasPrev();
$hasNext = $this->Paginator->hasNext();
?>
<div class="container-fluid pb-4 fi-as fi-as-results">
    <div class="fi-as-toolbar">
        <a class="btn btn-primary" href="<?= h($baseurl . '/attributes/search') ?>">
            <i class="fa-solid fa-magnifying-glass me-1"></i><?= __('New search') ?>
        </a>
        <div class="fi-as-export">
            <label class="fi-faint" for="fiAsExportFormat"><?= __('Download results as') ?></label>
            <select id="fiAsExportFormat" class="form-select form-select-sm">
                <?php foreach ($exports as $format): ?>
                    <option value="<?= h($format) ?>"<?= $format === 'json' ? ' selected' : '' ?>><?= h($format) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="fiAsExportButton">
                <i class="fas fa-download me-1"></i><?= __('Download') ?>
            </button>
        </div>
    </div>

    <div class="alert alert-info fi-as-summary" role="status">
        <i class="fa-solid fa-circle-info me-2"></i><?= h($summary) ?>
    </div>

    <?= $this->element('genericElementsBS5/IndexTable/multi_select_toolbar', [
        'filter_bar' => ['soft_delete' => '/deleteSelection'],
        'item_url' => '/attributes',
    ]) ?>

    <div class="fi-panel fi-as-table">
        <?= $this->element('genericElementsBS5/IndexTable/index_table', [
            'scaffold_data' => [
                'data' => [
                    'data' => $attributes,
                    'primary_id_path' => 'Attribute.id',
                    'fields' => $fields,
                    'row_class_callable' => function ($row) {
                        return empty($row['Attribute']['warnings']) ? '' : 'fi-as-warn';
                    },
                ],
            ],
        ]) ?>
    </div>

    <?php if ($hasPrev || $hasNext): ?>
        <nav class="fi-as-pager" aria-label="<?= __('Pagination') ?>">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item<?= $hasPrev ? '' : ' disabled' ?>">
                    <?= $hasPrev
                        ? $this->Paginator->prev('<i class="fas fa-chevron-left me-1"></i>' . __('Previous'), ['class' => 'page-link', 'escape' => false, 'tag' => false])
                        : '<span class="page-link"><i class="fas fa-chevron-left me-1"></i>' . __('Previous') . '</span>' ?>
                </li>
                <li class="page-item active"><span class="page-link fi-mono"><?= $page ?></span></li>
                <li class="page-item<?= $hasNext ? '' : ' disabled' ?>">
                    <?= $hasNext
                        ? $this->Paginator->next(__('Next') . '<i class="fas fa-chevron-right ms-1"></i>', ['class' => 'page-link', 'escape' => false, 'tag' => false])
                        : '<span class="page-link">' . __('Next') . '<i class="fas fa-chevron-right ms-1"></i></span>' ?>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<script>
var selectedItems = new Map();

document.getElementById('fiAsExportButton').addEventListener('click', function () {
    var format = document.getElementById('fiAsExportFormat').value;
    window.location.href = baseurl + '/attributes/restSearch/returnFormat:'
        + encodeURIComponent(format) + '/search_token:'
        + encodeURIComponent(<?= json_encode($search_token) ?>);
});

function fiProposeDeletion(id) {
    showConfirmModal({
        title: <?= json_encode(__('Propose deletion')) ?>,
        body: escapeHtml(<?= json_encode(__('Propose to delete attribute #%s?')) ?>.replace('%s', id)),
        confirmLabel: <?= json_encode(__('Propose')) ?>,
        confirmClass: 'btn-danger',
        onConfirm: function () {
            fetch(baseurl + '/shadow_attributes/delete/' + encodeURIComponent(id), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                }
            })
                .then(function (r) { return r.json().catch(function () { return {}; }); })
                .then(function (resp) {
                    if (resp && resp.saved) {
                        showToast(escapeHtml(resp.success), 'success');
                    } else {
                        showToast(escapeHtml((resp && resp.errors) || <?= json_encode(__('Could not create the proposal.')) ?>), 'danger');
                    }
                })
                .catch(function () {
                    showToast(escapeHtml(<?= json_encode(__('Could not create the proposal.')) ?>), 'danger');
                });
        }
    });
}
</script>
