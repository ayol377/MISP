<?php

App::uses('OverviewWidgetTool', 'Lib/Dashboard/Tools');
App::uses('WidgetCache', 'Lib/Dashboard/Tools');

/**
 * Panel data for the SOC wall (app/webroot/wall/, JSON from
 * DashboardsController::wall()). panel($user, $key) returns a
 * JSON-ready array, or null for an unknown panel or one the viewer may
 * not see.
 *
 * Widget-backed panels call the dashboard widget's own handler() (same
 * ACL scoping and checkPermissions as the board) with the widget cache
 * shortened to TTL under a separate key; the rest are bounded,
 * ACL-scoped queries cached per org for TTL.
 *
 * Event-centric panels leave out daily IOC-dump events (freetext /
 * CSV feed events, OverviewWidgetTool::dumpEventExclusion()); attribute
 * panels keep them.
 */
class SocWallTool
{
    const TTL = 60;
    const HOUR = 3600;
    const DAY = 86400;

    const PANELS = [
        'stats', 'threats', 'ingest', 'ioc', 'sources', 'indicators',
        'tactics', 'category', 'tlp', 'actors', 'tags', 'sync', 'ticker',
    ];

    /** IOC radar axes, in drawing order (clockwise from the top). */
    const IOC_AXES = ['IP', 'Domain', 'URL', 'Hash', 'Email', 'Host artefact', 'CVE', 'Other'];

    /** Exact attribute type => IOC axis; iocBucket() covers the families. */
    const IOC_TYPES = [
        'ip-src' => 'IP', 'ip-dst' => 'IP', 'ip-src|port' => 'IP', 'ip-dst|port' => 'IP',
        'domain' => 'Domain', 'hostname' => 'Domain', 'domain|ip' => 'Domain',
        'hostname|port' => 'Domain',
        'url' => 'URL', 'uri' => 'URL', 'link' => 'URL',
        'whois-registrant-email' => 'Email', 'target-email' => 'Email',
        'filename' => 'Host artefact', 'filename-pattern' => 'Host artefact',
        'regkey' => 'Host artefact', 'regkey|value' => 'Host artefact',
        'mutex' => 'Host artefact', 'named pipe' => 'Host artefact',
        'windows-service-name' => 'Host artefact',
        'windows-service-displayname' => 'Host artefact',
        'windows-scheduled-task' => 'Host artefact', 'pdb' => 'Host artefact',
        'yara' => 'Host artefact', 'sigma' => 'Host artefact',
        'vulnerability' => 'CVE',
    ];

    /** Hash families, with or without a "filename|" prefix. */
    const IOC_HASH_RE = '/^(filename\|)?(md5|sha\d|ssdeep|imphash|tlsh|authentihash|pehash|impfuzzy|telfhash|vhash|cdhash|ja3|jarm|hassh|x509-fingerprint)/';

    /** ATT&CK radar axes (tactic => label), clockwise from the top. */
    const TACTIC_AXES = [
        'initial-access' => 'Initial access',
        'execution' => 'Execution',
        'persistence' => 'Persistence',
        'privilege-escalation' => 'Priv. escalation',
        'defense-evasion' => 'Defense evasion',
        'credential-access' => 'Credential access',
        'discovery' => 'Discovery',
        'lateral-movement' => 'Lateral movement',
        'command-and-control' => 'C2',
        'exfiltration' => 'Exfiltration',
        'impact' => 'Impact',
    ];

    /** ATT&CK v18 split of defense evasion, folded back into its axis. */
    const TACTIC_ALIASES = ['stealth' => 'defense-evasion', 'defense-impairment' => 'defense-evasion'];

    public static function panel(array $user, $key)
    {
        if (!in_array($key, self::PANELS, true)) {
            return null;
        }
        $method = 'panel' . ucfirst($key);
        return self::$method($user);
    }

    /**
     * IOC radar axis of an attribute type.
     */
    public static function iocBucket($type)
    {
        if (isset(self::IOC_TYPES[$type])) {
            return self::IOC_TYPES[$type];
        }
        if (preg_match(self::IOC_HASH_RE, $type)) {
            return 'Hash';
        }
        return strpos($type, 'email') === 0 ? 'Email' : 'Other';
    }

