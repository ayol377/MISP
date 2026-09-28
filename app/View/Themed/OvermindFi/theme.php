<?php
/*
 * Registry entry only. OvermindFi has no views of its own: AppController
 * renders it through the Overmind theme (so every `$this->theme ===
 * 'Overmind'` branch still applies) and sets $themeVariant = 'fi', which
 * the Overmind layouts use to swap in the side rail and misp-fi-theme.css.
 */
return [
    'label' => __('Overmind Dark UI'),
    'description' => '[DEV] Overmind UI with a dark side-rail layout.',
    'hide_from_users' => false
];
