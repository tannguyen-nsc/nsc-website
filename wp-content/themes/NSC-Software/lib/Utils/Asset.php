<?php

namespace NscSoftware\Utils;

/**
 * Provides a set of methods that are used to get the url or path
 * of an asset.
 */
class Asset
{
    /**
     * The internal list (array) of assets.
     *
     * @var array
     */
    protected static array $assetManifest;
    protected static ?string $hotServerUrl = null;

    /**
     * Gets the asset's url.
     *
     * @since 0.1.0
     *
     * @param string $asset The filename of the required asset.
     *
     * @return string|boolean Returns the url or false if the asset is not found.
     */
    public static function requireUrl(string $asset)
    {
        return self::get('url', $asset);
    }

    /**
     * Gets the asset's absolute path.
     *
     * @since 0.1.0
     *
     * @param string $asset The filename of the required asset.
     *
     * @return string|boolean Returns the absolute path or false if the asset is not found.
     */
    public static function requirePath(string $asset)
    {
        return self::get('path', $asset);
    }

    /**
     * Gets the contents of an asset.
     * Useful for loading SVGs or other files inline.
     *
     * @since 0.2.0
     *
     * @param string $asset Asset path (relative to the theme directory).
     * @return string|boolean Returns the file contents or false in case of failure.
     */
    public static function getContents(string $asset)
    {
        $file = self::requirePath($asset);
        if (file_exists($file)) {
            return file_get_contents($file);
        } else {
            trigger_error("File not found at: {$asset}", E_USER_WARNING);
            return false;
        }
    }

    /**
     * Gets the asset’s url or absolute path.
     *
     * If the asset is not found, it will return the original asset path.
     * This is useful for loading assets from the theme directory.
     *
     * @param string $returnType The type of the return value. Either 'url' or 'path'.
     * @param string $asset The filename of the required asset.
     * @return string|false
     */
    protected static function get(string $returnType, string $asset)
    {
        if (!isset(self::$assetManifest)) {
            $distPath = get_template_directory() . '/dist';
            $manifestPath = $distPath . '/manifest.json';
            if (is_file($manifestPath)) {
                self::$assetManifest = json_decode(file_get_contents($manifestPath), true);
            } else {
                self::$assetManifest = [];
            }
        }

        $assetSuffix = self::$assetManifest[$asset]['file'] ?? $asset;
        $filePath = get_template_directory() . '/dist/' . $assetSuffix;

        if ('path' == $returnType) {
            return file_exists($filePath) ? $filePath : get_template_directory() . '/' . $assetSuffix;
        }

        if ('url' == $returnType) {
            if (self::isHotModuleReplacement()) {
                return trailingslashit((string) self::$hotServerUrl) . $asset;
            }
            return file_exists($filePath) ? get_template_directory_uri() . '/dist/' . $assetSuffix : get_template_directory_uri() . '/' . $assetSuffix;
        }

        return false;
    }

    /**
     * Checks if the current environment is a Vite dev server.
     *
     * @return boolean
     */
    public static function isHotModuleReplacement()
    {
        // Prevent localhost dev-server injection on staging/production even when dist/hot exists.
        $env = 'production';
        if (function_exists('wp_get_environment_type')) {
            $env = (string) wp_get_environment_type();
        } elseif (defined('WP_ENVIRONMENT_TYPE')) {
            $env = (string) constant('WP_ENVIRONMENT_TYPE');
        } elseif (defined('WP_ENV')) {
            $env = (string) constant('WP_ENV');
        }

        if (!in_array(strtolower($env), ['local', 'development'], true)) {
            self::$hotServerUrl = null;
            return false;
        }

        $hotFile = self::viteHotFile();
        if (!file_exists($hotFile)) {
            self::$hotServerUrl = null;
            return false;
        }

        $raw = trim((string) file_get_contents($hotFile));
        if ($raw === '') {
            self::$hotServerUrl = null;
            return false;
        }

        self::$hotServerUrl = $raw;
        return true;
    }

    /**
     * Gets the path to the Vite dev server hot file.
     *
     * @return string
     */
    protected static function viteHotFile()
    {
        return get_template_directory() . '/dist/hot';
    }
}
