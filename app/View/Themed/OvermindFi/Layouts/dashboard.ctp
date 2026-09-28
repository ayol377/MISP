<?php
/**
 * OvermindFi dashboard layout: Overmind's Layouts/dashboard.ctp with
 * the fi side rail and forced dark (midnight) tokens. Keep the rest in
 * step with Themed/Overmind/Layouts/dashboard.ctp.
 */
?>
<!DOCTYPE html>
<html data-bs-theme="dark" data-theme="midnight" lang="<?= Configure::read('Config.language') === 'eng' ? 'en' : Configure::read('Config.language') ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="<?= $baseurl ?>/img/favicon.png">
    <title><?= h($title_for_layout) . ' - ' . h(Configure::read('MISP.title_text') ?: 'MISP') ?></title>
    <?php
        $css = [
            ['bootstrap5-custom.min', ['preload' => true]],
            ['tom-select.bootstrap5.min', ['preload' => true]],
            ['mainOvermind', ['preload' => true]],
            ['fontawesome7.min', ['preload' => true]],
            ['dashboard/dashboard.default', ['preload' => true]],
            ['dashboard/dashboard.midnight'],
            // misp-iconify icon set (generated CSS copied from the
            // app/files/misp-iconify submodule; self-contained masked SVGs,
            // currentColor). Lives in the main webroot — Cake's theme-aware
            // resolver falls back to it like it does for dashboard.default
            // above. Used by StatGrid's misp-icon glyphs (AD-21).
            ['misp-iconify'],
            // Loaded last so its rules win where they overlap with
            // dashboard.default.css. Cake's Helper::webroot() is
            // theme-aware and resolves this against
            // app/View/Themed/Overmind/webroot/css/dashboard/overmind.css
            // → /theme/Overmind/css/dashboard/overmind.css.
            ['dashboard/overmind', ['preload' => true]],
            ['print', ['media' => 'print']],
            ['misp-fi-theme', ['preload' => true]],
            ['fi/rail', ['preload' => true]],
            ['fi/screens', ['preload' => true]],
            ['fi/dashboard', ['preload' => true]],
        ];
        if (Configure::read('MISP.custom_css')) {
            $css[] = preg_replace('/\.css$/i', '', Configure::read('MISP.custom_css'));
        }
        $js = [
            ['tom-select.complete.min', ['preload' => true]],
        ];
        if (!empty($additionalCss)) {
            $css = array_merge($css, $additionalCss);
        }
        if (!empty($additionalJs)) {
            $js = array_merge($js, $additionalJs);
        }
        echo $this->element('genericElements/assetLoader', [
            'css' => $css,
            'js' => $js,
        ]);
    ?>
</head>
<body class="misp-dashboard-page mfi-shell"
      data-controller="<?= h($this->params['controller']) ?>"
      data-action="<?= h($this->params['action']) ?>">
    <div class="mfi-app">
            <?php
                // BS5 Overmind navbar — same build pattern as
                // Themed/Overmind/Layouts/default.ctp's BS5 branch.
                $context = [
                    'me' => $me,
                    'baseurl' => $baseurl,
                    'isAdmin' => $isAdmin,
                    'isSiteAdmin' => $isSiteAdmin,
                    'isAclSync' => $isAclSync,
                    'isAclRegexp' => $isAclRegexp,
                    'isAclAudit' => $isAclAudit,
                    'hostOrgUser' => $hostOrgUser,
                    'bookmarks' => $bookmarks,
                    'themes' => $themes,
                    'theme' => $theme,
                    'themesEnabled' => $themesEnabled,
                ];
                $menus = $this->Navbar->build($context);
                echo $this->element('fi_rail', [
                    'menus' => $menus,
                    'baseurl' => $baseurl,
                    'me' => $me ?? null,
                    'bs5' => true,
                ]);
            ?>
    <div class="mfi-col"><div class="main-wrapper">
        <main role="main" class="content" style="padding-top:0;">
            <div id="flashOverlay">
                <div id="flashContainer">
                    <?= $this->Flash->render() ?>
                </div>
            </div>
            <div>
                <?php if (!empty($fiBoard)): // DashboardsController::index, ?board=1 ?>
                    <a class="fi-ov-back" href="<?= h($baseurl . '/dashboards') ?>">&larr; <?= __('Overview') ?></a>
                <?php endif; ?>
                <?= $this->fetch('content') ?>
            </div>
        </main>
    </div>

    <?= $this->element('footerBS5') ?>
    </div></div><?php // .mfi-col, .mfi-app ?>
    <?= $this->element('sql_dump') ?>

    <!-- Modals, toasts, popovers and the loading overlay. Shared with
         Overmind's default.ctp; BS5 set only, the dashboard never loads
         misp.js. -->
    <?= $this->element('chrome_containers') ?>

    <?php
    echo $this->element('genericElements/assetLoader', [
        'js' => [
            'bootstrap.bundle.min',
            'mispOvermind',
        ],
    ]);
    ?>

    <script>
        // Flash auto-dismiss now comes from mispOvermind.js ("Page chrome").
        var baseurl = <?= json_encode($baseurl, JSON_UNESCAPED_SLASHES) ?>;
        // CSRF token for hand-built same-origin AJAX, which has no rendered form
        // to carry _Token fields. Sent as the X-CSRF-Token header - see
        // BetterSecurityComponent::_validateCsrf().
        var csrfToken = <?= json_encode(isset($this->request->params['_Token']['key']) ? $this->request->params['_Token']['key'] : '') ?>;
        var here = <?php
                if (substr($this->params['action'], 0, 6) === 'admin_') {
                    $here = $baseurl . '/admin/' . $this->params['controller']
                        . '/' . substr($this->params['action'], 6);
                } else {
                    $here = $baseurl . '/' . $this->params['controller']
                        . '/' . $this->params['action'];
                }
                echo json_encode($here, JSON_UNESCAPED_SLASHES);
            ?>;
    </script>
</body>
</html>
