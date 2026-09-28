<?php
/*
 * Dot-matrix map of the event's targeted countries, rendered as inline SVG.
 *
 * $countries: [ISO alpha-2 => label]. Positions come from the dashboard's
 * vendored iso-centroids.json. LAND_MASK is a 240x120 bitmap (1.5 degree
 * cells, row-major from 90N/180W) rasterised from the vendored
 * world-110m.geojson; one bit per cell, base64.
 */
$landMask = base64_decode(preg_replace('/\s+/', '', <<<'MASK'
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAD/gAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAB//8////44AAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAPx/n/////AAAA/AAYAAAAfAAAAAAAAAAAAAAAAEO3v4f////+AAAPgA
AAAAAAAcAAAAAAAAAAAAAADAAAfgP////+AAAHAAAAAAAAAMAAAAAAAAAAAAAAA9jjCAAP///+AA
AAAAAAHgAAP/8AAPAAAAAAAAAAcAAAAAAD///8AAAAAAAAYAAD//gAAAAAAAAAAAAAe6zs/AAB//
/4AAAAAAABgDA/////wGAAAAwAEAAAI/wM//AAf//4AAAAAAABwHf/////z/+AABAB//wPI/+sEP
4A///wAAAAf8AACHf////////8B8wH/////ww3mB4Af/8AAAAD//xjf7v///////////7A//////
//+B/g//gAAAAH//4///f///////////GH////////0P5Af4AH4AAPx+H///////////////AAf/
//////Ng+AP4ADAAA/n////////////////+AD///////8COMAHwAAAAD/P///////////////v8
AD/7/////4APwABgAAAAH/P//////////////wfAAA+AH////4APzAAAAAAAD/D/////////////
JhgAAAHAAf///8AH/gAAAAAIAOD////////////4AHAAAAQAAP////wH/gAAAAAMBuP/////////
///wAPgAACAAAH////+f/8AAAAAWAhf////////////AAPAAAAAAAT////+f/+AAAAA3H///////
///////+AOAAAAAAAB/////f/8AAAAAHn//////////////+AIAAAAAAAB//////8wAAAAAMf///
///////////9AAAAAAAAAA//////2HAAAAAF///////////////4AAAAAAAAAAf/////+AgAAAAD
///////////////4AAAAAAAAAAf//////wAAAAAB///yfx/////////wAAAAAAAAAAf/////yAAA
AAAB/z/gPj/////////jAAAAAAAAAAf/////gAAAAAA/wY/gDx////////8HAAAAAAAAAAf/////
AAAAAAA/gGfnn4////////wAAAAAAAAAAAf////+AAAAAAA/ACY//4///////1gGAAAAAAAAAAP/
///8AAAAAAA/ACM//4///////hwEAAAAAAAAAAH////4AAAAAAAOLgA//////////4wcAAAAAAAA
AAH////4AAAAAAAN/gDC/////////wz8AAAAAAAAAAB////wAAAAAAAf/gAA/////////wDgAAAA
AAAAAAA////AAAAAAAA//8YB/////////4EAAAAAAAAAAAAX///AAAAAAAA///f//////////4AA
AAAAAAAAAAAX/5BAAAAAAAB//////z///////4AAAAAAAAAAAAAb/gBAAAAAAAH////8/5//////
/4AAAAAAAAAAAAAF/gBoAAAAAAP////+/4f//////wAAAAAAAAAAAAAE/gAAAAAAAAP////+f8wH
/////gAAAAAAAAAAAAAAfgAAAAAAAAf/////P/4D/////IAAAAAAAAAAAAAAPgAQAAAAAAf/////
v/8D/+f/4AAAAAAAAAAAAAAAPgwOAAAAAAf/////n/4Af8P+AAAAAAAAAAAAAAAAHxwAwAAAAAf/
////n/wAfwH+wAAAAAAAAAAAAAAAB/gAAAAAAAf/////z/gAfgH+AMAAAAAAAAAAAAAAAT8AAAAA
AAf/////z+AAfAF/AIAAAAAAAAAAAAAAAB+AAAAAAAf/////94AAOAB/gIAAAAAAAAAAAAAAAAMA
AAAAAAf//////AAAOAA/gCAAAAAAAAAAAAAAAAEBQAAAAAP/////+MAAGAAHAFAAAAAAAAAAAAAA
AACDfgAAAAH//////8AAGAACAAAAAAAAAAAAAAAAAABP/wAAAAH//////4AABABgADAAAAAAAAAA
AAAAAAAP/4AAAAD//////4AABAAQABAAAAAAAAAAAAAAAAAP//gAAAA/H////wAAAACYBgAAAAAA
AAAAAAAAAAAH//wAAAAAA////wAAAADYDAAAAAAAAAAAAAAAAAAP//wAAAAAA////gAAAABoPgAA
AAAAAAAAAAAAAAAf//4AAAAAA///+AAAAAA4fuQAAAAAAAAAAAAAAAA///+AAAAAA///8AAAAAAY
fASAAAAAAAAAAAAAAAA////AAAAAA///4AAAAAAcfYBYAAAAAAAAAAAAAAA////8AAAAAf//4AAA
AAAOCEh/AAAAAAAAAAAAAAA/////AAAAAP//wAAAAAAGAAAPgAAAAAAAAAAAAAA/////gAAAAP//
wAAAAAADIABPwIAAAAAAAAAAAAAf////gAAAAH//wAAAAAAAOAAPYCAAAAAAAAAAAAAP////AAAA
AH//4AAAAAAAACAAMAAAAAAAAAAAAAAP///+AAAAAH//4AAAAAAAAAAAAAAAAAAAAAAAAAAH///+
AAAAAH//4AAAAAAAAAHhAAAAAAAAAAAAAAAH///8AAAAAP//4IAAAAAAAAvjAAAAAAAAAAAAAAAD
///8AAAAAP//4cAAAAAAAB/jgAAAAAAAAAAAAAAA///8AAAAAP//h4AAAAAAAD/7gAAAAAAAAAAA
AAAAf//8AAAAAP//B4AAAAAAAH//wAAAAAAAAAAAAAAAf//4AAAAAH/+AwAAAAAAAf//4AQAAAAA
AAAAAAAAf//4AAAAAH//BwAAAAAAB///8AIAAAAAAAAAAAAAf//gAAAAAD//BwAAAAAAD///+AAA
AAAAAAAAAAAAf/8AAAAAAD/+BgAAAAAAD////AAAAAAAAAAAAAAAf/8AAAAAAD/8AAAAAAAAD///
/AAAAAAAAAAAAAAAf/8AAAAAAD/8AAAAAAAAD////AAAAAAAAAAAAAAA//4AAAAAAB/4AAAAAAAA
B////AAAAAAAAAAAAAAA//wAAAAAAA/wAAAAAAAAB////AAAAAAAAAAAAAAA//gAAAAAAA/gAAAA
AAAAB/h//AAAAAAAAAAAAAAA//AAAAAAAA+AAAAAAAAAB+Av+AAAAAAAAAAAAAAA/8AAAAAAAAAA
AAAAAAAAAAAP8AAQAAAAAAAAAAAB/8AAAAAAAAAAAAAAAAAAAAAH8AAIAAAAAAAAAAAB/4AAAAAA
AAAAAAAAAAAAAAADQAAOAAAAAAAAAAAB/gAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAAAAAAAAB+AAA
AAAAAAAAAAAAAAAAAAAAYAAIAAAAAAAAAAAB+AAAAAAAAAAAAAAAAAAAAAAAYAAwAAAAAAAAAAAD
8AAAAAAAAAAAAAAAAAAAAAAAAADAAAAAAAAAAAAD4AAAAAAAAAAAAAAAAAAAAAAAAAHAAAAAAAAA
AAAD8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAADwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADwYAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAB4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA+AAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAGAAAAAAA
AAAAADwAACAefH/gAAAAAAAAAAAAAAAAEAAAAAAAAAAAB//8B///////4AAAAAAAAAAAAAAA/AAA
AAAAAAAH///8P////////8AAAAAAAAAAAAAB3gAAAAATf//////8//////////+AAAAAAAAAAAgA
HgAAAAH////////////////////AAAAAAAB4B////gAAAAP///////////////////4AAAAD////
////4AAAAD////////////////////gAAALP///////8AAAAD/////////////////////gAABj/
///////wAAHg//////////////////////4AAAAD///////8AQ/AB////////////////////+AA
AAB//////////AGP//////////////////////AAAAA/////////////////////////////////
//8AAD//gAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAA
MASK
));
$cell = 1.5;
$maskCols = 240;
$maskRows = 120;
$isLand = function ($lon, $lat) use ($landMask, $cell, $maskCols, $maskRows) {
    $c = (int)floor(($lon + 180) / $cell);
    $r = (int)floor((90 - $lat) / $cell);
    if ($c < 0 || $c >= $maskCols || $r < 0 || $r >= $maskRows) {
        return false;
    }
    $i = $r * $maskCols + $c;
    return (ord($landMask[$i >> 3]) >> (7 - ($i & 7))) & 1;
};

