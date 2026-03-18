<?php

/**
 * Defines field variables to be used across multiple components.
 */

namespace NscSoftware\FieldVariables;

/**
 * Hidden option: when enabled, the component is not displayed on the front page.
 *
 * @return array<string, mixed>
 */
function getHidden()
{
    return [
        'label' => __('Hide on front', 'NscSoftware'),
        'name' => 'hidden',
        'type' => 'true_false',
        'message' => __('Hide this component on the front page', 'NscSoftware'),
        'default_value' => 0,
        'ui' => 1,
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
