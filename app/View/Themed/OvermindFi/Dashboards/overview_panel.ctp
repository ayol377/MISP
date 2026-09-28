<?php
/*
 * One OvermindFi Overview panel body (see Dashboards/overview.ctp), from
 * DashboardsController::__fiOverview() with ?panel=<key>.
 *
 * Vars: $panel (key), $data (the widget's handler() payload), $filters.
 * The map reuses the DotMap render kind; the rest is fi markup over the
 * widget payloads documented in each widget class.
 */
App::uses('DashboardURLValidator', 'Lib/Dashboard/Tools');

$compact = function ($n) {
    $n = (int)$n;
    foreach ([1000000 => 'M', 10000 => 'k'] as $floor => $suffix) {
        if (abs($n) >= $floor) {
            $div = $suffix === 'M' ? 1000000 : 1000;
            return rtrim(rtrim(number_format($n / $div, 1), '0'), '.') . $suffix;
        }
    }
    return number_format($n);
};
$link = function ($url) use ($baseurl) {
    $url = DashboardURLValidator::validate($url);
    if ($url !== null && $url[0] === '/' && substr($url, 0, 2) !== '//') {
        $url = $baseurl . $url;
    }
    return $url;
};
$empty = function ($text) {
    return '<div class="fi-ov-empty">' . h($text) . '</div>';
};
$rankTable = function (array $rows, $nameHeader) {
    // $rows: [[label, count, href|null], ...]
    $out = '<table class="fi-ov-rank"><thead><tr><th>' . h(__('Rank')) . '</th><th>' . h($nameHeader)
        . '</th><th class="fi-ov-num">' . h(__('Events')) . '</th></tr></thead><tbody>';
    foreach (array_values($rows) as $i => $row) {
        $name = h($row[0]);
        if (!empty($row[2])) {
            $name = '<a href="' . h($row[2]) . '">' . $name . '</a>';
        }
        $out .= '<tr><td class="fi-mono">' . sprintf('%02d', $i + 1) . '</td><td class="fi-ov-rank-name" title="'
            . h($row[0]) . '">' . $name . '</td><td class="fi-ov-num">' . h(number_format((int)$row[1])) . '</td></tr>';
    }
    return $out . '</tbody></table>';
};
$data = is_array($data) ? $data : [];

switch ($panel):

case 'stats':
    // Rows in OverviewStatsWidget order. Tone of a rise / a fall per stat,
    // after the mockup: more events or sightings reads as bad news, fewer
    // pending proposals as good; volume stats are neutral.
    $tones = [['bad', 'good'], ['neutral', 'neutral'], ['neutral', 'neutral'],
        ['neutral', 'neutral'], ['bad', 'good'], ['bad', 'good']];
    $rows = array_values($data);
    $attributeTotal = isset($rows[1]['raw']) ? $rows[1]['raw'] : null;
?>
<div class="fi-ov-stats">
<?php foreach ($rows as $i => $row):
    $raw = $row['raw'] ?? null;
    $change = isset($row['change']) ? (int)$row['change'] : 0;
    $href = !empty($row['drilldown']) ? $link($row['drilldown']) : null;
    $tag = $href ? 'a' : 'div';
    $badge = null;
    if ($raw !== null && $change !== 0) {
        $previous = (int)($row['previous'] ?? 0);
        // Correlation hits swing with volume; a percentage reads better.
        $badge = ($i === 3 && $previous > 0)
            ? round(abs($change) / $previous * 100) . '%'
            : $compact(abs($change));
        $tone = $tones[$i][$change > 0 ? 0 : 1] ?? 'neutral';
    }
    $title = isset($row['tooltip']) ? $row['tooltip'] : '';
    if ($raw !== null && isset($row['previous'])) {
        $title = trim($title . ' ' . __('Previous %s: %s', $filters['range'], number_format((int)$row['previous'])));
    }
?>
    <<?= $tag ?> class="fi-ov-stat"<?= $href ? ' href="' . h($href) . '"' : '' ?><?= $title !== '' ? ' title="' . h($title) . '"' : '' ?>>
        <span class="fi-ov-stat-label"><?= h($row['title'] ?? '') ?></span>
        <span class="fi-ov-stat-line">
            <span class="fi-num fi-ov-stat-value"><?= $raw === null ? h(__('N/A')) : h($compact($raw)) ?></span>
<?php if ($i === 2 && $raw !== null && $attributeTotal !== null): ?>
            <span class="fi-mono fi-ov-stat-max">/<?= h($compact($attributeTotal)) ?></span>
