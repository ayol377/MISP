<?php
/**
 * Side-rail navigation for the Overmind theme (replaces the top navbar).
 * Consumes the same $menus structure as navbar.ctp (NavbarHelper::build()).
 */
$herePath = rtrim(strtok($this->request->here, '?'), '/');
$urlMatches = function ($url) use ($herePath) {
    if (empty($url)) {
        return false;
    }
    $path = rtrim((string)parse_url($url, PHP_URL_PATH), '/');
    return $path !== '' && $path === $herePath;
};
$groupActive = function (array $item) use (&$groupActive, $urlMatches) {
    if (!empty($item['active']) || $urlMatches($item['url'] ?? null)) {
        return true;
    }
    foreach ($item['children'] ?? [] as $child) {
        if (is_array($child) && $groupActive($child)) {
            return true;
        }
    }
    return false;
};
$renderLink = function (array $item, $extraClass = '') use ($urlMatches) {
    $active = $urlMatches($item['url'] ?? null) ? ' active' : '';
    return '<a class="mfi-link' . $active . $extraClass . '" href="' . h($item['url'] ?? '#') . '">'
        . $this->element('navbar_item', ['item' => $item]) . '</a>';
};
?>
<aside class="mfi-sidebar" aria-label="<?= __('Main navigation') ?>">
    <a class="mfi-brand" href="<?= empty($homepage['path']) ? $baseurl . '/' : $baseurl . h($homepage['path']) ?>">
        <?= $this->Html->image('misp-logo-main-cmyk-icon coul.png', ['alt' => __('MISP Logo'), 'class' => 'mfi-brand-logo']) ?>
        <span class="mfi-brand-name"><?= h(Configure::read('MISP.title_text') ?: 'MISP') ?></span>
    </a>

    <form class="mfi-search" action="<?= $baseurl ?>/events/index" method="get" role="search">
        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" name="searchall" class="form-control form-control-sm" placeholder="<?= __('Search events and values') ?>" aria-label="<?= __('Search') ?>">
    </form>

    <nav class="mfi-nav">
        <?php foreach ($menus['left'] as $i => $item): ?>
            <?php
                $gid = 'mfi-g-' . (!empty($item['id']) ? h($item['id']) : $i);
                $open = $groupActive($item);
            ?>
            <?php if (empty($item['children'])): ?>
                <?= $renderLink($item, ' mfi-link-top') ?>
            <?php else: ?>
                <div class="mfi-group"<?= empty($item['id']) ? '' : ' data-tour="nav-' . h($item['id']) . '"' ?>>
                    <button class="mfi-group-toggle<?= $open ? '' : ' collapsed' ?>" type="button"
                            data-bs-toggle="collapse" data-bs-target="#<?= $gid ?>"
                            aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= $gid ?>">
                        <?= $this->element('navbar_item', ['item' => $item]) ?>
                        <i class="fas fa-chevron-down mfi-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="collapse<?= $open ? ' show' : '' ?>" id="<?= $gid ?>">
                        <?php foreach ($item['children'] as $child): ?>
                            <?php if (!empty($child['divider'])): ?>
                                <hr class="mfi-divider">
                            <?php elseif (!empty($child['children'])): ?>
                                <div class="mfi-sub-label"><?= h($child['label'] ?? '') ?></div>
                                <?php foreach ($child['children'] as $sub): ?>
                                    <?php if (!empty($sub['divider'])) continue; ?>
                                    <?php if (!empty($sub['url'])): ?>
                                        <?= $renderLink($sub, ' mfi-link-sub') ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php elseif (!empty($child['url'])): ?>
                                <?= $renderLink($child) ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <?php // Themes, bookmarks and account keep their original dropdown rendering (theme switch, dark mode, homepage, logout). ?>
    <ul class="navbar-nav mfi-foot">
        <?php foreach ($menus['right'] as $item): ?>
            <?= $this->element('navbar_nav', ['item' => $item, 'alignEnd' => false]) ?>
        <?php endforeach; ?>
    </ul>
</aside>

<script>
    // Same handlers as Elements/navbar.ctp so the right-hand menus keep working.
    document.addEventListener('DOMContentLoaded', function() {
        // events/index filters on named args, not the query string.
        document.querySelectorAll('.mfi-search').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var value = form.querySelector('input').value.trim();
                window.location.href = '<?= $baseurl ?>/events/index'
                    + (value ? '/searchall:' + encodeURIComponent(value) : '');
            });
        });
        document.querySelectorAll('.mfi-foot .dropdown').forEach(function (d) { d.classList.add('dropup'); });
        document.querySelectorAll('.setTheme').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                fetch('<?= $baseurl ?>/user_settings/setTheme/' + encodeURIComponent(String(this.dataset.theme || '')), {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': (window.csrfToken || '')},
                    credentials: 'same-origin'
                }).then(function (r) { if (r.ok) { location.reload(); } else { throw new Error('Server Error'); } })
                  .catch(function () { alert('<?= __('Failed to switch theme. Please try again.') ?>'); });
            });
        });
        document.querySelectorAll('.toggle-dark-mode').forEach(function(button) {
            button.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); toggleDarkMode(); });
        });
        document.querySelectorAll('.set-homepage').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                fetch('<?= $baseurl ?>/user_settings/setHomePage', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json', 'X-CSRF-Token': (window.csrfToken || '')},
                    credentials: 'same-origin',
                    body: JSON.stringify({ path: window.location.pathname })
                }).then(function (r) { if (!r.ok) throw new Error(); showToast('<?= __('Homepage saved!') ?>'); })
                  .catch(function () { showToast('<?= __('Failed to set homepage. Please try again.') ?>', 'danger'); });
            });
        });
    });
</script>
