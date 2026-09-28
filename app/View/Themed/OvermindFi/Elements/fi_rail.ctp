<?php
/**
 * OvermindFi side rail. Replaces both the Overmind navbar and the legacy
 * global_menu, so it must not depend on Bootstrap JS: groups are native
 * <details>, handlers are plain JS.
 *
 * Every entry comes from NavbarHelper::build(), which already filters by
 * ACL; this element only regroups and relabels the top-level menus.
 *
 * @var array $menus ['left' => [...], 'right' => [...]]
 * @var bool $bs5 Bootstrap 5 page (false on legacy BS2 pages)
 */
$bs5 = !empty($bs5);
$herePath = rtrim(strtok($this->request->here, '?'), '/');
$currentController = strtolower(str_replace('_', '', $this->request->params['controller']));

$pathOf = function ($url) {
    return empty($url) ? '' : rtrim((string)parse_url($url, PHP_URL_PATH), '/');
};

// Resolve the one active link: exact or prefix path match first, then any
// link to the current controller (e.g. events/view highlights Events).
$links = [];
$collect = function (array $items) use (&$collect, &$links) {
    foreach ($items as $item) {
        if (!empty($item['url'])) {
            $links[] = $item;
        }
        if (!empty($item['children'])) {
            $collect($item['children']);
        }
    }
};
$collect($menus['left']);
$activeUrl = null;
$bestLength = 0;
foreach ($links as $link) {
    $path = $pathOf($link['url']);
    if ($path !== '' && ($herePath === $path || strpos($herePath . '/', $path . '/') === 0) && strlen($path) > $bestLength) {
        $activeUrl = $link['url'];
        $bestLength = strlen($path);
    }
}
if ($activeUrl === null) {
    foreach ($links as $link) {
        if (!empty($link['controller']) && strtolower(str_replace('_', '', $link['controller'])) === $currentController) {
            $activeUrl = $link['url'];
            break;
        }
    }
}

$containsActive = function (array $item) use (&$containsActive, $activeUrl) {
    if ($activeUrl !== null && ($item['url'] ?? null) === $activeUrl) {
        return true;
    }
    foreach ($item['children'] ?? [] as $child) {
        if (is_array($child) && $containsActive($child)) {
            return true;
        }
    }
    return false;
};

$icon = function (array $item) {
    if (!empty($item['image'])) {
        return $item['image'];
    }
    return empty($item['icon']) ? '' : '<i class="' . h($item['icon']) . ' fa-fw" aria-hidden="true"></i>';
};

$renderLink = function (array $item, $class = '') use ($activeUrl, $icon) {
    $active = $activeUrl !== null && $item['url'] === $activeUrl;
    return '<a class="mfi-link' . ($active ? ' active' : '') . $class . '" href="' . h($item['url']) . '"'
        . ($active ? ' aria-current="page"' : '') . '>'
        . $icon($item) . '<span>' . h($item['label']) . '</span></a>';
};

// Render the children of a menu: links, nested groups, theme switches.
$renderItems = function (array $items) use (&$renderItems, $renderLink, $containsActive, $icon) {
    $out = '';
    foreach ($items as $item) {
        if (!empty($item['divider'])) {
            continue;
        }
        $type = $item['type'] ?? null;
        if ($type === 'theme') {
            $out .= '<button type="button" class="mfi-link mfi-theme-switch' . (!empty($item['on']) ? ' on' : '') . '" data-theme="' . h($item['theme']) . '">'
                . '<i class="fas fa-desktop fa-fw" aria-hidden="true"></i><span>' . h($item['label']) . '</span>'
                . (!empty($item['on']) ? '<span class="mfi-count">' . __('On') . '</span>' : '') . '</button>';
        } elseif ($type === 'message') {
            $out .= '<div class="mfi-note">' . h($item['label']) . '</div>';
        } elseif (!empty($item['children'])) {
            $open = $containsActive($item);
            $out .= '<details class="mfi-sub"' . ($open ? ' open' : '') . '><summary class="mfi-link">'
                . $icon($item) . '<span>' . h($item['label']) . '</span><i class="fas fa-chevron-down mfi-chevron" aria-hidden="true"></i></summary>'
                . '<div class="mfi-sub-items">' . $renderItems($item['children']) . '</div></details>';
        } elseif (!empty($item['url'])) {
            $out .= $renderLink($item);
        }
    }
    return $out;
};

