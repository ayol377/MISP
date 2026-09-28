<?php
/*
 * OvermindFi: attribute/object type chip (replaces Overmind's bordered
 * <p>). Same inputs as Themed/Overmind/Elements/genericElementsBS5/Badges/type.ctp.
 *
 * Expected:
 * $type (string)
 */
$type = isset($type) ? $type : null;
?>
<span class="fi-type-chip"><?= h($type) ?></span>
