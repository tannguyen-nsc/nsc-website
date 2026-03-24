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

/**
 * Feature-gated layouts use a dedicated field name so acf/load_value/name=… hooks can target them
 * (ACFComposer does not allow a custom field `key` in config).
 *
 * @return array<string, mixed>
 */
function getHiddenBlogHome(): array
{
    $f = getHidden();
    $f['name'] = 'hiddenBlogHome';

    return $f;
}

/**
 * @return array<string, mixed>
 */
function getHiddenJobsArchive(): array
{
    $f = getHidden();
    $f['name'] = 'hiddenJobsArchive';

    return $f;
}

/**
 * @return array<string, mixed>
 */
function getHiddenCaseStudiesArchive(): array
{
    $f = getHidden();
    $f['name'] = 'hiddenCaseStudiesArchive';

    return $f;
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

/**
 * Options tab: max rows from the Content repeater to output on the front (first N).
 *
 * @return array<string, mixed>
 */
function getRepeaterItemLimitField(int $default = 4, int $min = 1, int $max = 30)
{
    return [
        'label' => __('Max items (content repeater)', 'NscSoftware'),
        'name' => 'repeaterItemLimit',
        'type' => 'number',
        'default_value' => $default,
        'min' => $min,
        'max' => $max,
        'instructions' => __('Only the first this many repeater rows are shown. You can add more rows in the editor (up to the row limit) if needed.', 'NscSoftware'),
    ];
}

/**
 * @return array<string, mixed>
 */
function getBlogHomePostsLimitField()
{
    return [
        'label' => __('Max posts (Featured & Latest)', 'NscSoftware'),
        'name' => 'homeBlogPostsLimit',
        'type' => 'number',
        'default_value' => 4,
        'min' => 1,
        'max' => 24,
        'instructions' => __('Applies to both Featured Insights and Latest Updates lists.', 'NscSoftware'),
    ];
}

/**
 * @return array<string, mixed>
 */
function getArchiveFeaturedPostsLimitField()
{
    return [
        'label' => __('Max featured posts', 'NscSoftware'),
        'name' => 'featuredPostsLimit',
        'type' => 'number',
        'default_value' => 4,
        'min' => 1,
        'max' => 24,
        'instructions' => __('Number of posts in the Featured area (1 large + rest in sidebar).', 'NscSoftware'),
    ];
}

/**
 * @return array<string, mixed>
 */
function getArchiveBlogListPerPageField()
{
    return [
        'label' => __('Blog list: posts per page', 'NscSoftware'),
        'name' => 'blogListPerPage',
        'type' => 'number',
        'default_value' => 6,
        'min' => 1,
        'max' => 48,
        'instructions' => __('Used by the searchable blog list (Vue pagination).', 'NscSoftware'),
    ];
}

/**
 * @return array<string, mixed>
 */
function getCaseStudiesListPerPageField()
{
    return [
        'label' => __('Case studies: items per page', 'NscSoftware'),
        'name' => 'caseStudiesPerPage',
        'type' => 'number',
        'default_value' => 6,
        'min' => 1,
        'max' => 48,
        'instructions' => __('Vue grid pagination (matches static case-studies.html).', 'NscSoftware'),
    ];
}
