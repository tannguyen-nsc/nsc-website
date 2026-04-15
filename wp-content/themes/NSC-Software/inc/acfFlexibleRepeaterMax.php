<?php

/**
 * Sync ACF repeater "max rows" with Options tab repeaterItemLimit (flexible layout pageComponents).
 */

namespace NscSoftware\AcfFlexibleRepeaterMax;

/**
 * Layout name => [ repeater sub field name, default limit ].
 *
 * @var array<string, array{0: string, 1: int}>
 */
const LAYOUT_REPEATER_DEFAULTS = [
    'nscBlockStats' => ['stats', 4],
    'nscBlockOurServices' => ['services', 8],
    'nscBlockWhyUs' => ['items', 7],
    'nscBlockHowWeWork' => ['items', 4],
    'nscBlockHowWeWorkPageEngagement' => ['engagementModels', 4],
    'nscBlockOurLeaders' => ['leaders', 4],
    'nscBlockGlobalPresence' => ['locations', 5],
    'nscBlockAiImpact' => ['items', 4],
    'nscBlockAiSecurity' => ['items', 3],
];

const FLEXIBLE_FIELD_NAME = 'pageComponents';
const ABSOLUTE_MAX = 30;

/**
 * @param array<string, mixed> $field
 * @return array<string, mixed>|false
 */
function parse_flexible_row_from_prefix($field)
{
    $prefix = isset($field['prefix']) ? (string) $field['prefix'] : '';
    if ($prefix === '' || !preg_match('/^([^[]+)\[(row-\d+|acfcloneindex)\]$/', $prefix, $pm)) {
        return false;
    }
    if ($pm[1] !== FLEXIBLE_FIELD_NAME) {
        return false;
    }

    return [
        'row_token' => $pm[2],
        'row_i' => $pm[2] === 'acfcloneindex' ? null : (int) substr($pm[2], 4),
    ];
}

/**
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function apply_repeater_max_from_options($field)
{
    if (($field['type'] ?? '') !== 'repeater') {
        return $field;
    }

    $parsed = parse_flexible_row_from_prefix($field);
    if ($parsed === false) {
        return $field;
    }

    $row_i = $parsed['row_i'];
    $post_id = function_exists('acf_get_valid_post_id') ? acf_get_valid_post_id() : get_the_ID();
    if (!$post_id) {
        return $field;
    }

    // New clone row / unsaved row: keep field definition max (e.g. 30).
    if ($row_i === null) {
        return $field;
    }

    if (!function_exists('get_field')) {
        return $field;
    }

    $rows = get_field(FLEXIBLE_FIELD_NAME, $post_id);
    if (!is_array($rows) || !isset($rows[$row_i]) || !is_array($rows[$row_i])) {
        return $field;
    }

    $row = $rows[$row_i];
    $layout = isset($row['acf_fc_layout']) ? (string) $row['acf_fc_layout'] : '';
    if ($layout === '' || !isset(LAYOUT_REPEATER_DEFAULTS[$layout])) {
        return $field;
    }

    [$repeaterName, $default] = LAYOUT_REPEATER_DEFAULTS[$layout];
    if (($field['name'] ?? '') !== $repeaterName) {
        return $field;
    }

    $opts = isset($row['options']) && is_array($row['options']) ? $row['options'] : [];
    $raw = $opts['repeaterItemLimit'] ?? null;
    if ($raw === null || $raw === '') {
        $max = $default;
    } else {
        $max = max(1, min(ABSOLUTE_MAX, (int) $raw));
    }

    $field['max'] = $max;

    return $field;
}

add_filter('acf/prepare_field/type=repeater', __NAMESPACE__ . '\\apply_repeater_max_from_options', 20);

add_action('acf/input/admin_enqueue_scripts', function () {
    $path = get_template_directory() . '/assets/scripts/acf-repeater-max-sync.js';
    if (!is_readable($path)) {
        return;
    }
    wp_enqueue_script(
        'nsc-acf-repeater-max-sync',
        get_template_directory_uri() . '/assets/scripts/acf-repeater-max-sync.js',
        ['acf-input'],
        wp_get_theme()->get('Version'),
        true
    );

    $css = <<<'CSS'
.nsc-acf-repeater-at-max .acf-button.acf-repeater-add-row,
.nsc-acf-repeater-at-max a[data-event="add-row"] {
	opacity: 0.4 !important;
	cursor: not-allowed !important;
	pointer-events: none;
	filter: grayscale(0.35);
}
.nsc-acf-repeater-at-max .acf-table ~ .acf-actions .acf-icon.-plus[data-event="add-row"] {
	opacity: 0.35 !important;
	cursor: not-allowed !important;
	pointer-events: none;
}
CSS;
    wp_register_style('nsc-acf-repeater-max', false, [], wp_get_theme()->get('Version'));
    wp_enqueue_style('nsc-acf-repeater-max');
    wp_add_inline_style('nsc-acf-repeater-max', $css);
});