// Mockup IA: flat, icon-less sections of curated links. An entry only
// shows when NavbarHelper kept a link to that path, so ACL filtering is
// inherited; Overview (the dashboard) is open to every user. Every link
// not curated here stays reachable under "More".
$sections = [
    '' => [['overview', __('Overview'), '/dashboards']],
    __('Investigate') => [
        ['events', __('Events'), '/events/index'],
        [null, __('Attributes'), '/attributes/index'],
        [null, __('Correlations'), '/correlations/top'],
        [null, __('Proposals'), '/shadow_attributes/index'],
    ],
    __('Knowledge') => [
        [null, __('Galaxies'), '/galaxies/index'],
        [null, __('Taxonomies'), '/taxonomies/index'],
        [null, __('Warninglists'), '/warninglists/index'],
        [null, __('Decaying models'), '/decayingModel/index'],
    ],
    __('Exchange') => [
        ['feeds', __('Feeds'), '/feeds/index'],
        ['servers', __('Sync servers'), '/servers/index'],
        [null, __('Sharing groups'), '/SharingGroups/index'],
        [null, __('Organisations'), '/organisations/index'],
    ],
    __('Automate') => [
        [null, __('Workflows'), '/workflows/index'],
        [null, __('REST client'), '/api/rest'],
    ],
];
$linkByPath = [];
foreach ($links as $link) {
    $linkByPath[strtolower(substr($pathOf($link['url']), strlen(rtrim((string)parse_url($baseurl, PHP_URL_PATH), '/'))))] = $link;
}
$curatedUrls = [];
foreach ($sections as $label => $entries) {
    foreach ($entries as $k => $entry) {
        $link = $linkByPath[strtolower($entry[2])] ?? null;
        if ($link === null && $entry[0] === 'overview') {
            $link = ['url' => $baseurl . '/dashboards'];
        }
        if ($link === null) {
            unset($sections[$label][$k]);
            continue;
        }
        $sections[$label][$k] = ['count' => $entry[0], 'label' => $entry[1], 'url' => $link['url']];
        $curatedUrls[$link['url']] = true;
    }
}
if ($activeUrl === null && strtolower($currentController) === 'dashboards') {
    $activeUrl = $sections[''][0]['url'] ?? null;
}

// The rest of the menu, minus curated links, for "More".
$prune = function (array $items) use (&$prune, $curatedUrls) {
    $out = [];
    foreach ($items as $item) {
        if (!empty($item['url']) && isset($curatedUrls[$item['url']])) {
            continue;
        }
        if (!empty($item['children'])) {
            $item['children'] = $prune($item['children']);
            if (empty($item['children'])) {
                continue;
            }
        }
        $out[] = $item;
    }
    return $out;
};
$more = [];
foreach ($menus['left'] as $root) {
    $children = $prune($root['children'] ?? []);
    if (array_filter($children, function ($c) { return empty($c['divider']); })) {
        $more[] = ['label' => $root['label'] ?? '', 'id' => $root['id'] ?? '', 'children' => $children];
    }
}

