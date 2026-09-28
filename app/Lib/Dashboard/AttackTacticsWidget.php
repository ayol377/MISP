<?php

App::uses('OverviewWidgetTool', 'Lib/Dashboard/Tools');

/**
 * AttackTacticsWidget — "ATT&CK tactics observed": a punch-card of
 * distinct events per Enterprise ATT&CK tactic per time bucket.
 *
 * Events are those the viewer may see, bucketed by Event.timestamp, that
 * carry an event-level misp-galaxy:mitre-attack-pattern tag (as
 * AttackWidget counts). A technique's tactics come from its cluster's
 * `kill_chain` meta (Enterprise "attack-*" chains only), for clusters the
 * viewer may see. Only observed tactics get a row, in kill-chain order.
 * Rendered by the PunchCard kind.
 */
class AttackTacticsWidget
{
    public $title = 'ATT&CK tactics observed';
    public $category = 'events';
    public $render = 'PunchCard';
    public $width = 4;
    public $height = 4;
    public $description = 'Punch-card of events per ATT&CK tactic over time, '
        . 'from the technique tags on the events you can see.';
    public $params = [
        'time_window' => 'The time window, going back in seconds, to include '
            . '(e.g. "28d"; -1 = the last 90 days).',
    ];
    public $schema = [
        'time_window' => [
            'type' => 'time_window',
            'default' => 'P28D',
            'help' => 'Window covered by the punch-card; about 30 columns '
                . '(hours for up to 2 days, else days or multi-day buckets).',
        ],
    ];
    public $placeholder = '{
    "time_window": "28d"
}';
    public $cacheLifetime = false;
    public $autoRefreshDelay = false;
    public $cache_duration = 600;
    public $cache_scope = 'org';

    /** Enterprise tactic order; unknown (renamed/new) tactics sort after. */
    const TACTIC_ORDER = [
        'reconnaissance', 'resource-development', 'initial-access', 'execution',
        'persistence', 'privilege-escalation', 'defense-evasion', 'stealth',
        'defense-impairment', 'credential-access', 'discovery',
        'lateral-movement', 'collection', 'command-and-control',
        'exfiltration', 'impact',
    ];

    public function handler($user, $options = array())
    {
        $window = OverviewWidgetTool::parseWindow($options, 28 * OverviewWidgetTool::DAY);
        if ($window === -1) {
            $window = 90 * OverviewWidgetTool::DAY;
        }
        $bucket = $window <= 2 * OverviewWidgetTool::DAY
            ? 3600
            : OverviewWidgetTool::DAY * max(1, (int)ceil($window / OverviewWidgetTool::DAY / 30));
        $cols = (int)ceil($window / $bucket);
        $start = time() - $cols * $bucket;

        $pairs = OverviewWidgetTool::eventTagRows($user, 'misp-galaxy:mitre-attack-pattern=', $start);
        $tagNames = array_unique(array_column($pairs, 1));
        $tacticsByTag = [];
        foreach (OverviewWidgetTool::clusterElements($user, $tagNames, 'kill_chain') as $tagName => $chains) {
            foreach ($chains as $chain) {
                if (strpos($chain, 'attack-') === 0 && ($pos = strrpos($chain, ':')) !== false) {
                    $tactic = substr($chain, $pos + 1);
                    $tacticsByTag[$tagName][$tactic] = $tactic;
                }
            }
        }

        $cells = [];   // tactic => col => [event_id => true]
        $totals = [];  // tactic => [event_id => true]
        foreach ($pairs as $pair) {
            list($eventId, $tagName, $timestamp) = $pair;
            if (empty($tacticsByTag[$tagName])) {
                continue;
            }
            $col = (int)min($cols - 1, max(0, intdiv($timestamp - $start, $bucket)));
            foreach ($tacticsByTag[$tagName] as $tactic) {
                $cells[$tactic][$col][$eventId] = true;
                $totals[$tactic][$eventId] = true;
            }
        }

        $order = array_flip(self::TACTIC_ORDER);
        uksort($cells, function ($a, $b) use ($order) {
            return [$order[$a] ?? 99, $a] <=> [$order[$b] ?? 99, $b];
        });
        $rows = [];
        $max = 0;
        foreach ($cells as $tactic => $byCol) {
            $counts = array_fill(0, $cols, 0);
            foreach ($byCol as $col => $events) {
                $counts[$col] = count($events);
            }
            $max = max($max, max($counts));
            $rows[] = [
                'label' => ucfirst(str_replace('-', ' ', $tactic)),
                'cells' => $counts,
                'total' => count($totals[$tactic]),
            ];
        }
        return [
            'rows' => $rows,
            'max' => $max,
            'start' => $start,
            'bucket' => $bucket,
            'window' => OverviewWidgetTool::windowLabel($window),
        ];
    }
}
