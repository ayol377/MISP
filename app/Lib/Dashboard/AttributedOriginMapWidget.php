<?php

App::uses('OverviewWidgetTool', 'Lib/Dashboard/Tools');

/**
 * AttributedOriginMapWidget — "Activity by attributed origin": distinct
 * events in the window per country, where the country is the `country`
 * meta of the threat-actor galaxy clusters tagged on the event.
 *
 * Where ThreatActorCountryMapWidget maps the galaxy library itself, this
 * maps activity: only events the viewer may see (createEventConditions),
 * only clusters the viewer may see (GalaxyCluster::buildConditions).
 * Event-level tags only, like AttackWidget. Rendered by the DotMap kind
 * (dot-matrix land grid, no JS).
 */
class AttributedOriginMapWidget
{
    public $title = 'Activity by attributed origin';
    public $category = 'events';
    public $render = 'DotMap';
    public $width = 8;
    public $height = 7;
    public $description = 'Dot-matrix world map of events in the time window, '
        . 'by the country of the threat actors tagged on them (threat-actor '
        . 'galaxy cluster country meta).';
    public $params = [
        'time_window' => 'The time window, going back in seconds, to include '
            . '(e.g. "30d"; -1 = all history).',
    ];
    public $schema = [
        'time_window' => [
            'type' => 'time_window',
            'default' => 'P30D',
            'help' => 'Only events updated within this window are counted.',
        ],
    ];
    public $placeholder = '{
    "time_window": "30d"
}';
    public $cacheLifetime = false;
    public $autoRefreshDelay = false;
    public $cache_duration = 600;
    public $cache_scope = 'org';

    public function handler($user, $options = array())
    {
        $window = OverviewWidgetTool::parseWindow($options, 30 * OverviewWidgetTool::DAY);
        $start = $window === -1 ? null : time() - $window;

        // Actors tagged on the event or on any of its attributes.
        $eventsByActor = [];
        $eventFilter = OverviewWidgetTool::eventFilter($user, $options);
        $rows = array_merge(
            OverviewWidgetTool::eventTagRows($user, 'misp-galaxy:threat-actor=', $start, $eventFilter),
            OverviewWidgetTool::attributeTagRows($user, 'misp-galaxy:threat-actor=', $start, $eventFilter)
        );
        foreach ($rows as $row) {
            $eventsByActor[$row[1]][$row[0]] = true;
        }
        $countries = OverviewWidgetTool::clusterElements($user, array_keys($eventsByActor), 'country');

        $eventsByCountry = [];
        $actorsByCountry = [];
        foreach ($countries as $tagName => $values) {
            foreach ($values as $value) {
                $iso = strtoupper(trim($value));
                if (!preg_match('/^[A-Z]{2}$/', $iso)) {
                    continue;
                }
                $eventsByCountry[$iso] = ($eventsByCountry[$iso] ?? []) + $eventsByActor[$tagName];
                $actorsByCountry[$iso][OverviewWidgetTool::clusterName($tagName)] = count($eventsByActor[$tagName]);
            }
        }

        $data = array_map('count', $eventsByCountry);
        arsort($data);
        $actors = [];
        foreach ($actorsByCountry as $iso => $list) {
            arsort($list);
            $actors[$iso] = array_slice(array_keys($list), 0, 3);
        }

        // Most active attributed actor, for the map flag.
        $top = null;
        foreach ($eventsByActor as $tagName => $events) {
            if (isset($countries[$tagName]) && ($top === null || count($events) > $top['count'])) {
                $top = ['name' => OverviewWidgetTool::clusterName($tagName), 'count' => count($events)];
            }
        }

        return [
            'data' => $data,
            'actors' => $actors,
            'top' => $top,
            'window' => OverviewWidgetTool::windowLabel($window),
        ];
    }
}
