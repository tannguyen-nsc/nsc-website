<?php

declare(strict_types=1);

/**
 * Move Contact Form CFDB7 top-level "Contact Forms" under Contact Form 7 → Contact.
 *
 * CFDB7 registers this page with add_menu_page( ..., 'cfdb7-list.php', ... ). The theme
 * re-registers the same slug under Contact Form 7 via add_submenu_page( 'wpcf7', ..., 'cfdb7-list.php' ).
 * WordPress builds the hook name with get_plugin_page_hookname(): because 'cfdb7-list.php' is already
 * in $admin_page_hooks from the top-level item, BOTH registrations attach to
 * `toplevel_page_cfdb7-list`, so admin.php runs the list output twice. We dedupe after both register.
 */
function nsc_cfdb7_render_list_table_page(): void
{
    try {
        $ref = new ReflectionClass('CFDB7_Wp_Main_Page');
        if (!$ref->hasMethod('list_table_page')) {
            return;
        }
        $page = $ref->newInstanceWithoutConstructor();
        $page->list_table_page();
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('NSC CFDB7 submenu render: ' . $e->getMessage());
        }
    }
}

add_action(
    'admin_menu',
    static function (): void {
        if (!class_exists('CFDB7_Wp_Main_Page')) {
            return;
        }

        remove_menu_page('cfdb7-list.php');

        $cap = current_user_can('cfdb7_access') ? 'cfdb7_access' : 'manage_options';

        add_submenu_page(
            'wpcf7',
            __('Contact Forms', 'contact-form-cfdb7'),
            __('Submissions', 'NscSoftware'),
            $cap,
            'cfdb7-list.php',
            'nsc_cfdb7_render_list_table_page'
        );

        // CFDB7 add-ons register under parent slug cfdb7-list.php; re-parent under Contact Form 7.
        remove_submenu_page('cfdb7-list.php', 'cfdb7-extensions');
        if (\function_exists('cfdb7_extensions')) {
            add_submenu_page(
                'wpcf7',
                __('Extensions', 'contact-form-cfdb7'),
                '<span style="color:#f18500">' . esc_html__('Addons', 'contact-form-cfdb7') . '</span>',
                'manage_options',
                'cfdb7-extensions',
                'cfdb7_extensions'
            );
        }
    },
    100
);

add_action(
    'admin_menu',
    static function (): void {
        if (!class_exists('CFDB7_Wp_Main_Page')) {
            return;
        }
        $hook = 'toplevel_page_cfdb7-list';
        if (!has_action($hook)) {
            return;
        }
        remove_all_actions($hook);
        add_action($hook, 'nsc_cfdb7_render_list_table_page', 10, 0);
    },
    110
);
