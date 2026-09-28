<?php
/**
 * DotMap renderer (dashboard v2) — dot-matrix world map, server-side SVG.
 *
 * Land is drawn from the DotMapLand grid; each run of land cells is one
 * path segment dashed into dots ("0 1" dash array + round caps), so the
 * whole map is a handful of <path>s. Countries with a count get their own
 * path, coloured on a log ramp, with a <title> tooltip.
 *
 * Data contract:
 *   [
 *     'data'   => ['XX' => count, ...],        // ISO alpha-2
 *     'actors' => ['XX' => ['Name', ...]],     // optional, tooltip
 *     'top'    => ['name' => ..., 'count' => n] | null,  // optional flag
 *     'window' => '30d',                       // optional label
 *   ]
 *
 * Colours are SVG presentation attributes so the map reads in every theme;
 * fi/dashboard.css retones the land dots.
 */
App::uses('DotMapLand', 'Lib/Dashboard/Tools');
App::uses('WidgetToolkit', 'Lib/Dashboard/Tools');

$counts = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : [];
$actors = (isset($data['actors']) && is_array($data['actors'])) ? $data['actors'] : [];
$window = isset($data['window']) ? (string)$data['window'] : '';
$ramp = ['rgba(218,79,73,0.2)', 'rgba(218,79,73,0.45)', 'rgba(218,79,73,0.8)', '#da4f49', '#f89406'];
$max = empty($counts) ? 0 : max(array_map('intval', $counts));

$land = '';
$hot = [];
foreach (DotMapLand::runs() as $run) {
    list($row, $col, $len, $iso) = $run;
    $segment = 'M' . ($col + 0.5) . ' ' . ($row + 0.5) . 'h' . ($len - 0.99);
    if (!empty($counts[$iso])) {
        $hot[$iso] = ($hot[$iso] ?? '') . $segment;
    } else {
        $land .= $segment;
    }
}

$toolkit = new WidgetToolkit();
// array_flip picks the last alias ("Mainland China"); prefer the short
// names WorldMap.ctp also pins.
$names = array_merge(array_flip($toolkit->getCountryCodeMapping()), [
    'CN' => 'China', 'RU' => 'Russia', 'KP' => 'North Korea', 'KR' => 'South Korea',
    'US' => 'United States', 'IR' => 'Iran', 'SY' => 'Syria', 'VN' => 'Vietnam',
]);
$dot = 'fill="none" stroke-width="0.62" stroke-linecap="round" stroke-dasharray="0 1"';
?>
<div class="misp-dotmap">
    <svg class="misp-dotmap-svg" viewBox="0 0 <?= DotMapLand::COLS ?> <?= DotMapLand::ROWS ?>"
         preserveAspectRatio="xMidYMid meet" role="img"
         aria-label="<?= h(__('Events by attributed country of origin')) ?>">
        <path class="misp-dotmap-land" d="<?= h($land) ?>" stroke="currentColor" stroke-opacity="0.3" <?= $dot ?>></path>
<?php foreach ($hot as $iso => $d):
    $n = (int)$counts[$iso];
    // Log scale, squared so only the busiest countries reach the top stops.
    $step = $max > 0 ? (int)min(4, floor((log1p($n) / log1p($max)) ** 2 * 4.999)) : 0;
    $label = sprintf(
        '%s: %s',
        $names[$iso] ?? $iso,
        __n('%s event', '%s events', $n, number_format($n))
    );
    if (!empty($actors[$iso]) && is_array($actors[$iso])) {
        $label .= ' · ' . implode(', ', array_map('strval', $actors[$iso]));
    }
?>
        <path class="misp-dotmap-hot" d="<?= h($d) ?>" stroke="<?= h($ramp[$step]) ?>" <?= $dot ?>><title><?= h($label) ?></title></path>
<?php endforeach; ?>
    </svg>
<?php if (!empty($data['top']['name'])): ?>
    <div class="misp-dotmap-flag">
        <?= h($data['top']['name']) ?> · <?= h(__n('%s event', '%s events', (int)$data['top']['count'], number_format((int)$data['top']['count']))) ?><?= $window !== '' ? ' ' . h(__('in %s', $window)) : '' ?>
    </div>
<?php endif; ?>
    <div class="misp-dotmap-foot">
<?php if (empty($counts)): ?>
        <span class="misp-dotmap-note"><?= h(__('No attributed threat-actor activity in this window.')) ?></span>
<?php else: ?>
        <span class="misp-dotmap-note"><?= h(__n('%s country', '%s countries', count($counts), count($counts))) ?><?= $window !== '' ? ' · ' . h($window) : '' ?></span>
        <span class="misp-dotmap-legend" aria-hidden="true">
            <span><?= __('fewer') ?></span>
            <svg viewBox="0 0 5 1" width="60" height="10" preserveAspectRatio="none">
<?php foreach ($ramp as $i => $colour): ?>
                <rect x="<?= $i ?>" y="0" width="1" height="1" fill="<?= h($colour) ?>"></rect>
<?php endforeach; ?>
            </svg>
            <span><?= __('more') ?></span>
        </span>
<?php endif; ?>
    </div>
</div>
