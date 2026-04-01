<?php

namespace NscSoftware\Assets;

use NscSoftware\Utils\Asset;
use NscSoftware\ComponentManager;
use NscSoftware\Utils\ScriptAndStyleLoader;

call_user_func(function () {
    $loader = new ScriptAndStyleLoader();
    add_filter('script_loader_tag', [$loader, 'filterScriptLoaderTag'], 10, 3);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('NscSoftware/assets/main', Asset::requireUrl('assets/main.js'), [], null);
    wp_script_add_data('NscSoftware/assets/main', 'module', true);

    wp_localize_script('NscSoftware/assets/main', 'NscSoftwareData', [
        'componentsWithScript' => ComponentManager::getInstance()->getComponentsWithScript(),
        'templateDirectoryUri' => get_template_directory_uri(),
    ]);

    wp_enqueue_style('NscSoftware/assets/main', Asset::requireUrl('assets/main.scss'), [], null);
    wp_enqueue_style('NscSoftware/assets/print', Asset::requireUrl('assets/print.scss'), [], null, 'print');
});

// Safety net: if any plugin/theme still adds localhost hints, strip them from output.
add_filter('wp_resource_hints', function (array $urls, string $relation_type): array {
    if ($relation_type !== 'dns-prefetch' && $relation_type !== 'preconnect') {
        return $urls;
    }

    return array_values(array_filter($urls, static function ($url): bool {
        $host = (string) wp_parse_url((string) $url, PHP_URL_HOST);
        $urlValue = (string) $url;

        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }

        return stripos($urlValue, '//localhost') === false
            && stripos($urlValue, '//127.0.0.1') === false
            && stripos($urlValue, '//[::1]') === false;
    }));
}, 20, 2);

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_script('NscSoftware/assets/admin', Asset::requireUrl('assets/admin.js'), [], null);
    wp_script_add_data('NscSoftware/assets/admin', 'module', true);

    wp_localize_script('NscSoftware/assets/admin', 'NscSoftwareData', [
        'componentsWithScript' => ComponentManager::getInstance()->getComponentsWithScript(),
        'templateDirectoryUri' => get_template_directory_uri(),
    ]);

    wp_enqueue_style('NscSoftware/assets/admin', Asset::requireUrl('assets/admin.scss'), [], null);
});
