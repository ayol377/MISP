<?php
/*
 * One compact filter of the events index (mockup 1c): a faint label and a
 * dropdown pill. Every option is a plain link to events/index with the
 * filter set, so it works without JS and keeps the other filters.
 *
 * $label   : chip label ("TLP")
 * $value   : what the pill shows ("amber", "Any")
 * $set     : whether a filter is on (the pill brightens)
 * $options : [['label' => ..., 'url' => ..., 'active' => bool], ...]
 * $range   : optional ['from' => 'Y-m-d', 'until' => 'Y-m-d'], adds the
 *            custom date range form (js/fi/events-index.js submits it)
 */
$range = $range ?? null;
?>
<div class="fi-ev-chip dropdown">
    <span class="fi-ev-chip-label"><?= h($label) ?></span>
    <button type="button" class="fi-ev-chip-value<?= !empty($set) ? ' is-set' : '' ?>"
            data-bs-toggle="dropdown" aria-expanded="false"
            <?= $range !== null ? 'data-bs-auto-close="outside"' : '' ?>
            aria-label="<?= h($label . ': ' . $value) ?>">
        <?= h($value) ?>
    </button>
    <div class="dropdown-menu fi-ev-menu">
        <?php foreach ($options as $option): ?>
            <a class="dropdown-item<?= !empty($option['active']) ? ' active' : '' ?>"
               href="<?= h($option['url']) ?>"
               <?= !empty($option['active']) ? 'aria-current="true"' : '' ?>><?= h($option['label']) ?></a>
        <?php endforeach; ?>
        <?php if ($range !== null): ?>
            <div class="dropdown-divider"></div>
            <form class="fi-ev-range-form" data-fi-ev-period>
                <label><?= __('From') ?>
                    <input type="date" name="datefrom" class="form-control form-control-sm" value="<?= h($range['from']) ?>">
                </label>
                <label><?= __('Until') ?>
                    <input type="date" name="dateuntil" class="form-control form-control-sm" value="<?= h($range['until']) ?>">
                </label>
                <button type="submit" class="btn btn-primary btn-sm"><?= __('Apply') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
