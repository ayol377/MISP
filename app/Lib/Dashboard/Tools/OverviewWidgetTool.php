<?php

/**
 * Shared helpers for the ops-overview widgets (OverviewStatsWidget,
 * AttributedOriginMapWidget, AttributeIngestWidget, AttackTacticsWidget,
 * FeedSyncHealthWidget): time-window parsing and the ACL joins every
 * windowed count needs.
 *
 * ACL posture: every query built here is scoped by the viewer's event
 * visibility (Event::createEventConditions) and, for attributes, by
 * MispAttribute::buildConditions — the same conditions the event and
 * attribute indexes apply. Site admins get no filter (both return []).
 */
class OverviewWidgetTool
{
    const DAY = 86400;

    /**
     * Resolve the `time_window` option (already canonical-translated:
     * "<N>d", int seconds or -1) to seconds back from now, or -1 for all
     * time. Mirrors TrendingWidget::parseWindow.
     */
    public static function parseWindow(array $options, $default)
    {
        $tw = isset($options['time_window']) ? $options['time_window'] : null;
        if (is_string($tw) && substr($tw, -1) === 'd') {
            $days = (int)substr($tw, 0, -1);
            return $days > 0 ? $days * self::DAY : $default;
        }
        if ($tw === -1 || $tw === '-1') {
            return -1;
        }
        return ((int)$tw > 0) ? (int)$tw : $default;
    }

    /**
     * Short label for a window in seconds: 24h, 7d, 30d, all time.
     */
    public static function windowLabel($seconds)
    {
        if ($seconds === -1) {
            return __('all time');
        }
        if ($seconds < 2 * self::DAY) {
            return max(1, (int)round($seconds / 3600)) . 'h';
        }
        return (int)round($seconds / self::DAY) . 'd';
    }

    /**
     * `<field> >= $start AND <field> < $end` (unix seconds; null = open).
     */
    public static function window($field, $start, $end = null)
    {
        $conditions = [];
        if ($start !== null) {
            $conditions[$field . ' >='] = $start;
        }
        if ($end !== null) {
            $conditions[$field . ' <'] = $end;
        }
        return $conditions;
    }

    /**
     * INNER JOIN events AS Event on the given foreign key.
     */
    public static function eventJoin($foreignKey)
    {
        return [
            'table' => 'events',
            'alias' => 'Event',
            'type' => 'INNER',
            'conditions' => ['Event.id = ' . $foreignKey],
        ];
    }

    /**
     * INNER JOIN tags AS Tag on EventTag.tag_id.
     */
    public static function eventTagTagJoin()
    {
        return [
            'table' => 'tags',
            'alias' => 'Tag',
            'type' => 'INNER',
            'conditions' => ['Tag.id = EventTag.tag_id'],
        ];
    }

    /**
     * Conditions + joins for an ACL-scoped query on MispAttribute.
     * buildConditions() references Event.* and Object.*, so both are
     * joined for non-admins; site admins skip the joins entirely.
     *
     * @return array [conditions, joins]
     */
    public static function attributeQuery(array $user, array $conditions)
    {
        $attributeModel = ClassRegistry::init('MispAttribute');
        $acl = $attributeModel->buildConditions($user);
        if (empty($acl)) {
            return [$conditions, []];
        }
        $conditions['AND'][] = $acl;
        return [$conditions, [
            self::eventJoin('Attribute.event_id'),
            [
                'table' => 'objects',
                'alias' => 'Object',
                'type' => 'LEFT',
                'conditions' => ['Object.id = Attribute.object_id'],
            ],
        ]];
    }

    /**
     * Event-level (event_id, tag name, event timestamp) rows for tags whose
     * name starts with $tagPrefix, on events the user may see, with
     * Event.timestamp >= $start (null = all time).
     *
     * ponytail: capped at $limit rows; switch to SQL-side grouping if
     * instances routinely exceed it within one window.
     */
    public static function eventTagRows(array $user, $tagPrefix, $start, $limit = 50000)
    {
        $eventModel = ClassRegistry::init('Event');
        $conditions = $eventModel->createEventConditions($user);
        $conditions += self::window('Event.timestamp', $start);
        $conditions['Tag.name LIKE'] = $tagPrefix . '%';
        $rows = ClassRegistry::init('EventTag')->find('all', [
            'recursive' => -1,
            'fields' => ['EventTag.event_id', 'Tag.name', 'Event.timestamp'],
            'joins' => [self::eventJoin('EventTag.event_id'), self::eventTagTagJoin()],
            'conditions' => $conditions,
            'limit' => $limit,
        ]);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                (int)$row['EventTag']['event_id'],
                (string)$row['Tag']['name'],
                (int)$row['Event']['timestamp'],
            ];
        }
        return $out;
    }

    /**
     * Galaxy element values under $key for the clusters whose tag_name is
     * in $tagNames, restricted to clusters the user may see.
     *
     * @return array tag_name => list of element values
     */
    public static function clusterElements(array $user, array $tagNames, $key)
    {
        if (empty($tagNames)) {
            return [];
        }
        $clusterModel = ClassRegistry::init('GalaxyCluster');
        $conditions = $clusterModel->buildConditions($user);
        $conditions['GalaxyCluster.tag_name'] = array_values($tagNames);
        $conditions['GalaxyCluster.deleted'] = 0;
        $rows = $clusterModel->find('all', [
            'recursive' => -1,
            'fields' => ['GalaxyCluster.tag_name', 'GalaxyElement.value'],
            'joins' => [[
                'table' => 'galaxy_elements',
                'alias' => 'GalaxyElement',
                'type' => 'INNER',
                'conditions' => [
                    'GalaxyElement.galaxy_cluster_id = GalaxyCluster.id',
                    'GalaxyElement.key' => $key,
                ],
            ]],
            'conditions' => $conditions,
        ]);
        $out = [];
        foreach ($rows as $row) {
            $value = (string)$row['GalaxyElement']['value'];
            $out[$row['GalaxyCluster']['tag_name']][$value] = $value;
        }
        return array_map('array_values', $out);
    }

    /**
     * Display name from a galaxy tag: misp-galaxy:x="Name" -> Name.
     */
    public static function clusterName($tagName)
    {
        return preg_match('/="(.*)"$/', $tagName, $m) ? $m[1] : $tagName;
    }
}