    // ---- widget-backed panels ------------------------------------------

    /**
     * A dashboard widget's handler() payload, cached for at most TTL under
     * a key of its own (the board's longer-lived entries are left alone).
     * null when the viewer may not load the widget.
     */
    private static function widget(array $user, $class, array $options)
    {
        $widget = ClassRegistry::init('Dashboard')->loadWidget($user, $class, true);
        if ($widget === false) {
            return null;
        }
        // Same scope as the widget declares; shorter TTL, own key prefix.
        $holder = (object)[
            'cache_duration' => self::TTL,
            'cache_path' => WidgetCache::path($widget) . ':wall',
            'cache_scope' => $widget->cache_scope ?? null,
        ];
        return WidgetCache::remember($holder, $options, function () use ($widget, $user, $options) {
            return $widget->handler($user, $options);
        }, $user);
    }

    /** Own query, cached per org bucket (site admins share one) for TTL. */
    private static function cached(array $user, $key, callable $compute)
    {
        $holder = (object)[
            'cache_duration' => self::TTL,
            'cache_path' => 'misp:soc_wall_cache:' . $key,
            'cache_scope' => 'org',
        ];
        return WidgetCache::remember($holder, [], $compute, $user);
    }

    private static function feedRows(array $user)
    {
        $data = self::widget($user, 'FeedSyncHealthWidget', ['time_window' => '7d', 'limit' => 200]);
        return $data === null ? null : (array)($data['rows'] ?? []);
    }

    private static function panelStats(array $user)
    {
        $rows = self::widget($user, 'OverviewStatsWidget', ['time_window' => '1d', 'exclude_dump_events' => 1]);
        $keys = ['events', 'attributes', 'ids', 'correlations', 'sightings', 'proposals'];
        $by = [];
        foreach (array_values((array)$rows) as $i => $row) {
            if (isset($keys[$i])) {
                $by[$keys[$i]] = ['value' => $row['raw'] ?? null, 'previous' => $row['previous'] ?? null];
            }
        }
        $high = self::cached($user, 'high', function () use ($user) {
            $now = time();
            return [
                'value' => self::countEvents($user, ['Event.threat_level_id' => 1], $now - self::DAY),
                'previous' => self::countEvents($user, ['Event.threat_level_id' => 1], $now - 2 * self::DAY, $now - self::DAY),
            ];
        });
        $stats = [
            ['key' => 'events', 'label' => __('Events, 24h')] + ($by['events'] ?? []),
            ['key' => 'high', 'label' => __('High threat, 24h')] + $high,
            ['key' => 'attributes', 'label' => __('Attributes, 24h')] + ($by['attributes'] ?? []),
            ['key' => 'ids', 'label' => __('IDS-flagged, 24h'), 'of' => $by['attributes']['value'] ?? null] + ($by['ids'] ?? []),
            ['key' => 'sightings', 'label' => __('Sightings, 24h')] + ($by['sightings'] ?? []),
            ['key' => 'correlations', 'label' => __('New correlations')] + ($by['correlations'] ?? []),
            ['key' => 'proposals', 'label' => __('Proposals pending')] + ($by['proposals'] ?? []),
        ];
        // Feed health is site-admin data; for others the stat is left out
        // (and the sync panel answers 404).
        $feeds = self::feedRows($user);
        if ($feeds !== null) {
            $feeds = array_filter($feeds, function ($row) {
                return ($row['kind'] ?? '') === 'feed';
            });
            $healthy = count(array_filter($feeds, function ($row) {
                return ($row['status'] ?? '') === 'ok';
            }));
            $stats[] = ['key' => 'feeds', 'label' => __('Feeds healthy'), 'value' => $healthy, 'of' => count($feeds)];
        }
        return [
            'org' => (string)($user['Organisation']['name'] ?? ''),
            'stats' => $stats,
        ];
    }

    private static function panelIngest(array $user)
    {
        $data = self::widget($user, 'AttributeIngestWidget', ['time_window' => '2d', 'forecast' => false]);
        $values = array_map('intval', (array)($data['values'] ?? []));
        // 49 hourly buckets (the first one partial); keep the last 48.
        $drop = max(0, count($values) - 48);
        return [
            'values' => array_slice($values, $drop),
            'start' => (int)($data['start'] ?? 0) + $drop * self::HOUR,
            'bucket' => self::HOUR,
        ];
    }

