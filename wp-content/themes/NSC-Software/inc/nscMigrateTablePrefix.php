<?php

declare(strict_types=1);

/**
 * Shared WordPress table prefix migration (single-site). Used by CLI and NSC seeders admin.
 */

namespace NscSoftware\WpTablePrefix;

/**
 * Normalize user input to a valid table prefix ending with a single underscore (e.g. wp_, nsc_).
 * Strips non-alphanumeric/underscore characters, trims trailing underscores, then appends "_".
 */
function normalize_prefix_input(string $raw): string
{
    $s = \trim($raw);
    $s = (string) \preg_replace('/[^a-zA-Z0-9_]/', '', $s);
    if ($s === '') {
        return '';
    }
    $s = \rtrim($s, '_') . '_';
    if (\strlen($s) > 64) {
        return '';
    }

    return \preg_match('/^[a-zA-Z0-9_]+$/', $s) ? $s : '';
}

/**
 * @return true|\WP_Error
 */
function migrate(string $newPrefix, string $wpRoot, bool $dryRun = false): \WP_Error|bool
{
    global $wpdb;

    if (\defined('MULTISITE') && \MULTISITE) {
        return new \WP_Error('nsc_prefix_multisite', \__('Multisite is not supported.', 'NscSoftware'));
    }

    $oldPrefix = $wpdb->prefix;
    if ($oldPrefix === $newPrefix) {
        return new \WP_Error(
            'nsc_prefix_same',
            \__('The new prefix is the same as the current prefix.', 'NscSoftware')
        );
    }

    $dbName = $wpdb->get_var('SELECT DATABASE()');
    if ($dbName === null || $dbName === '') {
        return new \WP_Error('nsc_prefix_db', \__('Could not determine database name.', 'NscSoftware'));
    }

    if (!\preg_match('/^[a-zA-Z0-9_]+$/', (string) $dbName)) {
        return new \WP_Error('nsc_prefix_db', \__('Unexpected database name.', 'NscSoftware'));
    }

    $escapedLike = $wpdb->esc_like($oldPrefix) . '%';
    $tables = $wpdb->get_col(
        $wpdb->prepare("SHOW TABLES FROM `{$dbName}` LIKE %s", $escapedLike)
    );

    if ($tables === []) {
        return new \WP_Error(
            'nsc_prefix_tables',
            \__('No tables found for the current prefix.', 'NscSoftware')
        );
    }

    $renames = [];
    foreach ($tables as $table) {
        if (\strpos($table, $oldPrefix) !== 0) {
            return new \WP_Error('nsc_prefix_table', \__('Unexpected table name.', 'NscSoftware'));
        }
        $suffix = \substr($table, \strlen($oldPrefix));
        $renames[] = [$table, $newPrefix . $suffix];
    }

    foreach ($renames as [, $newTable]) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $newTable));
        if ($exists === $newTable && !$dryRun) {
            return new \WP_Error(
                'nsc_prefix_exists',
                /* translators: %s: table name */
                \sprintf(\__('Target table already exists: %s', 'NscSoftware'), $newTable)
            );
        }
    }

    $wpConfigPath = \rtrim($wpRoot, '/\\') . '/wp-config.php';
    $pattern = '/(\$table_prefix\s*=\s*[\'"])(' . \preg_quote($oldPrefix, '/') . ')([\'"]\s*;)/';

    $config = \file_get_contents($wpConfigPath);
    if ($config === false) {
        return new \WP_Error('nsc_prefix_wpconfig', \__('Could not read wp-config.php.', 'NscSoftware'));
    }
    if (!\preg_match($pattern, $config)) {
        return new \WP_Error(
            'nsc_prefix_wpconfig',
            \__('Could not find $table_prefix in wp-config.php. Update it manually.', 'NscSoftware')
        );
    }
    if (!$dryRun && !\is_writable($wpConfigPath)) {
        return new \WP_Error(
            'nsc_prefix_wpconfig',
            \__('wp-config.php is not writable. Fix permissions before migrating.', 'NscSoftware')
        );
    }

    $newOptionsTable = $newPrefix . 'options';
    $newUsermetaTable = $newPrefix . 'usermeta';

    foreach ($renames as [$oldTable, $newTable]) {
        if ($dryRun) {
            continue;
        }
        if (!\preg_match('/^[a-zA-Z0-9_]+$/', $oldTable) || !\preg_match('/^[a-zA-Z0-9_]+$/', $newTable)) {
            return new \WP_Error('nsc_prefix_invalid', \__('Invalid table name.', 'NscSoftware'));
        }
        $ok = $wpdb->query("RENAME TABLE `{$oldTable}` TO `{$newTable}`");
        if ($ok === false) {
            return new \WP_Error(
                'nsc_prefix_rename',
                $wpdb->last_error !== '' ? $wpdb->last_error : \__('Database error on RENAME TABLE.', 'NscSoftware')
            );
        }
    }

    $oldUserRolesOption = $oldPrefix . 'user_roles';
    $newUserRolesOption = $newPrefix . 'user_roles';

    if (!$dryRun) {
        $updated = $wpdb->update(
            $newOptionsTable,
            ['option_name' => $newUserRolesOption],
            ['option_name' => $oldUserRolesOption],
            ['%s'],
            ['%s']
        );
        if ($updated === false) {
            return new \WP_Error(
                'nsc_prefix_options',
                $wpdb->last_error !== '' ? $wpdb->last_error : \__('Could not update user_roles option.', 'NscSoftware')
            );
        }
    }

    $oldPrefixLen = \strlen($oldPrefix);
    if (!$dryRun) {
        $sql = $wpdb->prepare(
            "UPDATE `{$newUsermetaTable}` SET meta_key = CONCAT(%s, SUBSTRING(meta_key, %d))
			 WHERE meta_key LIKE %s",
            $newPrefix,
            $oldPrefixLen + 1,
            $wpdb->esc_like($oldPrefix) . '%'
        );
        $ok = $wpdb->query($sql);
        if ($ok === false) {
            return new \WP_Error(
                'nsc_prefix_usermeta',
                $wpdb->last_error !== '' ? $wpdb->last_error : \__('Could not update usermeta.', 'NscSoftware')
            );
        }
    }

    if (!$dryRun) {
        $updatedConfig = \preg_replace_callback(
            $pattern,
            static function (array $m) use ($newPrefix): string {
                return $m[1] . $newPrefix . $m[3];
            },
            $config,
            1
        );
        if ($updatedConfig === null || $updatedConfig === $config) {
            return new \WP_Error('nsc_prefix_wpconfig', \__('Failed to patch wp-config.php.', 'NscSoftware'));
        }
        if (\file_put_contents($wpConfigPath, $updatedConfig) === false) {
            return new \WP_Error('nsc_prefix_wpconfig', \__('Could not write wp-config.php.', 'NscSoftware'));
        }
    }

    return true;
}
