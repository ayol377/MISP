<?php
/*
 * OvermindFi event view (mockup 1e). Started from
 * Themed/Overmind/Events/view2.ctp: same lazy sections, same tab ids
 * (#tab-attributes, #tab-correlation, ... are targeted by other scripts),
 * laid out as header + metadata strip + tabs + context rail.
 */
$this->set('hideHeaderSection', true);

$ev = $event['Event'];
$eventId = (int)$ev['id'];
$orgc = $event['Orgc'] ?? [];
$extensionSuffix = $extensionSuffix ?? '';

$mayModify = $this->Acl->canModifyEvent($event);
$canEdit = $isSiteAdmin || $mayModify;
$mayPublish = $isSiteAdmin
    || ($mayModify && $this->Acl->canPublishEvent($event));
$isPublished = !empty($ev['published']);
$canPropose = !$canEdit && !empty($me['Role']['perm_add']);

$modal = function ($url, $size = null) {
    return sprintf(
        "event.preventDefault(); openModal('%s'%s);",
        $url,
        $size === null ? '' : ", '" . $size . "'"
    );
};
$base = $baseurl . '/events';

$threatId = (int)($ev['threat_level_id'] ?? 4);
$threatTone = [1 => 'high', 2 => 'medium', 3 => 'low'][$threatId] ?? 'undef';
$threatName = $event['ThreatLevel']['name'] ?? __('Undefined');
$analysis = (int)($ev['analysis'] ?? 0);
$distribution = (int)($ev['distribution'] ?? 0);
if ($distribution === 4 && !empty($event['SharingGroup']['name'])) {
    $distributionHtml = sprintf(
        '<a href="%s/sharing_groups/view/%s">%s</a>',
        h($baseurl),
        h($event['SharingGroup']['id']),
        h($event['SharingGroup']['name'])
    );
} else {
    $distributionHtml = h($distributionLevels[$distribution] ?? $distribution);
}

$orgLogo = $this->OrgImg->getOrgLogoV2($orgc, 32);
$orgInitials = strtoupper(substr(
    preg_replace('/[^A-Za-z0-9]/', '', $orgc['name'] ?? '?'),
    0,
    2
));

echo $this->element('genericElements/assetLoader', [
    'js'  => ['markdown-it', 'font-awesome-helper', 'misp-report-markdown', 'Chart.min']
]);

// Extended / extending view: say so, and carry the mode into every lazy
// tab so a tab load never drops back to the atomic view.
echo $this->element('Events/View/extension_banner');

/*
 * Tabs. The first seven follow the mockup, the rest (sections Overmind
 * shows on its General tab) sit under "More". Content entries are an
 * element name or ['ajax' => url] like view_layout.ctp.
 */
$ajax = function ($action) use ($base, $eventId, $extensionSuffix) {
    return ['ajax' => sprintf('%s/%s/%d%s', $base, $action, $eventId, $extensionSuffix)];
};
$tabs = [
    'attributes' => [__('Attributes'), $attribute_count ?? 0, $ajax('viewAttributes')],
    'objects' => [__('Objects'), $object_count ?? 0, $ajax('viewObjects')],
    'galaxies' => [__('Galaxies'), null, 'Events/View/event_galaxies'],
    'correlation' => [__('Correlations'), $correlation_count ?? 0, 'Events/View/event_correlation_graph'],
    'reports' => [__('Report'), $report_count ?? 0, $ajax('viewEventReports')],
    'sightings' => [__('Sightings'), null, 'Events/View/event_sightings'],
    'history' => [__('History'), null, 'Events/View/event_history'],
];
$moreTabs = [
    'tags' => [__('Tags'), null, 'Events/View/event_tags'],
    'attachments' => [__('Attachments'), null, 'Events/View/event_attachments'],
    'warninglists' => [__('Warninglist hits'), null, 'Events/View/event_warninglists'],
    'analyst-data' => [__('Analyst data'), null, 'Events/View/event_analyst_data'],
    'collections' => [__('Collections'), null, 'Events/View/event_collections'],
    'pivot-explorer' => [__('Pivot explorer'), null, 'Events/View/event_pivot_explorer'],
];

