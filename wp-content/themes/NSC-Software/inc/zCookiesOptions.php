<?php

/**
 * Translatable options for the site cookie bar (Theme Options → Global).
 */

use NscSoftware\Utils\Options;

add_action('NscSoftware/afterRegisterComponents', static function (): void {
    Options::addTranslatable('NSCCookiesContent', [
        [
            'label' => __('NSC Cookies Content', 'NscSoftware'),
            'name' => 'cookiesTab',
            'type' => 'tab',
            'placement' => 'top',
            'endpoint' => 0,
        ],
        [
            'label' => __('Dialog aria label', 'NscSoftware'),
            'name' => 'ariaLabel',
            'type' => 'text',
            'default_value' => __('Cookie preferences', 'NscSoftware'),
        ],
        [
            'label' => __('Heading', 'NscSoftware'),
            'name' => 'heading',
            'type' => 'text',
            'default_value' => __('We use cookies', 'NscSoftware'),
        ],
        [
            'label' => __('Content', 'NscSoftware'),
            'name' => 'content',
            'type' => 'textarea',
            'default_value' => __('This site uses cookies to improve your browsing experience, analyze traffic, and remember your preferences. You can accept all cookies, reject non-essential cookies, or review details in our Cookies Policy.', 'NscSoftware'),
            'rows' => 4,
        ],
        [
            'label' => __('Reject button label', 'NscSoftware'),
            'name' => 'rejectLabel',
            'type' => 'text',
            'default_value' => __('Reject non-essential', 'NscSoftware'),
        ],
        [
            'label' => __('Settings button label', 'NscSoftware'),
            'name' => 'settingsLabel',
            'type' => 'text',
            'default_value' => __('Cookie settings', 'NscSoftware'),
        ],
        [
            'label' => __('Settings URL', 'NscSoftware'),
            'name' => 'settingsUrl',
            'type' => 'url',
        ],
        [
            'label' => __('Open settings in new tab', 'NscSoftware'),
            'name' => 'settingsOpenInNewTab',
            'type' => 'true_false',
            'default_value' => 0,
            'ui' => 1,
        ],
        [
            'label' => __('Accept button label', 'NscSoftware'),
            'name' => 'acceptLabel',
            'type' => 'text',
            'default_value' => __('Accept all', 'NscSoftware'),
        ],
    ], 'Global');
}, 20);