// Counts (Events, Feeds, Sync servers), cached in the session for 5 min so
// the rail costs no queries on most page loads.
$counts = CakeSession::read('FiRail.counts');
if (!is_array($counts) || ($counts['at'] ?? 0) < time() - 300) {
    $counts = ['at' => time()];
    try {
        $Event = ClassRegistry::init('Event');
        $conditions = empty($me['Role']['perm_site_admin']) ? $Event->createEventConditions($me) : [];
        $counts['events'] = (int)$Event->find('count', ['conditions' => $conditions, 'recursive' => -1]);
        if (!empty($curatedUrls[$linkByPath['/feeds/index']['url'] ?? ''])) {
            $counts['feeds'] = (int)ClassRegistry::init('Feed')->find('count', ['conditions' => ['Feed.enabled' => 1], 'recursive' => -1]);
        }
        if (!empty($curatedUrls[$linkByPath['/servers/index']['url'] ?? ''])) {
            $counts['servers'] = (int)ClassRegistry::init('Server')->find('count', ['recursive' => -1]);
        }
    } catch (Throwable $e) {
        // Counts are decoration; never let them break navigation.
    }
    CakeSession::write('FiRail.counts', $counts);
}
$countOf = function ($key) use ($counts) {
    return ($key !== null && isset($counts[$key])) ? number_format($counts[$key]) : '';
};
$renderFlat = function (array $item) use ($activeUrl, $countOf) {
    $active = $activeUrl !== null && $item['url'] === $activeUrl;
    return '<a class="mfi-link' . ($active ? ' active' : '') . '" href="' . h($item['url']) . '"'
        . ($active ? ' aria-current="page"' : '') . '><span>' . h($item['label']) . '</span>'
        . '<span class="mfi-count">' . h($countOf($item['count'])) . '</span></a>';
};

$bookmarksMenu = null;
$accountMenu = null;
foreach ($menus['right'] as $root) {
    if (($root['id'] ?? null) === 'bookmarks') {
        $bookmarksMenu = $root;
    } elseif (($root['id'] ?? null) === 'account') {
        $accountMenu = $root;
    }
}

$orgName = $me['Organisation']['name'] ?? '';
$roleName = $me['Role']['name'] ?? '';
?>
<aside class="mfi-rail" aria-label="<?= __('Main navigation') ?>">
    <a class="mfi-brand" href="<?= empty($homepage['path']) ? h($baseurl) . '/' : h($baseurl . $homepage['path']) ?>">
        <span class="mfi-brand-name"><?= h(Configure::read('MISP.title_text') ?: 'MISP') ?></span>
        <?php if ($orgName !== ''): ?><span class="mfi-brand-org"><?= h($orgName) ?></span><?php endif; ?>
    </a>

    <form class="mfi-search" action="<?= h($baseurl) ?>/events/index" method="get" role="search">
        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" class="mfi-search-input" placeholder="<?= __('Search') ?>" aria-label="<?= __('Search events and values') ?>" autocomplete="off">
        <kbd class="mfi-kbd" aria-hidden="true">Ctrl K</kbd>
    </form>

    <nav class="mfi-nav">
        <?php foreach ($sections as $label => $entries): ?>
            <?php if (empty($entries)) continue; ?>
            <div class="mfi-section">
                <?php if ($label !== ''): ?><div class="mfi-section-label"><?= h($label) ?></div><?php endif; ?>
                <?php foreach ($entries as $entry): ?>
                    <?= $renderFlat($entry) ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($more): ?>
            <?php $moreOpen = $activeUrl !== null && !isset($curatedUrls[$activeUrl]); ?>
            <details class="mfi-section mfi-section-collapsible mfi-more"<?= $moreOpen ? ' open' : '' ?>>
                <summary class="mfi-section-label"><?= __('More') ?><i class="fas fa-chevron-down mfi-chevron" aria-hidden="true"></i></summary>
                <?php foreach ($more as $group): ?>
                    <details class="mfi-sub"<?= $containsActive($group) ? ' open' : '' ?><?= $group['id'] === '' ? '' : ' data-tour="nav-' . h($group['id']) . '"' ?>>
                        <summary class="mfi-link"><span><?= h($group['label']) ?></span><i class="fas fa-chevron-down mfi-chevron" aria-hidden="true"></i></summary>
                        <div class="mfi-sub-items"><?= $renderItems($group['children']) ?></div>
                    </details>
                <?php endforeach; ?>
            </details>
        <?php endif; ?>
    </nav>

    <?php if ($accountMenu): ?>
        <details class="mfi-user" data-tour="nav-account">
            <summary class="mfi-user-card">
                <span class="mfi-user-logo"><?= $accountMenu['image'] ?? '' ?></span>
                <span class="mfi-user-text">
                    <span class="mfi-user-name"><?= $accountMenu['label'] ?></span>
                    <span class="mfi-user-role"><?= h(trim($roleName . ($orgName !== '' ? ' · ' . $orgName : ''), ' ·')) ?></span>
                </span>
                <i class="fas fa-chevron-up mfi-chevron" aria-hidden="true"></i>
            </summary>
            <div class="mfi-user-menu">
                <?php if ($bookmarksMenu): ?>
                    <div class="mfi-section-label"><?= __('Bookmarks') ?></div>
                    <?php foreach ($bookmarksMenu['children'] as $b): ?>
                        <?php if (($b['type'] ?? null) === 'setHomepage'): ?>
                            <button type="button" class="mfi-link mfi-set-homepage"><?= $icon($b) ?><span><?= h($b['label']) ?></span></button>
                        <?php elseif (!empty($b['url'])): ?>
                            <?php // Bookmark URLs arrive already escaped by NavbarHelper ?>
                            <a class="mfi-link" href="<?= $b['url'] ?>"><?= $icon($b) ?><span><?= h($b['label']) ?></span></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <hr class="mfi-divider">
                <?php endif; ?>
                <?php foreach ($accountMenu['children'] as $a): ?>
                    <?php $type = $a['type'] ?? null; ?>
                    <?php if ($type === 'darkMode' || !empty($a['divider'])): ?>
                        <?php // fi is dark only ?>
                    <?php elseif ($type === 'tutorial'): ?>
                        <?php if ($bs5): ?>
                            <button type="button" class="mfi-link onboarding-launch"><?= $icon($a) ?><span><?= h($a['label']) ?></span></button>
                        <?php endif; ?>
                    <?php elseif (!empty($a['url'])): ?>
                        <a class="mfi-link" href="<?= h($a['url']) ?>"><?= $icon($a) ?><span><?= h($a['label']) ?></span></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
