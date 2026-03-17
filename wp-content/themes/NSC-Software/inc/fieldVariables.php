<?php

/**
 * Defines field variables to be used across multiple components.
 */

namespace NscSoftware\FieldVariables;

function getTheme($default = '')
{
    return [
        'label' => __('Theme', 'NscSoftware'),
        'name' => 'theme',
        'type' => 'select',
        'allow_null' => 0,
        'multiple' => 0,
        'ui' => 0,
        'ajax' => 0,
        'choices' => [
            '' => __('(none)', 'NscSoftware'),
            'light' => __('Light', 'NscSoftware'),
            'dark' => __('Dark', 'NscSoftware'),
        ],
        'default_value' => $default,
    ];
}


function getSize($default = 'medium')
{
    return [
        'label' => __('Size', 'NscSoftware'),
        'name' => 'size',
        'type' => 'radio',
        'other_choice' => 0,
        'save_other_choice' => 0,
        'layout' => 'horizontal',
        'choices' => [
            'medium' => __('Medium', 'NscSoftware'),
            'wide' => __('Wide', 'NscSoftware'),
            'full' => __('Full', 'NscSoftware'),
        ],
        'default_value' => $default
    ];
}

function getAlignment($args = [])
{
    $options = wp_parse_args($args, [
        'label' => __('Align', 'NscSoftware'),
        'name' => 'align',
        'default' => 'center',
    ]);

    return [
        'label' => $options['label'],
        'name' => $options['name'],
        'type' => 'radio',
        'other_choice' => 0,
        'save_other_choice' => 0,
        'layout' => 'horizontal',
        'choices' => [
            'left' => __('Left', 'NscSoftware'),
            'center' => __('Center', 'NscSoftware'),
        ],
        'default_value' => $options['default']
    ];
}

function getTextAlignment($args = [])
{
    $options = wp_parse_args($args, [
        'label' => __('Align text', 'NscSoftware'),
        'name' => 'textAlign',
        'default' => 'left',
    ]);

    return [
        'label' => $options['label'],
        'name' => $options['name'],
        'type' => 'button_group',
        'choices' => [
            'left' => sprintf('<i class="dashicons dashicons-editor-alignleft" title="%1$s"></i>', __('Align text left', 'NscSoftware')),
            'center' => sprintf('<i class="dashicons dashicons-editor-aligncenter" title="%1$s"></i>', __('Align text center', 'NscSoftware'))
        ],
        'default_value' => $options['default']
    ];
}
