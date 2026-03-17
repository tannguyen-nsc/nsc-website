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

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_script('NscSoftware/assets/admin', Asset::requireUrl('assets/admin.js'), [], null);
    wp_script_add_data('NscSoftware/assets/admin', 'module', true);

    wp_localize_script('NscSoftware/assets/admin', 'NscSoftwareData', [
        'componentsWithScript' => ComponentManager::getInstance()->getComponentsWithScript(),
        'templateDirectoryUri' => get_template_directory_uri(),
    ]);

    wp_enqueue_style('NscSoftware/assets/admin', Asset::requireUrl('assets/admin.scss'), [], null);
});
