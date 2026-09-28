<?php

App::uses('OverviewWidgetTool', 'Lib/Dashboard/Tools');

/**
 * FeedSyncHealthWidget — "Feeds and sync": one row per enabled feed and
 * per pull/push-enabled sync server, with a status, a bar sparkline of
 * completed fetch/pull/push jobs per bucket over the time window (failed
 * buckets flagged) and the time of the last successful run.
 *
 * Source: the jobs table (fetch_feeds / feed_fetch for feeds, pull / push
 * for servers; job_input "Feed: <id>" / "Server: <id>"), which both the UI
 * and the cron shells write. Status: danger when the latest job failed,
 * warn when nothing succeeded in the window, ok otherwise.
 *
 * Site admins only: jobs, feeds and servers are site-admin data.
 * Rendered by the SyncHealth kind.
 */
class FeedSyncHealthWidget
{
    public $title = 'Feeds and sync';
    public $category = 'status';
    public $render = 'SyncHealth';
    public $width = 4;
    public $height = 4;
    public $description = 'Status, activity sparkline and last successful '
        . 'run of each enabled feed and sync server, from the job log.';
    public $params = [
        'time_window' => 'The time window, going back in seconds, for the '
            . 'sparkline (e.g. "7d"; -1 = the last 30 days).',
        'limit' => 'Maximum number of rows (failing ones first). Default: 8.',
    ];
    public $schema = [
        'time_window' => [
            'type' => 'time_window',
            'default' => 'P7D',
            'help' => 'Window covered by the sparkline and the status.',
        ],
        'limit' => [
            'type' => 'int',
            'default' => 8,
            'help' => 'Maximum number of rows; failing sources are listed first.',
        ],
    ];
    public $placeholder = '{
    "time_window": "7d",
    "limit": 8
}';
    public $cacheLifetime = false;
    public $autoRefreshDelay = false;
    public $cache_duration = 300;

    const BARS = 18;
    const JOB_CAP = 5000;

    public function checkPermissions($user)
    {
        return !empty($user['Role']['perm_site_admin']);
    }

    public function handler($user, $options = array())
    {
        $window = OverviewWidgetTool::parseWindow($options, 7 * OverviewWidgetTool::DAY);
        if ($window === -1) {
            $window = 30 * OverviewWidgetTool::DAY;
        }
        $limit = (isset($options['limit']) && (int)$options['limit'] > 0) ? (int)$options['limit'] : 8;
        $now = time();
        $start = $now - $window;
        $bucket = $window / self::BARS;

        $sources = [];
        $feeds = ClassRegistry::init('Feed')->find('all', [
            'recursive' => -1,
            'fields' => ['Feed.id', 'Feed.name'],
            'conditions' => ['Feed.enabled' => 1],
        ]);
        foreach ($feeds as $feed) {
            $sources['Feed: ' . $feed['Feed']['id']] = [
                'name' => $feed['Feed']['name'],
                'kind' => 'feed',
                'drilldown' => '/feeds/view/' . (int)$feed['Feed']['id'],
            ];
        }
        $servers = ClassRegistry::init('Server')->find('all', [
            'recursive' => -1,
            'fields' => ['Server.id', 'Server.name'],
            'conditions' => ['OR' => ['Server.pull' => 1, 'Server.push' => 1]],
        ]);
        foreach ($servers as $server) {
            $sources['Server: ' . $server['Server']['id']] = [
                'name' => $server['Server']['name'],
                'kind' => 'server',
                'drilldown' => '/servers/index',
            ];
        }
        if (empty($sources)) {
            return ['rows' => []];
        }
        foreach ($sources as $key => $source) {
            $sources[$key] += ['bars' => array_fill(0, self::BARS, 0), 'alerts' => [], 'last' => null, 'latest' => null];
        }

        // Job dates are written with date() (server-local), so compare in kind.
        $jobs = ClassRegistry::init('Job')->find('all', [
            'recursive' => -1,
            'fields' => ['Job.job_input', 'Job.status', 'Job.date_modified'],
            'conditions' => [
                'Job.job_type' => ['fetch_feeds', 'feed_fetch', 'pull', 'push'],
                'Job.job_input' => array_keys($sources),
                'Job.date_modified >=' => date('Y-m-d H:i:s', $start),
            ],
            'order' => ['Job.id ASC'],
            'limit' => self::JOB_CAP,
        ]);
        foreach ($jobs as $job) {
            $key = $job['Job']['job_input'];
            $ts = strtotime($job['Job']['date_modified']);
            $status = (int)$job['Job']['status'];
            $slot = (int)min(self::BARS - 1, max(0, floor(($ts - $start) / $bucket)));
            if ($status === Job::STATUS_COMPLETED) {
                $sources[$key]['bars'][$slot]++;
                $sources[$key]['last'] = max((int)$sources[$key]['last'], $ts);
            } elseif ($status === Job::STATUS_FAILED) {
                $sources[$key]['alerts'][$slot] = $slot;
            }
            if ($status === Job::STATUS_COMPLETED || $status === Job::STATUS_FAILED) {
                $sources[$key]['latest'] = $status;
            }
        }

        $rank = ['danger' => 0, 'warn' => 1, 'ok' => 2];
        $rows = [];
        foreach ($sources as $source) {
            if ($source['latest'] === Job::STATUS_FAILED) {
                $status = 'danger';
            } elseif ($source['last'] === null) {
                $status = 'warn';
            } else {
                $status = 'ok';
            }
            $source['status'] = $status;
            $source['alerts'] = array_values($source['alerts']);
            unset($source['latest']);
            $rows[] = $source;
        }
        usort($rows, function ($a, $b) use ($rank) {
            return [$rank[$a['status']], $a['name']] <=> [$rank[$b['status']], $b['name']];
        });
        return ['rows' => array_slice($rows, 0, $limit), 'total' => count($rows)];
    }
}