<?php endif; ?>
<?php if ($badge !== null): ?>
            <span class="fi-ov-badge fi-ov-badge-<?= h($tone) ?>"><?= h($badge) ?><?= $change > 0 ? '↑' : '↓' ?></span>
<?php endif; ?>
        </span>
    </<?= $tag ?>>
<?php endforeach; ?>
</div>
<?php
    break;

case 'origin':
    echo $this->element('dashboard/Widgets/DotMap', ['data' => $data]);
    break;

case 'ingest':
    $values = array_map('floatval', (array)($data['values'] ?? []));
    if (count($values) < 2) {
        echo $empty(__('No attributes in this window.'));
        break;
    }
    $forecast = array_map('floatval', (array)($data['forecast'] ?? []));
    $w = 300;
    $h = 70;
    $slots = count($values) + count($forecast) - 1;
    $max = max(1, max($values), empty($forecast) ? 0 : max($forecast));
    $point = function ($i, $v) use ($w, $h, $slots, $max) {
        return [round($i / $slots * $w, 2), round($h - 2 - $v / $max * ($h - 8), 2)];
    };
    $line = [];
    foreach ($values as $i => $v) {
        $line[] = $point($i, $v);
    }
    $last = end($line);
    $tail = [$last];
    foreach ($forecast as $j => $v) {
        $tail[] = $point(count($values) + $j, $v);
    }
    $pts = function (array $points) {
        return implode(' ', array_map(function ($p) { return $p[0] . ',' . $p[1]; }, $points));
    };
    $bucket = max(1, (int)($data['bucket'] ?? 86400));
    $start = (int)($data['start'] ?? 0);
    $format = $bucket < 86400 ? 'H:i' : 'm.d';
    $end = $start + (count($values) - 1) * $bucket;
    $area = $pts($line) . ' ' . $last[0] . ',' . $h . ' 0,' . $h;
?>
<div class="fi-ov-ingest" title="<?= h(__('%s attributes · %s', number_format((int)($data['total'] ?? array_sum($values))), $data['unit'] ?? '')) ?>">
    <div class="fi-ov-ingest-plot">
    <svg viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="none" role="img" aria-label="<?= h(__('Attribute ingest trend')) ?>">
        <defs>
            <linearGradient id="fiOvIngestFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#5bb75b" stop-opacity="0.18"></stop>
                <stop offset="1" stop-color="#5bb75b" stop-opacity="0"></stop>
            </linearGradient>
        </defs>
        <polygon points="<?= h($area) ?>" fill="url(#fiOvIngestFill)"></polygon>
        <polyline points="<?= h($pts($line)) ?>" fill="none" stroke="#5bb75b" stroke-width="1.2"
                  vector-effect="non-scaling-stroke" stroke-linejoin="round"></polyline>
<?php if (count($tail) > 1): ?>
        <polyline points="<?= h($pts($tail)) ?>" fill="none" stroke="#5bb75b" stroke-opacity="0.8" stroke-width="1.2"
                  stroke-dasharray="3 3" vector-effect="non-scaling-stroke"></polyline>
<?php endif; ?>
    </svg>
    <span class="fi-ov-ingest-dot" style="left:<?= round($last[0] / $w * 100, 2) ?>%;top:<?= round($last[1] / $h * 100, 2) ?>%"></span>
    </div>
    <div class="fi-ov-axis fi-mono">
        <span><?= h(date($format, $start)) ?></span>
        <span><?= h(date($format, $end)) ?></span>
    </div>
</div>
<?php
    break;

case 'actors':
    if (empty($data)) {
        echo $empty(__('No threat actors tagged on events in this window.'));
        break;
    }
    $rows = [];
    foreach (array_slice($data, 0, 5) as $row) {
        $rows[] = [$row['label'] ?? '', $row['count'] ?? 0, !empty($row['drilldown']) ? $link($row['drilldown']) : null];
    }
    echo $rankTable($rows, __('Threat actor'));
    break;

case 'tags':
    if (empty($data['data'])) {
        echo $empty(__('No tagged events in this window.'));
        break;
    }
    $rows = [];
    foreach (array_slice($data['data'], 0, 5, true) as $tag => $count) {
        $rows[] = [(string)$tag, $count, $baseurl . '/events/index/searchtag:' . rawurlencode((string)$tag)];
    }
    echo $rankTable($rows, __('Tag'));
    break;