</aside>

<script>
(function () {
    var baseurl = <?= json_encode($baseurl, JSON_UNESCAPED_SLASHES) ?>;
    var rail = document.currentScript.previousElementSibling;
    var search = rail.querySelector('.mfi-search');
    var searchInput = rail.querySelector('.mfi-search-input');
    var notify = function (message, variant) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, variant);
        } else {
            window.alert(message);
        }
    };
    var post = function (url, body) {
        var headers = {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.csrfToken || ''};
        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
        }
        return fetch(url, {method: 'POST', headers: headers, credentials: 'same-origin', body: body});
    };

    // events/index filters on named args, not the query string.
    search.addEventListener('submit', function (e) {
        e.preventDefault();
        var value = searchInput.value.trim();
        window.location.href = baseurl + '/events/index' + (value ? '/searchall:' + encodeURIComponent(value) : '');
    });
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && !e.altKey && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });

    rail.querySelectorAll('.mfi-theme-switch').forEach(function (button) {
        button.addEventListener('click', function () {
            post(baseurl + '/user_settings/setTheme/' + encodeURIComponent(button.dataset.theme || ''))
                .then(function (r) { if (!r.ok) { throw new Error(); } window.location.reload(); })
                .catch(function () { notify(<?= json_encode(__('Failed to switch theme. Please try again.')) ?>, 'danger'); });
        });
    });
    rail.querySelectorAll('.mfi-set-homepage').forEach(function (button) {
        button.addEventListener('click', function () {
            post(baseurl + '/user_settings/setHomePage', JSON.stringify({path: window.location.pathname}))
                .then(function (r) { if (!r.ok) { throw new Error(); } notify(<?= json_encode(__('Homepage saved!')) ?>); })
                .catch(function () { notify(<?= json_encode(__('Failed to set homepage. Please try again.')) ?>, 'danger'); });
        });
    });

    // Close the account menu on outside click / Escape.
    var user = rail.querySelector('.mfi-user');
    if (user) {
        document.addEventListener('click', function (e) { if (!user.contains(e.target)) { user.open = false; } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { user.open = false; } });
    }

    // Keep the active link in view when the rail scrolls.
    var active = rail.querySelector('.mfi-link.active');
    if (active && active.scrollIntoView) {
        active.scrollIntoView({block: 'nearest'});
    }
})();
</script>
