<?php
/**
 * SyncHealth renderer (dashboard v2) — status + micro-bar sparkline + age
 * per sync source (FeedSyncHealthWidget first).
 *
 * Data contract:
 *   ['rows' => [
 *      ['name' => ..., 'kind' => 'feed'|'server',
 *       'status' => 'ok'|'warn'|'danger',
 *       'bars' => [int, ...], 'alerts' => [bar index, ...],
 *       'last' => unix ts|null, 'drilldown' => '/relative/url'],
 *   ], 'total' => int]
 *
 * Status colours are SVG fills so they read in every theme; the rest is
 * styled by fi/dashboard.css (plain rows otherwise).
 */
App::uses('DashboardURLValidator', 'Lib/Dashboard/Tools');

$rows = (isset($data['rows']) && is_array($data['rows'])) ? $data['rows'] : [];
if (empty($rows)) {
    echo '<div class="misp-list-empty">' . __('No enabled feeds or sync servers.') . '</div>';
    return;
}
$colours = ['ok' => '#5bb75b', 'warn' => '#f89406', 'danger' => '#da4f49'];
$labels = ['ok' => __('OK'), 'warn' => __('No successful run in window'), 'danger' => __('Last run failed')];
$ago = function ($ts) {
    if (empty($ts)) {
        return '—';
    }
    $d = max(0, time() - (int)$ts);
    if ($d < 3600) {
        return max(1, intdiv($d, 60)) . 'm';
    }
    if ($d < 86400) {
        return intdiv($d, 3600) . 'h';
    }
    return intdiv($d, 86400) . 'd';
};
?>
<div class="misp-synchealth">
<?php foreach ($rows as $row):
    $status = isset($colours[$row['status'] ?? '']) ? $row['status'] : 'warn';
    $bars = (isset($row['bars']) && is_array($row['bars'])) ? array_map('intval', $row['bars']) : [];
    $alerts = array_flip(array_map('intval', (array)($row['alerts'] ?? [])));
    $max = max(1, empty($bars) ? 0 : max($bars));
    $href = !empty($row['drilldown']) ? DashboardURLValidator::validate($row['drilldown']) : null;
    $name = h($row['name'] ?? '');
?>
    <div class="misp-synchealth-row misp-synchealth-<?= h($status) ?>">
        <span class="misp-synchealth-name" title="<?= h($labels[$status]) ?>">
            <svg class="misp-synchealth-dot" viewBox="0 0 8 8" width="8" height="8" aria-hidden="true"><circle cx="4" cy="4" r="3.5" fill="<?= h($colours[$status]) ?>"></circle></svg>
            <?= $href !== null ? '<a href="' . h($href) . '">' . $name . '</a>' : $name ?>
            <span class="misp-synchealth-kind"><?= ($row['kind'] ?? '') === 'server' ? __('sync') : __('feed') ?></span>
        </span>
        <svg class="misp-synchealth-bars" viewBox="0 0 <?= max(1, count($bars)) ?> 10" preserveAspectRatio="none" aria-hidden="true">
<?php foreach ($bars as $i => $n):
    $barHeight = $n > 0 ? max(1.5, round($n / $max * 10, 2)) : 0.8;
    $fill = isset($alerts[$i]) ? '#da4f49' : 'currentColor';
?>
            <rect x="<?= $i + 0.15 ?>" y="<?= 10 - $barHeight ?>" width="0.7" height="<?= $barHeight ?>" fill="<?= $fill ?>"<?= ($n === 0 && !isset($alerts[$i])) ? ' fill-opacity="0.25"' : '' ?>></rect>
<?php endforeach; ?>
        </svg>
        <span class="misp-synchealth-ago" title="<?= h(__('Last successful run')) ?>"><?= h($ago($row['last'] ?? null)) ?></span>
    </div>
<?php endforeach; ?>
<?php if (!empty($data['total']) && (int)$data['total'] > count($rows)): ?>
    <div class="misp-synchealth-more"><?= h(__('%s of %s sources shown', count($rows), (int)$data['total'])) ?></div>
<?php endif; ?>
</div>