case 'sync':
    $rows = (array)($data['rows'] ?? []);
    if (empty($rows)) {
        echo $empty(__('No enabled feeds or sync servers.'));
        break;
    }
    $statusText = ['ok' => __('OK'), 'warn' => __('No successful run in window'), 'danger' => __('Last run failed')];
    $ago = function ($ts) {
        if (empty($ts)) {
            return '—';
        }
        $d = max(0, time() - (int)$ts);
        if ($d < 3600) {
            return max(1, intdiv($d, 60)) . 'm';
        }
        return $d < 86400 ? intdiv($d, 3600) . 'h' : intdiv($d, 86400) . 'd';
    };
?>
<div class="fi-ov-sync">
<?php foreach ($rows as $row):
    $status = isset($statusText[$row['status'] ?? '']) ? $row['status'] : 'warn';
    $bars = array_map('intval', (array)($row['bars'] ?? []));
    $alerts = array_flip(array_map('intval', (array)($row['alerts'] ?? [])));
    $maxBar = max(1, empty($bars) ? 0 : max($bars));
    $href = !empty($row['drilldown']) ? $link($row['drilldown']) : null;
    $kind = ($row['kind'] ?? '') === 'server' ? __('Sync server') : __('Feed');
?>
    <div class="fi-ov-sync-row fi-ov-sync-<?= h($status) ?>">
        <span class="fi-ov-sync-name" title="<?= h($kind . ' · ' . $statusText[$status]) ?>">
            <span class="fi-ov-sync-mark" aria-hidden="true"></span>
            <?= $href ? '<a href="' . h($href) . '">' . h($row['name'] ?? '') . '</a>' : h($row['name'] ?? '') ?>
        </span>
        <svg class="fi-ov-sync-bars" viewBox="0 0 <?= max(1, count($bars)) ?> 10" preserveAspectRatio="none" aria-hidden="true">
<?php foreach ($bars as $i => $n):
    if ($n === 0 && !isset($alerts[$i])) {
        echo '<rect x="' . ($i + 0.2) . '" y="9.4" width="0.6" height="0.6" fill="currentColor" fill-opacity="0.45"></rect>';
        continue;
    }
    $barHeight = max(1.5, round(max(1, $n) / $maxBar * 10, 2));
?>
            <rect x="<?= $i + 0.15 ?>" y="<?= 10 - $barHeight ?>" width="0.7" height="<?= $barHeight ?>" fill="<?= isset($alerts[$i]) ? '#da4f49' : 'currentColor' ?>"></rect>
<?php endforeach; ?>
        </svg>
        <span class="fi-mono fi-ov-sync-ago" title="<?= h(__('Last successful run')) ?>"><?= h($ago($row['last'] ?? null)) ?></span>
    </div>
<?php endforeach; ?>
</div>
<?php
    break;

case 'tactics':
    $rows = (array)($data['rows'] ?? []);
    if (empty($rows)) {
        echo $empty(__('No ATT&CK techniques tagged on events in this window.'));
        break;
    }
    $max = max(1, (int)($data['max'] ?? 1));
    $start = (int)($data['start'] ?? 0);
    $bucket = max(1, (int)($data['bucket'] ?? 86400));
    $format = $bucket < 86400 ? 'm.d H:i' : 'm.d';
?>
<div class="fi-ov-tactics">
<?php foreach ($rows as $row): ?>
    <div class="fi-ov-tactic">
        <span class="fi-ov-tactic-label" title="<?= h(__n('%s event', '%s events', (int)($row['total'] ?? 0), number_format((int)($row['total'] ?? 0)))) ?>"><?= h($row['label'] ?? '') ?></span>
        <span class="fi-ov-tactic-cells">
<?php foreach (array_values((array)($row['cells'] ?? [])) as $i => $n):
    $n = (int)$n;
    // Spike: a bucket in the top quarter of the busiest one (and not a
    // grid where every hit is the max of 1).
    $class = $n === 0 ? '' : (($max > 1 && $n / $max > 0.75) ? ' is-spike' : ' is-on');
?>
            <i class="fi-ov-dot<?= $class ?>" title="<?= h(sprintf('%s · %s: %s', $row['label'] ?? '', date($format, $start + $i * $bucket), __n('%s event', '%s events', $n, $n))) ?>"></i>
<?php endforeach; ?>
        </span>
    </div>
<?php endforeach; ?>
</div>
<?php
    break;

endswitch;