    /**
     * Attributes per tactic: last 28 days vs the 28 before. An attribute
     * counts via its own or its event's Enterprise ATT&CK galaxy tags
     * (mitre-attack-pattern, and the legacy mitre-enterprise-attack-*
     * galaxies), dump events included.
     */
    private static function panelTactics(array $user)
    {
        return self::cached($user, 'tactics', function () use ($user) {
            $prefix = 'misp-galaxy:mitre-';
            $tagNames = ClassRegistry::init('Tag')->find('column', [
                'conditions' => ['Tag.name LIKE' => $prefix . '%'],
                'fields' => ['Tag.name'],
            ]);
            $tactics = [];
            foreach (OverviewWidgetTool::clusterElements($user, $tagNames, 'kill_chain') as $tagName => $chains) {
                foreach ($chains as $chain) {
                    if (preg_match('/^(attack-|mitre-attack:enterprise-attack:)/', $chain) && ($pos = strrpos($chain, ':')) !== false) {
                        $tactic = substr($chain, $pos + 1);
                        $tactic = self::TACTIC_ALIASES[$tactic] ?? $tactic;
                        $tactics[$tagName][$tactic] = $tactic;
                    }
                }
            }
            $keysOf = function ($tagName) use ($tactics) {
                return $tactics[$tagName] ?? [];
            };
            $now = time();
            $axes = array_fill_keys(array_keys(self::TACTIC_AXES), 0);
            $series = function ($start, $end) use ($user, $prefix, $keysOf, $axes) {
                $counts = self::attributeTagCounts($user, $prefix, $start, $end, $keysOf);
                return array_values(array_replace($axes, array_intersect_key($counts, $axes)));
            };
            return [
                'axes' => array_values(self::TACTIC_AXES),
                'current' => $series($now - 28 * self::DAY, null),
                'previous' => $series($now - 56 * self::DAY, $now - 28 * self::DAY),
            ];
        });
    }

    private static function panelActors(array $user)
    {
        $rows = self::widget($user, 'TrendingWidget', [
            'dimension' => 'threat-actor', 'threshold' => 7,
            'time_window' => '7d', 'exclude_dump_events' => 1,
        ]);
        $out = [];
        foreach ((array)$rows as $row) {
            $out[] = ['label' => (string)($row['label'] ?? ''), 'count' => (int)($row['count'] ?? 0)];
        }
        return ['rows' => $out];
    }

    private static function panelTags(array $user)
    {
        $data = self::widget($user, 'TrendingTagsWidget', ['time_window' => '1d', 'threshold' => 7, 'exclude_dump_events' => 1]);
        $out = [];
        foreach ((array)($data['data'] ?? []) as $name => $count) {
            $out[] = [
                'name' => (string)$name,
                'count' => (int)$count,
                'colour' => self::colour($data['colours'][$name] ?? null),
            ];
        }
        return ['rows' => $out];
    }

    private static function panelSync(array $user)
    {
        $rows = self::feedRows($user);
        if ($rows === null) {
            return null;
        }
        $out = [];
        foreach (array_slice($rows, 0, 8) as $row) {
            $out[] = [
                'name' => (string)($row['name'] ?? ''),
                'kind' => (string)($row['kind'] ?? ''),
                'status' => (string)($row['status'] ?? 'warn'),
                'last' => isset($row['last']) ? (int)$row['last'] : null,
            ];
        }
        return ['rows' => $out];
    }

    // ---- own queries ---------------------------------------------------

    /** Visible, non-dump events matching $extra in [$start, $end). */
    private static function eventConditions(array $user, array $extra, $start, $end = null)
    {
        $conditions = ClassRegistry::init('Event')->createEventConditions($user);
        $conditions += OverviewWidgetTool::window('Event.timestamp', $start, $end);
        $conditions += $extra;
        $dumps = OverviewWidgetTool::dumpEventExclusion();
        if (!empty($dumps)) {
            $conditions[] = $dumps;
        }
        return $conditions;
    }