$centroids = json_decode((string)@file_get_contents(
    WWW_ROOT . 'js/dashboard/charts/vendor/iso-centroids.json'
), true) ?: [];
$points = [];
foreach ($countries as $iso => $label) {
    if (isset($centroids[$iso])) {
        $points[] = [(float)$centroids[$iso][0], (float)$centroids[$iso][1]];
    }
}

if (!empty($points)):
    // Crop to the points with some context, at the panel's ~1.6:1 aspect.
    $lons = array_column($points, 0);
    $lats = array_column($points, 1);
    $pad = 12;
    $lonSpan = max(max($lons) - min($lons) + 2 * $pad, 50);
    $latSpan = max(max($lats) - min($lats) + 2 * $pad, $lonSpan / 1.6);
    $lonSpan = min(360, max($lonSpan, $latSpan * 1.6));
    $latSpan = min(144, $latSpan);
    $west = max(-180, min(180 - $lonSpan, (max($lons) + min($lons) - $lonSpan) / 2));
    $north = min(84, max(-60 + $latSpan, (max($lats) + min($lats) + $latSpan) / 2));
    // At most ~60 dots across, whatever the zoom.
    $step = $cell * max(1, ceil($lonSpan / $cell / 60));
    $cols = (int)floor($lonSpan / $step);
    $rows = (int)floor($latSpan / $step);
    $sigma = max(2.5, $lonSpan / 20);
    $px = 6;

    // Intensity ramp, low to high (design tokens).
    $ramp = ['rgba(218,79,73,.2)', 'rgba(218,79,73,.45)', 'rgba(218,79,73,.8)', '#da4f49', '#f89406'];
    $cuts = [0.12, 0.3, 0.5, 0.75, 0.92];
    $paths = array_fill(0, count($ramp) + 1, '');
    for ($r = 0; $r < $rows; $r++) {
        $lat = $north - ($r + 0.5) * $step;
        for ($c = 0; $c < $cols; $c++) {
            $lon = $west + ($c + 0.5) * $step;
            if (!$isLand($lon, $lat)) {
                continue;
            }
            $heat = 0;
            foreach ($points as [$pLon, $pLat]) {
                $d2 = ($lon - $pLon) ** 2 + ($lat - $pLat) ** 2;
                $heat = max($heat, exp(-$d2 / (2 * $sigma * $sigma)));
            }
            $bin = 0;
            foreach ($cuts as $i => $cut) {
                if ($heat >= $cut) {
                    $bin = $i + 1;
                }
            }
            $paths[$bin] .= sprintf('M%d %dh0', $c * $px + 3, $r * $px + 3);
        }
    }
    $toX = function ($lon) use ($west, $step, $px) {
        return round(($lon - $west) / $step * $px, 1);
    };
    $toY = function ($lat) use ($north, $step, $px) {
        return round(($north - $lat) / $step * $px, 1);
    };
?>
<svg class="fi-evw-geo" viewBox="0 0 <?= $cols * $px ?> <?= $rows * $px ?>"
     role="img" aria-label="<?= h(__('Targeted countries: %s', implode(', ', $countries))) ?>">
    <path d="<?= $paths[0] ?>" stroke="#4f6b82" stroke-opacity=".45"
          stroke-width="3.2" stroke-linecap="round"/>
    <?php foreach ($ramp as $i => $colour): ?>
        <?php if ($paths[$i + 1] !== ''): ?>
            <path d="<?= $paths[$i + 1] ?>" stroke="<?= $colour ?>"
                  stroke-width="3.2" stroke-linecap="round"/>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php foreach ($points as [$pLon, $pLat]): ?>
        <circle cx="<?= $toX($pLon) ?>" cy="<?= $toY($pLat) ?>" r="2.4" fill="#f89406"/>
    <?php endforeach; ?>
</svg>
<?php endif; ?>
<ul class="fi-evw-geo-list">
    <?php foreach ($countries as $iso => $label): ?>
        <li><span class="fi-mono"><?= h($iso) ?></span> <?= h($label) ?></li>
    <?php endforeach; ?>
</ul>
