<?php
/*
 * OvermindFi event view (mockup 1e). Own markup, same data as
 * Themed/Overmind/Events/view2.ctp: the same lazy sections under the same
 * tab ids (#tab-attributes, #tab-correlation, ... are targeted by other
 * scripts), laid out as header + metadata strip + pill tabs + context rail.
 * Behaviour lives in js/fi/event-view.js.
 */
$this->set('hideHeaderSection', true);
$this->set('additionalJs', ['fi/event-view']);

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
$editUrl = "$base/edit/$eventId";

$threatName = $event['ThreatLevel']['name'] ?? __('Undefined');
$analysis = (int)($ev['analysis'] ?? 0);
$distribution = (int)($ev['distribution'] ?? 0);
$distributionLabel = ($distribution === 4 && !empty($event['SharingGroup']['name']))
    ? $event['SharingGroup']['name']
    : ($distributionLevels[$distribution] ?? $distribution);

$orgLogo = $this->OrgImg->getOrgLogoV2($orgc, 32);
$orgInitials = strtoupper(substr(
    preg_replace('/[^A-Za-z0-9]/', '', $orgc['name'] ?? '?'),
    0,
    2
));

/*
 * Metadata strip: label + pill. A pill opens the event edit form when the
 * user may edit, and is plain text otherwise; the creator pill always goes
 * to the organisation.
 */
$metaPills = [
    [__('Creator'), $orgc['name'] ?? '', $baseurl . '/organisations/view/' . ($orgc['id'] ?? '')],
    [__('Date'), $ev['date'] ?? '', null],
    [__('Threat'), ucfirst(strtolower($threatName)), null],
    [__('Analysis'), $analysisLevels[$analysis] ?? $analysis, null],
    [__('Distribution'), $distributionLabel, null],
];

echo $this->element('genericElements/assetLoader', [
    'js'  => ['markdown-it', 'font-awesome-helper', 'misp-report-markdown', 'Chart.min']
]);

// Extended / extending view: say so, and carry the mode into every lazy
// tab so a tab load never drops back to the atomic view.
echo $this->element('Events/View/extension_banner');

/*
 * Tabs. The first seven follow the mockup, the rest (the sections Overmind
 * shows on its General tab) sit under "More". Content entries are an
 * element name or ['ajax' => url] like view_layout.ctp. The counts ride
 * the tab's title: the mockup shows bare labels.
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
    'details' => [__('Event details'), null, 'Events/View/event_general'],
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
            <nav class="fi-evw-crumb" aria-label="<?= __('Breadcrumb') ?>">
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
                <a class="fi-evw-btn fi-evw-btn-text"
                   href="<?= h("$base/enrichEvent/$eventId") ?>"
                   onclick="<?= $modal("$base/enrichEvent/$eventId") ?>">
                    <i class="fas fa-wand-magic-sparkles"></i><?= __('Enrich') ?>
                </a>
            <?php endif; ?>
            <a class="fi-evw-btn fi-evw-btn-ghost"
               href="<?= h("$base/exportChoice/$eventId") ?>"
               onclick="<?= $modal("$base/exportChoice/$eventId", 'md') ?>">
                <?= __('Export') ?>
            </a>
            <?php if ($canPropose): ?>
                <a class="fi-evw-btn fi-evw-btn-ghost"
                   href="<?= h("$baseurl/shadow_attributes/add/$eventId") ?>"
                   title="<?= h(__('Propose a new attribute to the event creator')) ?>">
                    <?= __('Propose change') ?>
                </a>
            <?php endif; ?>
            <?php if ($mayPublish && !$isPublished): ?>
                <a class="fi-evw-btn fi-evw-btn-primary" href="#" data-tour="action-publish"
                   onclick="<?= $modal("$base/publish/$eventId", 'md') ?>">
                    <?= __('Publish') ?>
                </a>
            <?php elseif ($mayPublish): ?>
                <a class="fi-evw-btn fi-evw-btn-ghost" href="#"
                   data-tour="action-unpublish"
                   onclick="<?= $modal("$base/unpublish/$eventId", 'md') ?>">
                    <?= __('Unpublish') ?>
                </a>
            <?php endif; ?>
            <?= $this->element('Events/View/event_actions', ['data' => $event]) ?>
        </div>
    </header>

    <dl class="fi-evw-meta">
        <?php foreach ($metaPills as [$label, $value, $link]): ?>
            <div>
                <dt><?= h($label) ?></dt>
                <dd>
                    <?php if ($link !== null): ?>
                        <a class="fi-evw-pill" href="<?= h($link) ?>"><?= h($value) ?></a>
                    <?php elseif ($canEdit): ?>
                        <a class="fi-evw-pill" href="<?= h($editUrl) ?>"
                           title="<?= h(__('Edit event')) ?>"
                           onclick="<?= $modal($editUrl) ?>"><?= h($value) ?></a>
                    <?php else: ?>
                        <span class="fi-evw-pill is-static"><?= h($value) ?></span>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>

    <div class="fi-evw-body">
        <section class="fi-evw-main">
            <div class="fi-evw-tabbar">
                <ul class="nav fi-evw-tabs" role="tablist" data-tour="view-tabs">
                    <?php $first = true; foreach ($tabs as $id => [$title, $count]): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link nav-view<?= $first ? ' active' : '' ?>"
                               data-bs-toggle="tab" role="tab"
                               data-tour="view-tab-<?= h($id) ?>"
                               href="#tab-<?= h($id) ?>"
                               <?= empty($count) ? '' : 'title="' . h(number_format((int)$count)) . '"' ?>
                               aria-selected="<?= $first ? 'true' : 'false' ?>"><?= h($title) ?></a>
                        </li>
                    <?php $first = false; endforeach; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                           href="#" role="button" aria-expanded="false"><?= __('More') ?></a>
                        <ul class="dropdown-menu">
                            <?php foreach ($moreTabs as $id => [$title]): ?>
                                <li>
                                    <a class="dropdown-item nav-view" data-bs-toggle="tab"
                                       role="tab" href="#tab-<?= h($id) ?>"
                                       data-tour="view-tab-<?= h($id) ?>"><?= h($title) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                </ul>
                <div class="fi-evw-tabtools">
                    <button type="button" class="fi-evw-btn fi-evw-btn-text" id="fi-evw-filter"
                            data-header-tab="attributes" aria-expanded="false">
                        <i class="fas fa-sliders"></i><?= __('Filter') ?>
                    </button>
                    <?php foreach ($tabAdds as $id => [$label, $url, $tour]): ?>
                        <a class="fi-evw-btn fi-evw-btn-text<?= $id === 'attributes' ? '' : ' d-none' ?>"
                           data-header-tab="<?= h($id) ?>"
                           <?= $tour ? 'data-tour="' . h($tour) . '"' : '' ?>
                           href="<?= h($url) ?>" onclick="<?= $modal($url) ?>">
                            <i class="far fa-square-plus"></i><?= h($label) ?>
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
