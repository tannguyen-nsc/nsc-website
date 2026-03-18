<?php

namespace NscSoftware\Components\NSCBlockTestimonials;

use NscSoftware\FieldVariables;

/**
 * Ensure image fields are arrays with url (ACF can return ID or Timber\Image when from meta); so Twig can use t.image.url.
 */
add_filter('NscSoftware/addComponentData?name=NSCBlockTestimonials', function ($data) {
    if (!empty($data['logos']) && is_array($data['logos'])) {
        foreach ($data['logos'] as $i => $row) {
            $id = attachmentIdFromMixed($row['image'] ?? null);
            if ($id > 0 && !is_array($row['image'])) {
                $data['logos'][$i]['image'] = imageIdToArray($id);
            }
        }
    }
    if (!empty($data['testimonials']) && is_array($data['testimonials'])) {
        foreach ($data['testimonials'] as $i => $t) {
            $id = attachmentIdFromMixed($t['image'] ?? null);
            if ($id > 0 && !is_array($t['image'])) {
                $data['testimonials'][$i]['image'] = imageIdToArray($id);
            }
        }
    }
    return $data;
});

/**
 * Get attachment ID from ACF image value (int, Timber\Image, or array with id).
 * Never casts an object to int; extracts scalar id from Timber\Image etc.
 *
 * @param mixed $value
 * @return int
 */
function attachmentIdFromMixed($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (is_object($value)) {
        $raw = null;
        if (isset($value->ID) && (is_int($value->ID) || is_numeric($value->ID))) {
            $raw = $value->ID;
        } elseif (isset($value->id)) {
            $raw = $value->id;
        }
        if ($raw !== null) {
            if (is_object($raw)) {
                $raw = isset($raw->ID) ? $raw->ID : (isset($raw->id) ? $raw->id : 0);
            }
            return is_numeric($raw) ? (int) $raw : 0;
        }
        return 0;
    }
    if (is_array($value) && isset($value['id'])) {
        $raw = $value['id'];
        return is_numeric($raw) ? (int) $raw : 0;
    }
    if (is_numeric($value)) {
        return (int) $value;
    }
    return 0;
}

function imageIdToArray(int $id): array
{
    $url = wp_get_attachment_image_url($id, 'full');
    $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
    return [
        'id'   => $id,
        'url'  => $url ?: '',
        'alt'  => is_string($alt) ? $alt : '',
    ];
}

function getACFLayout()
{
    return [
        'name' => 'nscBlockTestimonials',
        'label' => __('NSC Block: Testimonials', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Background image', 'NscSoftware'),
                'name' => 'backgroundImage',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Heading icon', 'NscSoftware'),
                'name' => 'headingIcon',
                'type' => 'image',
                'preview_size' => 'thumbnail',
                'return_format' => 'array',
            ],
            [
                'label' => __('Section title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'Beyond A Partner',
            ],
            [
                'label' => __('Intro description', 'NscSoftware'),
                'name' => 'introDescription',
                'type' => 'textarea',
            ],
            [
                'label' => __('Intro button', 'NscSoftware'),
                'name' => 'introButton',
                'type' => 'group',
                'sub_fields' => [
                    ['label' => __('Label', 'NscSoftware'), 'name' => 'label', 'type' => 'text', 'default_value' => 'Explore Our Case Studies'],
                    ['label' => __('URL', 'NscSoftware'), 'name' => 'url', 'type' => 'url', 'default_value' => home_url('/')],
                    ['label' => __('Open in new tab', 'NscSoftware'), 'name' => 'openInNewTab', 'type' => 'true_false', 'default_value' => 0],
                ],
            ],
            [
                'label' => __('Client logos', 'NscSoftware'),
                'name' => 'logos',
                'type' => 'repeater',
                'min' => 0,
                'layout' => 'table',
                'sub_fields' => [
                    [
                        'label' => __('Logo image', 'NscSoftware'),
                        'name' => 'image',
                        'type' => 'image',
                        'return_format' => 'array',
                    ],
                ],
            ],
            [
                'label' => __('Testimonials', 'NscSoftware'),
                'name' => 'testimonials',
                'type' => 'repeater',
                'min' => 0,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Quote / content', 'NscSoftware'),
                        'name' => 'content',
                        'type' => 'textarea',
                        'required' => 1,
                    ],
                    [
                        'label' => __('Extended content (for "Read more" popup)', 'NscSoftware'),
                        'name' => 'readMoreContent',
                        'type' => 'wysiwyg',
                        'toolbar' => 'basic',
                    ],
                    [
                        'label' => __('Author image', 'NscSoftware'),
                        'name' => 'image',
                        'type' => 'image',
                        'return_format' => 'array',
                    ],
                    [
                        'label' => __('Author name', 'NscSoftware'),
                        'name' => 'authorName',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Author role / title', 'NscSoftware'),
                        'name' => 'authorRole',
                        'type' => 'text',
                    ],
                ],
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => '',
                'name' => 'options',
                'type' => 'group',
                'layout' => 'row',
                'sub_fields' => [
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
