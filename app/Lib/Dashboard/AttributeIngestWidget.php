<?php

App::uses('OverviewWidgetTool', 'Lib/Dashboard/Tools');

/**
 * AttributeIngestWidget — attributes added/updated per bucket (hour for
 * windows up to 2 days, day up to 180 days, week beyond) over the time
 * window, as a thin sparkline with a dashed linear-trend forecast tail.
 * ACL-scoped via MispAttribute::buildConditions (see OverviewWidgetTool).
 * Rendered by the Sparkline kind.
 */
class AttributeIngestWidget
{
    public $title = 'Attribute ingest';
    public $category = 'events';
    public $render = 'Sparkline';
    public $width = 4;
    public $height = 3;
    public $description = 'Thin trend line of visible attributes by '
        . 'timestamp over the time window, with a dashed forecast tail.';
    public $params = [
        'time_window' => 'The time window, going back in seconds, to include '
            . '(e.g. "30d"; -1 = the last year).',
        'forecast' => 'Show the dashed linear-trend forecast tail.',
    ];
    public $schema = [
        'time_window' => [
            'type' => 'time_window',
            'default' => 'P30D',
            'help' => 'Window to plot. All time is plotted as the last year.',
        ],
        'forecast' => [
            'type' => 'bool',
            'default' => true,
            'help' => 'Extend the line with a dashed linear-trend forecast.',
        ],
    ];
    public $placeholder = '{
    "time_window": "30d",
    "forecast": true
}';
    public $cacheLifetime = false;
    public $autoRefreshDelay = false;
    public $cache_duration = 600;
    public $cache_scope = 'org';

    public function handler($user, $options = array())
    {
        $window = OverviewWidgetTool::parseWindow($options, 30 * OverviewWidgetTool::DAY);
        if ($window === -1) {
            $window = 365 * OverviewWidgetTool::DAY;
        }
        if ($window <= 2 * OverviewWidgetTool::DAY) {
            $bucket = 3600;
        } elseif ($window <= 180 * OverviewWidgetTool::DAY) {
            $bucket = OverviewWidgetTool::DAY;
        } else {
            $bucket = 7 * OverviewWidgetTool::DAY;
        }
        $now = time();
        $first = intdiv($now - $window, $bucket);
        $last = intdiv($now, $bucket);

        $conditions = OverviewWidgetTool::window('Attribute.timestamp', $first * $bucket);
        $conditions['Attribute.deleted'] = 0;
        list($conditions, $joins) = OverviewWidgetTool::attributeQuery($user, $conditions, OverviewWidgetTool::eventFilter($user, $options));
        // Integer bucketing is portable (MySQL and PostgreSQL) and UTC-aligned.
        $expr = 'FLOOR(Attribute.timestamp / ' . (int)$bucket . ')';
        $rows = ClassRegistry::init('MispAttribute')->find('all', [
            'recursive' => -1,
            'fields' => [$expr . ' AS bucket', 'COUNT(*) AS total'],
            'joins' => $joins,
            'conditions' => $conditions,
            'group' => [$expr],
        ]);
        $counts = array_fill($first, $last - $first + 1, 0);
        foreach ($rows as $row) {
            $b = (int)$row[0]['bucket'];
            if (isset($counts[$b])) {
                $counts[$b] = (int)$row[0]['total'];
            }
        }
        $values = array_values($counts);

        $forecast = [];
        $showForecast = filter_var($options['forecast'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($showForecast !== false && count($values) >= 4) {
            $forecast = $this->forecast($values);
        }

        return [
            'values' => $values,
            'forecast' => $forecast,
            'start' => $first * $bucket,
            'bucket' => $bucket,
            'total' => array_sum($values),
            'unit' => $bucket === 3600 ? __('per hour') : ($bucket === OverviewWidgetTool::DAY ? __('per day') : __('per week')),
        ];
    }

    /**
     * Least-squares line over the last third of the series (at least 4
     * points), projected ~15% further, clamped at 0. The last, partial
     * bucket is left out of the fit.
     *
     * ponytail: naive linear trend, no seasonality; swap for Holt-Winters
     * if the forecast is used for anything but a visual hint.
     */
    private function forecast(array $values)
    {
        $fit = array_slice($values, 0, -1);
        $n = max(4, (int)ceil(count($fit) / 3));
        $fit = array_slice($fit, -$n);
        $n = count($fit);
        $meanX = ($n - 1) / 2;
        $meanY = array_sum($fit) / $n;
        $num = 0;
        $den = 0;
        foreach ($fit as $x => $y) {
            $num += ($x - $meanX) * ($y - $meanY);
            $den += ($x - $meanX) ** 2;
        }
        $slope = $den > 0 ? $num / $den : 0;
        $steps = max(2, (int)round(count($values) * 0.15));
        $out = [];
        for ($i = 1; $i <= $steps; $i++) {
            // x of the last full bucket is $n - 1; the partial one is $n.
            $out[] = max(0, round($meanY + $slope * ($n + $i - $meanX), 1));
        }
        return $out;
    }
}
