<?php

/**
 * Slice flexible-component repeater data using Options tab "repeaterItemLimit".
 */

namespace NscSoftware;

/**
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function apply_repeater_item_limit(array $data, string $repeaterKey, int $default, int $min = 1, int $max = 30): array
{
    $opts = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
    $raw = $opts['repeaterItemLimit'] ?? null;
    $limit = $default;
    if ($raw !== null && $raw !== '') {
        $limit = (int) $raw;
        $limit = max($min, min($max, $limit));
    }
    if (!empty($data[$repeaterKey]) && is_array($data[$repeaterKey]) && $limit > 0) {
        $data[$repeaterKey] = array_slice($data[$repeaterKey], 0, $limit);
    }

    return $data;
}

$slice = static function ($data, string $key, int $default) {
    if (!is_array($data)) {
        return $data;
    }

    return apply_repeater_item_limit($data, $key, $default);
};

add_filter('NscSoftware/addComponentData?name=NSCBlockStats', static function ($data) use ($slice) {
    return $slice($data, 'stats', 4);
}, 5);

add_filter('NscSoftware/addComponentData?name=NSCBlockOurServices', static function ($data) use ($slice) {
    return $slice($data, 'services', 8);
}, 5);

add_filter('NscSoftware/addComponentData?name=NSCBlockWhyUs', static function ($data) use ($slice) {
    return $slice($data, 'items', 7);
}, 5);

add_filter('NscSoftware/addComponentData?name=NSCBlockHowWeWork', static function ($data) use ($slice) {
    return $slice($data, 'items', 4);
}, 5);

add_filter('NscSoftware/addComponentData?name=NSCBlockOurLeaders', static function ($data) use ($slice) {
    return $slice($data, 'leaders', 4);
}, 5);

add_filter('NscSoftware/addComponentData?name=NSCBlockGlobalPresence', static function ($data) use ($slice) {
    return $slice($data, 'locations', 5);
}, 5);

add_filter('NscSoftware/addComponentData?name=NSCBlockAiImpact', static function ($data) use ($slice) {
    return $slice($data, 'items', 4);
}, 5);

add_filter('NscSoftware/addComponentData?name=NSCBlockAiSecurity', static function ($data) use ($slice) {
    return $slice($data, 'items', 3);
}, 5);
