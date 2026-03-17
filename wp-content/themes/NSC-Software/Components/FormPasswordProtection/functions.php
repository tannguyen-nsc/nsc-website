<?php

namespace NscSoftware\Components\FormPasswordProtection;

use NscSoftware\Utils\Options;
use Timber\Timber;

add_filter('the_password_form', function () {
    $context = Timber::context();
    $context['form'] = [
        'url' => site_url('/wp-login.php?action=postpass', 'login_post')
    ];
    $translatableOptions = Options::getTranslatable('FormPasswordProtection');
    if (!empty($translatableOptions)) {
        $context = array_replace_recursive($context, $translatableOptions);
    }

    return Timber::compile('index.twig', $context);
});

Options::addTranslatable('FormPasswordProtection', [
    [
        'label' => __('Content', 'NscSoftware'),
        'name' => 'general',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Text', 'NscSoftware'),
        'name' => 'contentHtml',
        'type' => 'wysiwyg',
        'delay' => 0,
        'media_upload' => 0,
        'required' => 1,
        'default_value' => sprintf(
            '<h1 class="h3">%1$s</h1><p>%2$s %3$s</p>',
            __('Enter Password', 'NscSoftware'),
            __('This content is password protected.', 'NscSoftware'),
            __('To view it please enter your password below:', 'NscSoftware')
        ),
    ],
    [
        'label' => __('Labels', 'NscSoftware'),
        'name' => 'labelsTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0
    ],
    [
        'label' => '',
        'name' => 'labels',
        'type' => 'group',
        'sub_fields' => [
            [
                'label' => __('Input – Aria Label', 'NscSoftware'),
                'name' => 'inputAriaLabel',
                'type' => 'text',
                'default_value' => __('Password', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Input – Placeholder', 'NscSoftware'),
                'name' => 'inputPlaceholder',
                'type' => 'text',
                'default_value' => __('Enter password', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Input – Submit', 'NscSoftware'),
                'name' => 'buttonSubmit',
                'type' => 'text',
                'default_value' => __('Enter', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
        ],
    ],
]);
