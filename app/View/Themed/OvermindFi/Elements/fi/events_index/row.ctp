<?php
/*
 * One row of the OvermindFi events index (mockup 1c).
 *
 * $event, $k        : the paginated row and its position
 * $viewUrl          : view URL with %id%
 * $show             : ['tags', 'clusters', 'attr', 'corr'] => bool
 * $extendedEvents   : uuid => Event, from the controller
 *
 * Per-row rights are the ones upstream's row_actions / checkbox fields
 * compute: canModifyEvent() for edit/delete, plus canPublishEvent() for
 * (un)publish, site admins always.
 */
$e = $event['Event'];
$id = (int)$e['id'];
$rowUrl = str_replace('%id%', $id, $viewUrl);
$mayModify = $this->Acl->canModifyEvent($event);
$canEdit = !empty($isSiteAdmin) || $mayModify;
$canPublish = !empty($isSiteAdmin) || ($mayModify && $this->Acl->canPublishEvent($event));

$threatId = (int)$e['threat_level_id'];
$threatKinds = [1 => 'high', 2 => 'medium', 3 => 'low', 4 => 'undefined'];
$threatNames = [1 => __('High'), 2 => __('Medium'), 3 => __('Low'), 4 => __('Undefined')];
$threatLabel = $event['ThreatLevel']['name'] ?? ($threatNames[$threatId] ?? '');

$orgc = $event['Orgc'] ?? [];
$logo = empty($orgc) ? '' : $this->OrgImg->getOrgLogoV2($orgc, 18, false);
$initials = '';
if (!empty($orgc['name'])) {
    $words = preg_split('/[\s\-_]+/u', trim($orgc['name']), -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_substr($word, 0, 1);
    }
    $initials = mb_strtoupper($initials);
}

$tags = [];
if ($show['tags']) {
    foreach (($event['EventTag'] ?? []) as $eventTag) {
        if (!empty($eventTag['Tag']) && empty($eventTag['Tag']['is_galaxy'])) {
            $tags[] = $eventTag;
        }
    }
}
$clusters = $show['clusters'] ? ($event['GalaxyCluster'] ?? []) : [];
$maxTags = 4;
$extended = !empty($e['extends_uuid']) ? ($extendedEvents[$e['extends_uuid']] ?? null) : null;

$proposals = (int)($e['proposals_count'] ?? 0);
$distribution = (int)$e['distribution'];
$distLabel = ($distribution === 4 && !empty($event['SharingGroup']['name']))
    ? $event['SharingGroup']['name']
    : ($shortDist[$distribution] ?? '');
$state = $e['published'] ? __('Published') : __('Draft');
$stateTitle = $state . ($distLabel !== '' ? ' · ' . $distLabel : '')
    . ($proposals ? ' · ' . __n('%s proposal', '%s proposals', $proposals, $proposals) : '');