    private static function countEvents(array $user, array $extra, $start, $end = null)
    {
        return (int)ClassRegistry::init('Event')->find('count', [
            'recursive' => -1,
            'conditions' => self::eventConditions($user, $extra, $start, $end),
        ]);
    }

    /** Event rows with the creator org's name. */
    private static function eventRows(array $conditions, array $fields, $order, $limit)
    {
        return ClassRegistry::init('Event')->find('all', [
            'recursive' => -1,
            'fields' => array_merge($fields, ['Orgc.name']),
            'joins' => [[
                'table' => 'organisations',
                'alias' => 'Orgc',
                'type' => 'LEFT',
                'conditions' => ['Orgc.id = Event.orgc_id'],
            ]],
            'conditions' => $conditions,
            'order' => $order,
            'limit' => $limit,
        ]);
    }

    /**
     * Newest non-dump events (Event.timestamp, last 7 days),
     * each with its first TLP tag the viewer may see.
     */
    private static function panelThreats(array $user)
    {
        return self::cached($user, 'threats', function () use ($user) {
            $events = self::eventRows(
                self::eventConditions($user, [], time() - 7 * self::DAY),
                ['Event.id', 'Event.info', 'Event.threat_level_id', 'Event.timestamp'],
                ['Event.timestamp DESC'],
                20
            );
            $ids = array_map(function ($e) {
                return (int)$e['Event']['id'];
            }, $events);
            $tlp = self::tlpTags($user, $ids);
            $rows = [];
            foreach ($events as $e) {
                $id = (int)$e['Event']['id'];
                $rows[] = [
                    'id' => $id,
                    'info' => (string)$e['Event']['info'],
                    'level' => (int)$e['Event']['threat_level_id'],
                    'org' => (string)($e['Orgc']['name'] ?? ''),
                    'ts' => (int)$e['Event']['timestamp'],
                    'tlp' => $tlp[$id] ?? null,
                ];
            }
            return ['rows' => $rows];
        });
    }