// Per-tab "add" text button beside the tabs (Overmind's tab-scoped header actions).
$tabAdds = [];
if ($canEdit) {
    $tabAdds = [
        'attributes' => [__('Add attribute'), "$baseurl/attributes/add/$eventId", 'action-add-attribute'],
        'objects' => [__('Add object'), "$baseurl/objects/add/$eventId", null],
        'reports' => [__('Add report'), "$baseurl/event_reports/add/$eventId", null],
    ];
}
?>

<div class="fi-evw" data-event-id="<?= $eventId ?>">

    <header class="fi-evw-head">
        <div class="fi-evw-titleblock" data-tour="page-title">
            <nav class="fi-evw-crumb">
                <a href="<?= h($base . '/index') ?>"><?= __('Events') ?></a>
                <span>/</span>
                <span class="fi-mono">#<?= $eventId ?></span>
            </nav>
            <h1 class="fi-evw-title">
                <span class="fi-evw-logo">
                    <?= $orgLogo !== '' ? $orgLogo : h($orgInitials) ?>
                </span>
                <span class="fi-evw-info"><?= h($ev['info']) ?></span>
            </h1>
        </div>

        <div class="fi-evw-actions" data-tour="page-actions">
            <?php if ($canEdit && Configure::read('Plugin.Enrichment_services_enable')): ?>
                <a class="btn fi-btn-text"
                   href="<?= h("$base/enrichEvent/$eventId") ?>"
                   onclick="<?= $modal("$base/enrichEvent/$eventId") ?>">
                    <i class="fas fa-wand-magic-sparkles"></i> <?= __('Enrich') ?>
                </a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary"
               href="<?= h("$base/exportChoice/$eventId") ?>"
               onclick="<?= $modal("$base/exportChoice/$eventId", 'md') ?>">
                <?= __('Export') ?>
            </a>
            <?php if ($canPropose): ?>
                <a class="btn btn-outline-secondary"
                   href="<?= h("$baseurl/shadow_attributes/add/$eventId") ?>"
                   title="<?= h(__('Propose a new attribute to the event creator')) ?>">
                    <?= __('Propose change') ?>
                </a>
            <?php endif; ?>
            <?= $this->element('Events/View/event_actions', ['data' => $event]) ?>
            <?php if ($mayPublish && !$isPublished): ?>
                <a class="btn btn-primary" href="#" data-tour="action-publish"
                   onclick="<?= $modal("$base/publish/$eventId", 'md') ?>">
                    <?= __('Publish') ?>
                </a>
            <?php elseif ($mayPublish): ?>
                <a class="btn btn-outline-secondary" href="#"
                   data-tour="action-unpublish"
                   onclick="<?= $modal("$base/unpublish/$eventId", 'md') ?>">
                    <?= __('Unpublish') ?>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <dl class="fi-evw-meta">
        <div>
            <dt><?= __('Creator') ?></dt>
            <dd>
                <a href="<?= h($baseurl . '/organisations/view/' . ($orgc['id'] ?? '')) ?>">
                    <?= h($orgc['name'] ?? '') ?>
                </a>
            </dd>
        </div>
        <div>
            <dt><?= __('Date') ?></dt>
            <dd class="fi-mono"><?= h($ev['date'] ?? '') ?></dd>
        </div>
        <div>
            <dt><?= __('Threat') ?></dt>
            <dd class="fi-evw-threat fi-evw-threat-<?= $threatTone ?>"><?= h($threatName) ?></dd>
        </div>
        <div>
            <dt><?= __('Analysis') ?></dt>
            <dd><?= h($analysisLevels[$analysis] ?? $analysis) ?></dd>
        </div>
        <div>
            <dt><?= __('Distribution') ?></dt>
            <dd><?= $distributionHtml ?></dd>
        </div>
        <div>
            <dt><?= __('State') ?></dt>
            <dd><?= $isPublished ? __('Published') : __('Unpublished') ?></dd>
        </div>
        <button type="button" class="btn fi-btn-text fi-evw-details-toggle collapsed"
                data-bs-toggle="collapse" data-bs-target="#fi-evw-details"
                aria-expanded="false" aria-controls="fi-evw-details">
            <?= __('Details') ?> <i class="fas fa-chevron-down"></i>
        </button>
    </dl>

    <div class="collapse fi-evw-details" id="fi-evw-details">
        <?= $this->element('Events/View/event_general', ['data' => $event]) ?>
    </div>

    <div class="fi-evw-body">
        <section class="fi-evw-main">
            <div class="fi-evw-tabbar">
                <ul class="nav nav-tabs fi-evw-tabs" role="tablist" data-tour="view-tabs">
                    <?php $first = true; foreach ($tabs as $id => [$title, $count]): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link nav-view<?= $first ? ' active' : '' ?>"
                               data-bs-toggle="tab" role="tab"
                               data-tour="view-tab-<?= h($id) ?>"
                               href="#tab-<?= h($id) ?>"
                               aria-selected="<?= $first ? 'true' : 'false' ?>">
                                <?= h($title) ?>
                                <?php if (!empty($count)): ?>
                                    <span class="fi-evw-tabcount fi-mono"><?= h($count) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php $first = false; endforeach; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                           href="#" role="button" aria-expanded="false">
                            <?= __('More') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($moreTabs as $id => [$title]): ?>
                                <li>
                                    <a class="dropdown-item nav-view" data-bs-toggle="tab"
                                       role="tab" href="#tab-<?= h($id) ?>"
                                       data-tour="view-tab-<?= h($id) ?>">
                                        <?= h($title) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                </ul>
                <div class="fi-evw-tabtools">
                    <button type="button" class="btn fi-btn-text" id="fi-evw-filter">
                        <i class="fas fa-sliders"></i> <?= __('Filter') ?>
                    </button>
                    <?php foreach ($tabAdds as $id => [$label, $url, $tour]): ?>
                        <a class="btn fi-btn-text<?= $id === 'attributes' ? '' : ' d-none' ?>"
                           data-header-tab="<?= h($id) ?>"
                           <?= $tour ? 'data-tour="' . h($tour) . '"' : '' ?>
                           href="<?= h($url) ?>" onclick="<?= $modal($url) ?>">
                            <i class="fas fa-plus"></i> <?= h($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-content">
                <?php $first = true; foreach ($tabs + $moreTabs as $id => [, , $content]): ?>
                    <div class="tab-pane fade<?= $first ? ' show active' : '' ?>"
                         id="tab-<?= h($id) ?>" role="tabpanel">
                        <?php if (is_array($content)): ?>
                            <div class="ajax-tab-content" data-url="<?= h($content['ajax']) ?>">
                                <div class="text-center p-4"><div class="spinner-border"></div></div>
                            </div>
                        <?php else: ?>
                            <?= $this->element($content, ['data' => $event]) ?>
                        <?php endif; ?>
                    </div>
                <?php $first = false; endforeach; ?>
            </div>
        </section>

        <?= $this->element('fi/event_view/rail', ['event' => $event]) ?>
    </div>
</div>

<script>
(function () {
    // Tab <-> URL hash, and the per-tab "add" button (view_layout.ctp's
    // script, extended to tabs that live in the "More" dropdown).
    function tabLink(hash) {
        return document.querySelector('.fi-evw-tabs [data-bs-toggle="tab"][href="' + hash + '"]');
    }
    function activateFromHash() {
        var link = window.location.hash ? tabLink(window.location.hash) : null;
        if (link) bootstrap.Tab.getOrCreateInstance(link).show();
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
    document.addEventListener('DOMContentLoaded', function () {
        activateFromHash();
        syncTabActions(currentTabId());
        document.querySelectorAll('.fi-evw-tabs [data-bs-toggle="tab"]').forEach(function (t) {
            t.addEventListener('shown.bs.tab', function (e) {
                var href = e.target.getAttribute('href');
                history.replaceState(null, '', href);
                syncTabActions(href.replace('#tab-', ''));
            });
        });
        // "Filter": focus the active tab's filter box, falling back to the
        // attributes tab when the active one has none.
        document.getElementById('fi-evw-filter').addEventListener('click', function () {
            var pane = document.querySelector('.fi-evw .tab-pane.active');
            var field = pane && pane.querySelector('#filterField, input[type="search"]');
            if (!field) {
                var link = tabLink('#tab-attributes');
                bootstrap.Tab.getOrCreateInstance(link).show();
                field = document.querySelector('#tab-attributes #filterField');
            }
            if (field) {
                field.scrollIntoView({ block: 'center', behavior: 'smooth' });
                field.focus();
            }
        });
    });
    window.addEventListener('hashchange', function () {
        activateFromHash();
        syncTabActions(currentTabId());
    });
}());
</script>
