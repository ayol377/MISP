<?php
App::uses('AppView', 'View');

/**
 * View class for the OvermindFi theme.
 *
 * OvermindFi runs with $this->theme === 'Overmind' so every Overmind
 * controller branch applies. This class adds Themed/OvermindFi/ in front
 * of Themed/Overmind/ in the lookup order, so a template placed under
 * OvermindFi replaces its Overmind counterpart and anything not
 * overridden falls back to Overmind, then to the base views.
 */
class FiThemeView extends AppView
{
    protected function _paths($plugin = null, $cached = true)
    {
        $needle = DS . 'Themed' . DS . 'Overmind' . DS;
        $paths = [];
        foreach (parent::_paths($plugin, $cached) as $path) {
            $pos = strpos($path, $needle);
            if ($pos !== false) {
                $paths[] = substr_replace($path, DS . 'Themed' . DS . 'OvermindFi' . DS, $pos, strlen($needle));
            }
            $paths[] = $path;
        }
        return array_values(array_unique($paths));
    }
}