$objects = (int)($e['object_count'] ?? 0);
?>
<tr data-primary-id="<?= $id ?>" data-row-id="<?= h($k) ?>">
    <td class="fi-ev-c-id">
        <input type="checkbox" class="item-checkbox form-check-input"
               data-item-id="<?= $id ?>"
               data-can-delete="<?= $mayModify ? '1' : '0' ?>"
               data-publish="<?= $e['published'] ? '1' : '0' ?>"
               aria-label="<?= h(__('Select event %s', $id)) ?>">
        <a class="fi-mono" href="<?= h($rowUrl) ?>"><?= $id ?></a>
    </td>
    <td class="fi-ev-c-info">
        <a class="fi-ev-info" href="<?= h($rowUrl) ?>" title="<?= h($e['info']) ?>"><?= h($e['info']) ?></a>
        <?php if ($tags || $clusters || !empty($e['extends_uuid'])): ?>
            <div class="fi-ev-tags tag-container">
                <?php foreach ($tags as $i => $eventTag):
                    $tag = $eventTag['Tag'];
                    $colour = !empty($tag['colour']) ? $tag['colour'] : '#0088cc'; ?>
                    <a class="fi-ev-tag<?= $i >= $maxTags ? ' d-none extra-tag' : '' ?>"
                       href="<?= h($baseurl . '/events/index/searchtag:' . $tag['id']) ?>"
                       title="<?= h($tag['name']) ?>"
                       style="background-color:<?= h($colour) ?>;color:<?= h($this->TextColour->getTextColour($colour)) ?>;"><?php
                        if (!empty($eventTag['local'])): ?><i class="fas fa-user" title="<?= __('Local tag') ?>"></i><?php endif;
                        ?><?= h($tag['name']) ?></a>
                <?php endforeach; ?>
                <?php if (count($tags) > $maxTags): ?>
                    <button type="button" class="fi-ev-tag fi-ev-tag-more"
                            onclick="toggleTags(this)">+<?= count($tags) - $maxTags ?></button>
                <?php endif; ?>
                <?php foreach ($clusters as $cluster):
                    $galaxy = $cluster['Galaxy'] ?? []; ?>
                    <a class="fi-ev-cluster"
                       href="<?= h($baseurl . '/galaxy_clusters/view/' . (int)($cluster['id'] ?? 0)) ?>"
                       title="<?= h(($galaxy['name'] ?? '') . ' · ' . ($cluster['value'] ?? '')) ?>">
                        <i class="fas fa-<?= h($galaxy['icon'] ?? 'globe') ?>"></i><?php
                        if (!empty($cluster['local'])): ?><i class="fas fa-user"></i><?php endif;
                        ?><?= h($cluster['value'] ?? '') ?></a>
                <?php endforeach; ?>
                <?php if (!empty($e['extends_uuid'])): ?>
                    <span class="fi-ev-extends" title="<?= __('Extends') ?>">
                        <i class="fas fa-code-branch"></i>
                        <?php if ($extended): ?>
                            <a href="<?= h(str_replace('%id%', (int)$extended['id'], $viewUrl)) ?>"><?= h($extended['info']) ?></a>
                        <?php else: ?>
                            <span class="fi-mono"><?= h($e['extends_uuid']) ?></span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </td>
    <td class="fi-ev-c-org">
        <?php if (!empty($orgc)): ?>
            <a class="fi-ev-org" href="<?= h($baseurl . '/organisations/view/' . $orgc['id']) ?>" title="<?= h($orgc['name']) ?>">
                <span class="fi-ev-tile" aria-hidden="true"><?= $logo !== '' ? $logo : h($initials) ?></span>
                <span class="fi-ev-ellipsis"><?= h($orgc['name']) ?></span>
            </a>
        <?php endif; ?>
    </td>
    <td class="fi-ev-c-date fi-mono"><?= h($e['date']) ?></td>
    <td class="fi-ev-c-threat">
        <span class="fi-ev-threat fi-ev-threat--<?= h($threatKinds[$threatId] ?? 'undefined') ?>">
            <i aria-hidden="true"></i><?= h(ucfirst(strtolower($threatLabel))) ?>
        </span>
    </td>
    <?php if ($show['attr']): ?>
        <td class="fi-ev-c-num fi-num" data-label="<?= __('Attr.') ?>"<?= $objects ? ' title="' . h(__n('%s object', '%s objects', $objects, number_format($objects))) . '"' : '' ?>>
            <?= h(number_format((int)$e['attribute_count'])) ?>
        </td>
    <?php endif; ?>
    <?php if ($show['corr']): ?>
        <td class="fi-ev-c-num fi-num fi-ev-dim" data-label="<?= __('Corr.') ?>">
            <?php $corr = (int)($e['correlation_count'] ?? 0); ?>
            <?php if ($corr > 0): ?>
                <a href="<?= h($rowUrl) ?>#tab-correlation" title="<?= __('Correlations') ?>"><?= h(number_format($corr)) ?></a>
            <?php else: ?>0<?php endif; ?>
        </td>
    <?php endif; ?>
    <td class="fi-ev-c-state">
        <span title="<?= h($stateTitle) ?>"><?= $proposals ? h(__('Proposal')) : h($state) ?></span>
        <div class="dropdown fi-ev-act">
            <button type="button" class="fi-ev-more" data-bs-toggle="dropdown" aria-expanded="false"
                    aria-label="<?= h(__('Actions for event %s', $id)) ?>">
                <i class="fas fa-ellipsis"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end fi-ev-menu">
                <li><a class="dropdown-item" href="<?= h($rowUrl) ?>"><i class="fas fa-eye"></i><?= __('View') ?></a></li>
                <?php if ($canEdit):
                    $editUrl = $baseurl . '/events/edit/' . $id;
                    $deleteUrl = $baseurl . '/events/delete/' . $id; ?>
                    <li><a class="dropdown-item" href="<?= h($editUrl) ?>"
                           onclick="event.preventDefault(); openModal('<?= h($editUrl) ?>');"><i class="fas fa-pen-to-square"></i><?= __('Edit') ?></a></li>
                    <li><a class="dropdown-item text-danger" href="<?= h($deleteUrl) ?>"
                           onclick="event.preventDefault(); openModal('<?= h($deleteUrl) ?>', 'md');"><i class="fas fa-trash"></i><?= __('Delete') ?></a></li>
                <?php endif; ?>
                <?php if ($canPublish):
                    $pubUrl = $baseurl . '/events/' . ($e['published'] ? 'unpublish' : 'publish') . '/' . $id; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= h($pubUrl) ?>"
                           onclick="event.preventDefault(); openModal('<?= h($pubUrl) ?>', 'md');">
                        <i class="fas fa-<?= $e['published'] ? 'eye-slash' : 'upload' ?>"></i><?= $e['published'] ? __('Unpublish') : __('Publish') ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </td>
</tr>
