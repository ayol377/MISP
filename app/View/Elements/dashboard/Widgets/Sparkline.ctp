<?php
/**
 * Sparkline renderer (dashboard v2) — one thin trend line, server-side SVG.
 *
 * Data contract:
 *   [
 *     'values'   => [int, ...],     // one per bucket, oldest first
 *     'forecast' => [float, ...],   // optional, drawn dashed after values
 *     'start'    => unix ts of the first bucket,
 *     'bucket'   => bucket length in seconds,
 *     'total'    => int (optional, headline),
 *     'unit'     => 'per day' (optional caption),
 *   ]
 *
 * The line is 1.2px regardless of widget size (non-scaling stroke) and
 * takes currentColor, so each theme colours it; fi/dashboard.css sets it.
 */
$values = (isset($data['values']) && is_array($data['values'])) ? array_map('floatval', $data['values']) : [];
if (count($values) < 2) {
    echo '<div class="misp-list-empty">' . __('No data.') . '</div>';
    return;
}
$forecast = (isset($data['forecast']) && is_array($data['forecast'])) ? array_map('floatval', $data['forecast']) : [];
$width = 300;
$height = 60;
$slots = count($values) + count($forecast) - 1;
$max = max(1, max($values), empty($forecast) ? 0 : max($forecast));
$point = function ($i, $v) use ($width, $height, $slots, $max) {
    return round($i / $slots * $width, 2) . ',' . round($height - 2 - $v / $max * ($height - 4), 2);
};
$line = [];
foreach ($values as $i => $v) {
    $line[] = $point($i, $v);
}
$tail = [];
if (!empty($forecast)) {
    $tail[] = end($line);
    foreach ($forecast as $j => $v) {
        $tail[] = $point(count($values) + $j, $v);
    }
}
$bucket = max(1, (int)($data['bucket'] ?? 86400));
$start = (int)($data['start'] ?? 0);
$format = $bucket < 86400 ? 'H:i' : 'm.d';
$end = $start + (count($values) - 1) * $bucket;
?>
<div class="misp-sparkline">
    <div class="misp-sparkline-head">
<?php if (isset($data['total'])): ?>
        <span class="misp-sparkline-total"><?= h(number_format((int)$data['total'])) ?></span>
<?php endif; ?>
<?php if (!empty($data['unit'])): ?>
        <span class="misp-sparkline-unit"><?= h($data['unit']) ?><?= empty($forecast) ? '' : ' · ' . h(__('forecast dashed')) ?></span>
<?php endif; ?>
    </div>
    <svg class="misp-sparkline-svg" viewBox="0 0 <?= $width ?> <?= $height ?>" preserveAspectRatio="none"
         role="img" aria-label="<?= h(__('Trend line')) ?>">
        <polyline points="<?= h(implode(' ', $line)) ?>" fill="none" stroke="currentColor"
                  stroke-width="1.2" vector-effect="non-scaling-stroke" stroke-linejoin="round"></polyline>
<?php if (!empty($tail)): ?>
        <polyline points="<?= h(implode(' ', $tail)) ?>" fill="none" stroke="currentColor" stroke-opacity="0.6"
                  stroke-width="1.2" stroke-dasharray="3 3" vector-effect="non-scaling-stroke"></polyline>
<?php endif; ?>
    </svg>
    <div class="misp-sparkline-axis">
        <span><?= h(date($format, $start)) ?></span>
        <span><?= h(date($format, $end)) ?></span>
    </div>
</div>
