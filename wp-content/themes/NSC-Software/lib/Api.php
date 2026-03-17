<?php

namespace NscSoftware;

use NscSoftware\ComponentManager;

/**
 * Provides a set of static methods that can be used to register
 * components and render them.
 */
class Api
{
    /**
     * Register a component.
     *
     * @param string $componentName The name of the component.
     * @param string $componentPath The path to the component.
     *
     * @return void
     */
    public static function registerComponent(string $componentName, ?string $componentPath = null)
    {
        $componentManager = ComponentManager::getInstance();
        $componentManager->registerComponent($componentName, $componentPath);
    }

    /**
     * Register components from a path.
     *
     * @param string $componentBasePath The path to the components.
     *
     * @return void
     */
    public static function registerComponentsFromPath(string $componentBasePath)
    {
        foreach (glob("{$componentBasePath}/*", GLOB_ONLYDIR) as $componentPath) {
            $componentName = basename($componentPath);
            self::registerComponent($componentName, $componentPath);
        }
    }

    /**
     * Render a component.
     *
     * @param string $componentName The name of the component.
     * @param array $data The data to pass to the component.
     *
     * @return string The rendered component.
     */
    public static function renderComponent(string $componentName, array $data)
    {
        $data = apply_filters(
            'NscSoftware/addComponentData',
            $data,
            $componentName
        );

        $output = apply_filters(
            'NscSoftware/renderComponent',
            null,
            $componentName,
            $data
        );

        return is_null($output) ? '' : $output;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function registerHooks()
    {
        add_filter('NscSoftware/renderComponent', function ($output, $componentName, $data) {
            return apply_filters(
                "NscSoftware/renderComponent?name={$componentName}",
                $output,
                $componentName,
                $data
            );
        }, 10, 3);

        add_filter('NscSoftware/addComponentData', function ($data, $componentName) {
            return apply_filters(
                "NscSoftware/addComponentData?name={$componentName}",
                $data,
                $componentName
            );
        }, 10, 2);
    }
}