    /** event_id => {name, colour} of one TLP tag the viewer may see. */
    private static function tlpTags(array $user, array $eventIds)
    {
        if (empty($eventIds)) {
            return [];
        }
        $conditions = ['EventTag.event_id' => $eventIds, 'Tag.name LIKE' => 'tlp:%'];
        if (empty($user['Role']['perm_site_admin'])) {
            $conditions['Tag.org_id'] = [0, (int)$user['org_id']];
            $conditions['Tag.user_id'] = [0, (int)$user['id']];
        }
        $rows = ClassRegistry::init('EventTag')->find('all', [
            'recursive' => -1,
            'fields' => ['EventTag.event_id', 'Tag.name', 'Tag.colour'],
            'joins' => [OverviewWidgetTool::eventTagTagJoin()],
            'conditions' => $conditions,
        ]);
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['EventTag']['event_id']] = [
                'name' => (string)$row['Tag']['name'],
                'colour' => self::colour($row['Tag']['colour']),
            ];
        }
        return $out;
    }

    /**
     * New attributes (7d) per TLP level, by their own or their event's
     * tlp: tag, dump events included; untagged ones are left out.
     */
    private static function panelTlp(array $user)
    {
        return self::cached($user, 'tlp', function () use ($user) {
            $levels = ['red' => 'red', 'amber' => 'amber', 'amber+strict' => 'amber', 'green' => 'green', 'clear' => 'clear', 'white' => 'clear'];
            $counts = self::attributeTagCounts($user, 'tlp:', time() - 7 * self::DAY, null, function ($tagName) use ($levels) {
                $level = $levels[strtolower(substr($tagName, 4))] ?? null;
                return $level === null ? [] : [$level];
            });
            $out = [];
            foreach (['red', 'amber', 'green', 'clear'] as $level) {
                $out[$level] = $counts[$level] ?? 0;
            }
            return $out;
        });
    }

    /**
     * Non-deleted attributes the viewer may see (Attribute.timestamp in
     * [$start, $end)) per key, where an attribute's tags are its own plus
     * its event's. $keysOf maps a tag name (starting with $tagPrefix) to
     * the keys it counts towards; each attribute counts once per key.
     *
     * ponytail: attribute-level tag rows are loaded in PHP, capped at
     * 50000; group in SQL if a window routinely exceeds that.
     */
    private static function attributeTagCounts(array $user, $tagPrefix, $start, $end, callable $keysOf)
    {
        $base = OverviewWidgetTool::window('Attribute.timestamp', $start, $end);
        $base['Attribute.deleted'] = 0;
        $base['Tag.name LIKE'] = $tagPrefix . '%';
        list($conditions, $joins) = OverviewWidgetTool::attributeQuery($user, $base);
        $find = function (array $tagJoins, array $query) use ($conditions, $joins) {
            return ClassRegistry::init('MispAttribute')->find('all', $query + [
                'recursive' => -1,
                'joins' => array_merge($joins, $tagJoins),
                'conditions' => $conditions,
            ]);
        };
        $tagJoin = function ($table, $alias, $on, $tagId) {
            return [
                ['table' => $table, 'alias' => $alias, 'type' => 'INNER', 'conditions' => [$on]],
                ['table' => 'tags', 'alias' => 'Tag', 'type' => 'INNER', 'conditions' => ['Tag.id = ' . $tagId]],
            ];
        };

        $counts = [];
        $covered = []; // key => [event_id => true], counted via an event tag
        $rows = $find($tagJoin('event_tags', 'EventTag', 'EventTag.event_id = Attribute.event_id', 'EventTag.tag_id'), [
            'fields' => ['Attribute.event_id', 'Tag.name', 'COUNT(*) AS total'],
            'group' => ['Attribute.event_id', 'Tag.name'],
        ]);
        foreach ($rows as $row) {
            $eventId = (int)$row['Attribute']['event_id'];
            foreach ($keysOf((string)$row['Tag']['name']) as $key) {
                if (!isset($covered[$key][$eventId])) {
                    $covered[$key][$eventId] = true;
                    $counts[$key] = ($counts[$key] ?? 0) + (int)$row[0]['total'];
                }
            }
        }

        $seen = [];
        $rows = $find($tagJoin('attribute_tags', 'AttributeTag', 'AttributeTag.attribute_id = Attribute.id', 'AttributeTag.tag_id'), [
            'fields' => ['Attribute.id', 'Attribute.event_id', 'Tag.name'],
            'limit' => 50000,
        ]);
        foreach ($rows as $row) {
            $id = (int)$row['Attribute']['id'];
            $eventId = (int)$row['Attribute']['event_id'];
            foreach ($keysOf((string)$row['Tag']['name']) as $key) {
                if (!isset($covered[$key][$eventId]) && !isset($seen[$key][$id])) {
                    $seen[$key][$id] = true;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }
        }
        return $counts;
    }

    /** Newest published events (publish_timestamp, last 7 days). */
    private static function panelTicker(array $user)
    {
        return self::cached($user, 'ticker', function () use ($user) {
            $conditions = self::eventConditions($user, ['Event.published' => 1], null);
            $conditions['Event.publish_timestamp >='] = time() - 7 * self::DAY;
            $rows = [];
            foreach (self::eventRows($conditions, ['Event.id', 'Event.info'], ['Event.publish_timestamp DESC'], 15) as $e) {
                $rows[] = [
                    'id' => (int)$e['Event']['id'],
                    'info' => (string)$e['Event']['info'],
                    'org' => (string)($e['Orgc']['name'] ?? ''),
                ];
            }
            return ['rows' => $rows];
        });
    }

    /**
     * ACL-scoped attribute find (non-deleted, Attribute.timestamp >=
     * $start), always joined to its event.
     */
    private static function attributes(array $user, array $conditions, $start, array $query)
    {
        $conditions += OverviewWidgetTool::window('Attribute.timestamp', $start);
        $conditions['Attribute.deleted'] = 0;
        list($conditions, $joins) = OverviewWidgetTool::attributeQuery($user, $conditions);
        if (!in_array('Event', array_column($joins, 'alias'), true)) {
            $joins[] = OverviewWidgetTool::eventJoin('Attribute.event_id');
        }
        return ClassRegistry::init('MispAttribute')->find('all', $query + [
            'recursive' => -1,
            'joins' => $joins,
            'conditions' => $conditions,
        ]);
    }

    /** IDS attributes per type bucket: last 24h vs 7-day daily average. */
    private static function panelIoc(array $user)
    {
        return self::cached($user, 'ioc', function () use ($user) {
            $now = time();
            $count = function ($start) use ($user) {
                $totals = array_fill_keys(self::IOC_AXES, 0);
                $rows = self::attributes($user, ['Attribute.to_ids' => 1], $start, [
                    'fields' => ['Attribute.type', 'COUNT(*) AS total'],
                    'group' => ['Attribute.type'],
                ]);
                foreach ($rows as $row) {
                    $totals[self::iocBucket($row['Attribute']['type'])] += (int)$row[0]['total'];
                }
                return $totals;
            };
            $week = $count($now - 7 * self::DAY);
            return [
                'axes' => self::IOC_AXES,
                'day' => array_values($count($now - self::DAY)),
                'week_avg' => array_map(function ($n) {
                    return round($n / 7, 1);
                }, array_values($week)),
            ];
        });
    }

    /** IDS attributes (24h) per creator org of their event, top 7. */
    private static function panelSources(array $user)
    {
        return self::cached($user, 'sources', function () use ($user) {
            $rows = self::attributes($user, ['Attribute.to_ids' => 1], time() - self::DAY, [
                'fields' => ['Event.orgc_id', 'COUNT(*) AS total'],
                'group' => ['Event.orgc_id'],
                'order' => ['COUNT(*) DESC'],
                'limit' => 7,
            ]);
            $names = ClassRegistry::init('Organisation')->find('list', [
                'recursive' => -1,
                'fields' => ['Organisation.id', 'Organisation.name'],
                'conditions' => ['Organisation.id' => array_map(function ($r) {
                    return (int)$r['Event']['orgc_id'];
                }, $rows)],
            ]);
            $out = [];
            foreach ($rows as $row) {
                $out[] = ['org' => (string)($names[$row['Event']['orgc_id']] ?? ''), 'count' => (int)$row[0]['total']];
            }
            return ['rows' => $out];
        });
    }

    /** Attributes (24h) per category, top 8. */
    private static function panelCategory(array $user)
    {
        return self::cached($user, 'category', function () use ($user) {
            $rows = self::attributes($user, [], time() - self::DAY, [
                'fields' => ['Attribute.category', 'COUNT(*) AS total'],
                'group' => ['Attribute.category'],
                'order' => ['COUNT(*) DESC'],
                'limit' => 8,
            ]);
            $out = [];
            foreach ($rows as $row) {
                $out[] = ['category' => (string)$row['Attribute']['category'], 'count' => (int)$row[0]['total']];
            }
            return ['rows' => $out];
        });
    }

    /** Newest to_ids attributes (Attribute.timestamp, last 7 days). */
    private static function panelIndicators(array $user)
    {
        return self::cached($user, 'indicators', function () use ($user) {
            $rows = self::attributes($user, ['Attribute.to_ids' => 1], time() - 7 * self::DAY, [
                'fields' => ['Attribute.type', 'Attribute.value1', 'Attribute.value2', 'Attribute.event_id', 'Attribute.timestamp', 'Event.orgc_id'],
                'order' => ['Attribute.timestamp DESC'],
                'limit' => 25,
            ]);
            $names = ClassRegistry::init('Organisation')->find('list', [
                'recursive' => -1,
                'fields' => ['Organisation.id', 'Organisation.name'],
                'conditions' => ['Organisation.id' => array_unique(array_map(function ($r) {
                    return (int)$r['Event']['orgc_id'];
                }, $rows))],
            ]);
            $out = [];
            foreach ($rows as $row) {
                $a = $row['Attribute'];
                $out[] = [
                    'type' => (string)$a['type'],
                    'value' => (string)$a['value1'] . ((string)$a['value2'] !== '' ? '|' . $a['value2'] : ''),
                    'event_id' => (int)$a['event_id'],
                    'org' => (string)($names[$row['Event']['orgc_id']] ?? ''),
                    'ts' => (int)$a['timestamp'],
                ];
            }
            return ['rows' => $out];
        });
    }

    /** A tag colour, only when it is a plain #rgb / #rrggbb value. */
    private static function colour($colour)
    {
        return is_string($colour) && preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $colour) ? $colour : null;
    }
}
