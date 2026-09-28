<?php
/*
 * OvermindFi: attribute search form. Same fields as the default theme's
 * Attributes/search.ctp, POSTed to attributes/index as data[Attribute][...],
 * which is what AttributesController::index() reads. The results come back
 * through Themed/OvermindFi/Attributes/index.ctp.
 */
$this->set('headerTitle', __('Search attributes'));
$this->set('headerDescription', __('Find attributes by value, tag, event, organisation, type or category.'));

$named = $this->request->params['named'] ?? [];
$prefill = function ($key) use ($named) {
    return isset($named[$key]) && is_string($named[$key]) ? $named[$key] : '';
};

$textarea = function ($name, $label, $help, $placeholder = '', $mono = false) use ($prefill) {
    $id = 'fiAs' . Inflector::camelize($name);
    return sprintf(
        '<label class="form-label" for="%s">%s</label>'
        . '<textarea id="%s" name="data[Attribute][%s]" rows="2" class="form-control%s" placeholder="%s">%s</textarea>'
        . '<div class="fi-as-help">%s</div>',
        $id, h($label), $id, h($name), $mono ? ' fi-mono' : '', h($placeholder),
        h($prefill($name)), h($help)
    );
};

$select = function ($name, $label, array $options) use ($prefill) {
    $id = 'fiAs' . Inflector::camelize($name);
    $current = $prefill($name) !== '' ? $prefill($name) : 'ALL';
    $html = sprintf(
        '<label class="form-label" for="%s">%s</label><select id="%s" name="data[Attribute][%s]" class="form-select">',
        $id, h($label), $id, h($name)
    );
    foreach ($options as $value => $text) {
        $html .= sprintf(
            '<option value="%s"%s>%s</option>',
            h($value), (string)$value === $current ? ' selected' : '', h($text)
        );
    }
    return $html . '</select><div class="fi-as-help" id="' . $id . 'Desc"></div>';
};

$checkbox = function ($name, $label) use ($prefill) {
    $id = 'fiAs' . Inflector::camelize($name);
    return sprintf(
        '<div class="form-check">'
        . '<input type="hidden" name="data[Attribute][%1$s]" value="0">'
        . '<input class="form-check-input" type="checkbox" id="%2$s" name="data[Attribute][%1$s]" value="1"%3$s>'
        . '<label class="form-check-label" for="%2$s">%4$s</label></div>',
        h($name), $id, !empty($prefill($name)) ? ' checked' : '', h($label)
    );
};

$seen = function ($name, $label) use ($prefill) {
    $id = 'fiAs' . Inflector::camelize($name);
    return sprintf(
        '<label class="form-label" for="%s">%s</label>'
        . '<input type="datetime-local" step="1" id="%s" name="data[Attribute][%s]" class="form-control fi-mono" value="%s">',
        $id, h($label), $id, h($name), h(substr($prefill($name), 0, 19))
    );
};
?>
<div class="container-fluid pb-4 fi-as">
    <?= $this->Form->create('Attribute', ['url' => $baseurl . '/attributes/index', 'id' => 'fiAttributeSearch', 'class' => 'fi-panel']) ?>
        <p class="fi-as-intro">
            <?= __('Enter one term per line. Prefix a term with %s to exclude it, and wrap it in %s for a substring match.', '<code>!</code>', '<code>%</code>') ?>
        </p>
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <?= $textarea('value', __('Value'), __('Contained expressions, one per line'), "eu-docs.com\n185.225.73.18", true) ?>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <?= $textarea('tags', __('Tags'), __('On the attribute or on its event'), 'tlp:amber') ?>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <?= $textarea('uuid', __('Event ID or UUID'), __('Event IDs, event UUIDs or attribute UUIDs'), '', true) ?>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <?= $textarea('org', __('Organisation'), __('Creator organisation names or IDs')) ?>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <?= $textarea('object_relation', __('Object relation'), __('Relations inside an object, e.g. ip-src')) ?>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <?= $select('type', __('Type'), $types) ?>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <?= $select('category', __('Category'), $categories) ?>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <?= $seen('first_seen', __('First seen from')) ?>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <?= $seen('last_seen', __('Last seen until')) ?>
            </div>
            <div class="col-12 col-lg-4 d-flex align-items-end">
                <div class="fi-as-help mb-2"><?= __('Attributes without a first or last seen may not appear when these are set.') ?></div>
            </div>
            <div class="col-12 fi-as-actions">
                <div class="d-flex flex-wrap gap-4">
                    <?= $checkbox('to_ids', __('Only IDS-flagged')) ?>
                    <?= $checkbox('enforceWarninglist', __('Exclude warninglist hits')) ?>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h($baseurl . '/attributes/search') ?>"><?= __('Reset') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass me-1"></i><?= __('Search') ?></button>
                </div>
            </div>
        </div>
    <?= $this->Form->end() ?>
</div>
<script>
(function () {
    // category => types, both lists carrying ALL, as the legacy form used.
    var mapping = <?= json_encode(array_map(function (array $def) {
        return $def['types'];
    }, $categoryDefinitions)) ?>;
    var desc = <?= json_encode(['type' => $fieldDesc['type'], 'category' => $fieldDesc['category']]) ?>;
    var typeSel = document.getElementById('fiAsType');
    var catSel = document.getElementById('fiAsCategory');
    var allTypes = Array.from(typeSel.options, function (o) { return o.value; });
    var allCats = Array.from(catSel.options, function (o) { return o.value; });

    // Rebuild a select from its full list, keeping what the filter allows.
    function refill(sel, full, keep) {
        var current = sel.value;
        sel.replaceChildren();
        full.filter(keep).forEach(function (v) {
            sel.add(new Option(v, v, false, v === current));
        });
        if (sel.value !== current) sel.value = 'ALL';
    }
    function describe(sel, key) {
        document.getElementById(sel.id + 'Desc').textContent = desc[key][sel.value] || '';
    }
    typeSel.addEventListener('change', function () {
        var t = typeSel.value;
        refill(catSel, allCats, function (c) { return (mapping[c] || []).indexOf(t) !== -1; });
        describe(typeSel, 'type');
        describe(catSel, 'category');
    });
    catSel.addEventListener('change', function () {
        var types = mapping[catSel.value] || allTypes;
        refill(typeSel, allTypes, function (t) { return types.indexOf(t) !== -1; });
        describe(typeSel, 'type');
        describe(catSel, 'category');
    });
    describe(typeSel, 'type');
    describe(catSel, 'category');
}());
</script>
