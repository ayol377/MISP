<?php
/**
 * PunchCard renderer (dashboard v2) — rows of time-bucketed blocks, block
 * intensity = count (AttackTacticsWidget first). Server-side SVG.
 *
 * Data contract:
 *   [
 *     'rows'   => [['label' => ..., 'cells' => [int, ...], 'total' => int], ...],
 *     'max'    => int (largest cell),
 *     'start'  => unix ts of the first column,
 *     'bucket' => column length in seconds,
 *     'window' => '28d' (optional caption),
 *   ]
 *
 * Blocks use SVG fills (primary blue by intensity, top quartile in the
 * warning orange) so they read in every theme.
 */
$rows = (isset($data['rows']) && is_array($data['rows'])) ? $data['rows'] : [];
if (empty($rows)) {
    echo '<div class="misp-list-empty">' . __('No ATT&CK techniques tagged on events in this window.') . '</div>';
    return;
}
$max = max(1, (int)($data['max'] ?? 1));
$start = (int)($data['start'] ?? 0);
$bucket = max(1, (int)($data['bucket'] ?? 86400));
$format = $bucket < 86400 ? 'H:i' : 'm.d';
$cols = 0;
foreach ($rows as $row) {
    $cols = max($cols, count((array)$row['cells']));
}
?>
<div class="misp-punchcard">
    <table class="misp-punchcard-table">
<?php foreach ($rows as $row): ?>
        <tr>
            <th scope="row" class="misp-punchcard-label"><?= h($row['label']) ?></th>
            <td class="misp-punchcard-cells">
                <svg viewBox="0 0 <?= $cols ?> 1" width="100%" preserveAspectRatio="xMinYMid meet" aria-hidden="true">
<?php foreach (array_values((array)$row['cells']) as $i => $n):
    $n = (int)$n;
    $ratio = $n / $max;
    if ($n === 0) {
        $attrs = 'fill="currentColor" fill-opacity="0.08"';
    } elseif ($ratio > 0.75) {
        $attrs = 'fill="#f89406"';
    } else {
        $attrs = 'fill="#0088cc" fill-opacity="' . round(0.35 + $ratio * 0.65 / 0.75, 2) . '"';
    }
    $title = sprintf('%s · %s: %s', $row['label'], date($format === 'H:i' ? 'm.d H:i' : 'm.d', $start + $i * $bucket), __n('%s event', '%s events', $n, $n));
?>
                    <rect x="<?= $i + 0.12 ?>" y="0.12" width="0.76" height="0.76" rx="0.08" <?= $attrs ?>><title><?= h($title) ?></title></rect>
<?php endforeach; ?>
                </svg>
            </td>
            <td class="misp-punchcard-total"><?= h(number_format((int)($row['total'] ?? 0))) ?></td>
        </tr>
<?php endforeach; ?>
        <tr class="misp-punchcard-axis">
            <td></td>
            <td><span><?= h(date($format, $start)) ?></span><span><?= h(!empty($data['window']) ? $data['window'] : '') ?></span><span><?= h(date($format, $start + ($cols - 1) * $bucket)) ?></span></td>
            <td></td>
        </tr>
    </table>
</div>
