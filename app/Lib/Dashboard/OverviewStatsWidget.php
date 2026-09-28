<?php

App::uses('OverviewWidgetTool', 'Lib/Dashboard/Tools');

/**
 * OverviewStatsWidget — the ops-overview stat strip: events, attributes,
 * IDS-flagged (of total), correlation hits, sightings and proposals in the
 * time window, each with a delta vs the preceding equal-length window.
 *
 * Unlike NewDataStatsWidget (global scale-counts), every metric here is
 * scoped to what the viewer may see: events via createEventConditions,
 * attributes via MispAttribute::buildConditions, sightings via the
 * Plugin.Sightings_policy rules Sighting::eventsStatistic applies,
 * proposals via ShadowAttribute::buildConditions, correlations via the
 * active correlation engine's own ACL-aware lookup. Reuses StatGrid.
 */
class OverviewStatsWidget
{
    public $title = 'Overview';
    public $category = 'events';
    public $render = 'StatGrid';
    public $width = 12;
    public $height = 2;
    public $description = 'Stat strip: events, attributes, IDS-flagged '
        . 'attributes, correlation hits, sightings and pending proposals in '
        . 'the time window, each with a delta vs the previous window. Counts '
        . 'only what you can see.';
    public $params = [
        'time_window' => 'The time window, going back in seconds, to include '
            . '(e.g. "30d"; -1 = all history, no delta).',
    ];
    public $schema = [
        'time_window' => [
            'type' => 'time_window',
            'default' => 'P30D',
            'help' => 'Time window to count over; deltas compare with the '
                . 'preceding window of the same length.',
        ],
    ];
    public $placeholder = '{
    "time_window": "30d"
}';
    public $cacheLifetime = false;
    public $autoRefreshDelay = false;
    // ACL-scoped aggregate: same payload for every user of an org.
    public $cache_duration = 300;
    public $cache_scope = 'org';

    /** Most recent in-window events whose correlations are counted. */
    const CORRELATION_EVENT_CAP = 500;

    public function handler($user, $options = array())
    {
        $window = OverviewWidgetTool::parseWindow($options, 30 * OverviewWidgetTool::DAY);
        $now = time();
        $cur = $window === -1 ? [null, null] : [$now - $window, null];
        $prior = $window === -1 ? null : [$now - 2 * $window, $now - $window];

        $pair = function (callable $count) use ($cur, $prior) {
            return [
                $count($cur[0], $cur[1]),
                $prior === null ? null : $count($prior[0], $prior[1]),
            ];
        };
        $days = $window === -1 ? null : max(1, (int)round($window / OverviewWidgetTool::DAY)) . 'd';

        $events = $pair(function ($s, $e) use ($user) {
            return $this->countEvents($user, $s, $e);
        });
        $attributes = $pair(function ($s, $e) use ($user) {
            return $this->countAttributes($user, $s, $e, false);
        });
        $ids = $pair(function ($s, $e) use ($user) {
            return $this->countAttributes($user, $s, $e, true);
        });
        $correlations = $pair(function ($s, $e) use ($user) {
            return $this->countCorrelations($user, $s, $e);
        });
        $sightings = $pair(function ($s, $e) use ($user) {
            return $this->countSightings($user, $s, $e);
        });
        $proposals = $pair(function ($s, $e) use ($user) {
            return $this->countProposals($user, $s, $e);
        });

        $rows = [
            $this->row(__('Events'), $events, $days ? '/events/index/searchtimestamp:' . $days : '/events/index'),
            $this->row(__('Attributes'), $attributes, '/attributes/search'),
            $this->row(__('IDS-flagged'), $ids, null, number_format($ids[0]) . ' / ' . number_format($attributes[0])),
            $this->row(__('New correlations'), $correlations, null, null, __(
                'Correlation hits on the %s most recent events updated in the window.',
                self::CORRELATION_EVENT_CAP
            )),
            $this->row(__('Sightings'), $sightings),
            $this->row(__('Proposals pending'), $proposals, '/shadow_attributes/index'),
        ];
        return $rows;
    }

    private function row($title, array $pair, $drilldown = null, $display = null, $tooltip = null)
    {
        list($current, $previous) = $pair;
        $row = ['title' => $title, 'value' => $current === null ? __('N/A') : ($display ?? $current)];
        if ($current !== null && $previous !== null) {
            $row['change'] = $current - $previous;
        }
        if ($drilldown !== null) {
            $row['drilldown'] = $drilldown;
        }
        if ($tooltip !== null) {
            $row['tooltip'] = $tooltip;
        }
        return $row;
    }

    private function countEvents(array $user, $start, $end)
    {
        $eventModel = ClassRegistry::init('Event');
        $conditions = $eventModel->createEventConditions($user);
        $conditions += OverviewWidgetTool::window('Event.timestamp', $start, $end);
        return (int)$eventModel->find('count', ['recursive' => -1, 'conditions' => $conditions]);
    }

    private function countAttributes(array $user, $start, $end, $idsOnly)
    {
        $conditions = OverviewWidgetTool::window('Attribute.timestamp', $start, $end);
        $conditions['Attribute.deleted'] = 0;
        if ($idsOnly) {
            $conditions['Attribute.to_ids'] = 1;
        }
        list($conditions, $joins) = OverviewWidgetTool::attributeQuery($user, $conditions);
        return (int)ClassRegistry::init('MispAttribute')->find('count', [
            'recursive' => -1,
            'joins' => $joins,
            'conditions' => $conditions,
        ]);
    }

    /**
     * Correlation hits (related attributes, both directions) on the most
     * recent in-window visible events, through the active engine's
     * getAttributesRelatedToEvent — engine-agnostic and ACL-checked there.
     *
     * ponytail: capped at CORRELATION_EVENT_CAP events (and the engine's own
     * max_correlations_per_event); a per-attribute correlation timestamp
     * would allow an exact windowed count.
     */
    private function countCorrelations(array $user, $start, $end)
    {
        $eventModel = ClassRegistry::init('Event');
        $conditions = $eventModel->createEventConditions($user);
        $conditions += OverviewWidgetTool::window('Event.timestamp', $start, $end);
        $eventIds = $eventModel->find('column', [
            'recursive' => -1,
            'conditions' => $conditions,
            'fields' => ['Event.id'],
            'order' => ['Event.timestamp DESC'],
            'limit' => self::CORRELATION_EVENT_CAP,
        ]);
        if (empty($eventIds)) {
            return 0;
        }
        try {
            $sgids = $eventModel->SharingGroup->authorizedIds($user);
            $related = ClassRegistry::init('Correlation')
                ->getAttributesRelatedToEvent($user, array_values($eventIds), $sgids);
        } catch (Exception $e) {
            return null;
        }
        $hits = 0;
        foreach ($related as $list) {
            $hits += count($list);
        }
        return $hits;
    }

    /**
     * Sightings (type 0) in the window, visible under the instance's
     * sightings policy: all sightings on own-org events, and on other
     * visible events per Plugin.Sightings_policy (same rules as
     * Sighting::eventsStatistic).
     */
    private function countSightings(array $user, $start, $end)
    {
        $sightingModel = ClassRegistry::init('Sighting');
        $conditions = OverviewWidgetTool::window('Sighting.date_sighting', $start, $end);
        $conditions['Sighting.type'] = 0;
        $joins = [];
        if (empty($user['Role']['perm_site_admin'])) {
            $orgId = (int)$user['org_id'];
            $joins[] = OverviewWidgetTool::eventJoin('Sighting.event_id');
            $conditions['AND'][] = ClassRegistry::init('Event')->createEventConditions($user);
            $policy = Configure::read('Plugin.Sightings_policy');
            $policy = $policy === null ? Sighting::SIGHTING_POLICY_EVENT_OWNER : (int)$policy;
            if ($policy === Sighting::SIGHTING_POLICY_EVERYONE) {
                $foreign = null;
            } elseif ($policy === Sighting::SIGHTING_POLICY_SIGHTING_REPORTER) {
                $foreign = ['Sighting.event_id IN (SELECT event_id FROM sightings WHERE org_id = ' . $orgId . ')'];
            } elseif ($policy === Sighting::SIGHTING_POLICY_HOST_ORG) {
                $foreign = ['Sighting.org_id' => [$orgId, (int)Configure::read('MISP.host_org_id')]];
            } else {
                $foreign = ['Sighting.org_id' => $orgId];
            }
            if ($foreign !== null) {
                $conditions['AND'][] = ['OR' => [['Event.org_id' => $orgId], $foreign]];
            }
        }
        return (int)$sightingModel->find('count', [
            'recursive' => -1,
            'joins' => $joins,
            'conditions' => $conditions,
        ]);
    }

    /**
     * Open (not deleted) proposals raised in the window, visible per
     * ShadowAttribute::buildConditions (which needs Event + Attribute).
     */
    private function countProposals(array $user, $start, $end)
    {
        $proposalModel = ClassRegistry::init('ShadowAttribute');
        $conditions = OverviewWidgetTool::window('ShadowAttribute.timestamp', $start, $end);
        $conditions['ShadowAttribute.deleted'] = 0;
        $joins = [];
        $acl = $proposalModel->buildConditions($user);
        if (!empty($acl)) {
            $conditions['AND'][] = $acl;
            $joins = [
                OverviewWidgetTool::eventJoin('ShadowAttribute.event_id'),
                [
                    'table' => 'attributes',
                    'alias' => 'Attribute',
                    'type' => 'LEFT',
                    'conditions' => ['Attribute.id = ShadowAttribute.old_id'],
                ],
            ];
        }
        return (int)$proposalModel->find('count', [
            'recursive' => -1,
            'joins' => $joins,
            'conditions' => $conditions,
        ]);
    }
}
