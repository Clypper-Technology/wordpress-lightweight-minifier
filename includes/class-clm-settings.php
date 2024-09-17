<?php

namespace CLM;

if (!defined('ABSPATH')) {
    exit;
}

class CLM_Settings {

    public function __construct() {
        // Add settings menu
        add_action('admin_menu', array($this, 'add_settings_page'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page() {
        add_options_page(
            'Clypper\'s Minifier Settings',
            'Clypper\'s Minifier',
            'manage_options',
            'clm-settings',
            array($this, 'settings_page_content')
        );
    }

    public function register_settings() {
        register_setting('clm_settings_group', 'clm_defer_css', array($this, 'sanitize_textarea'));
        register_setting('clm_settings_group', 'clm_defer_js', array($this, 'sanitize_textarea'));

        add_settings_section('clm_settings_section', '', null, 'clm-settings');

        add_settings_field(
            'clm_defer_css',
            'CSS Files to Defer (one per line)',
            array($this, 'defer_css_callback'),
            'clm-settings',
            'clm_settings_section'
        );

        add_settings_field(
            'clm_defer_js',
            'JS Files to Defer (one per line)',
            array($this, 'defer_js_callback'),
            'clm-settings',
            'clm_settings_section'
        );
    }

    public function settings_page_content() {
        ?>
        <div class="wrap">
            <h1>Clypper's Minifier Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('clm_settings_group');
                do_settings_sections('clm-settings');
                submit_button();
                ?>
            </form>

            <h2>Manual Minification</h2>
            <form method="post">
                <?php
                wp_nonce_field('clm_manual_minify_action', 'clm_manual_minify_nonce');
                ?>
                <input type="submit" name="clm_manual_minify" class="button button-primary" value="Minify Now">
            </form>

            <h2>Restore Original Files</h2>
            <form method="post">
                <?php
                wp_nonce_field('clm_restore_files_action', 'clm_restore_files_nonce');
                ?>
                <input type="submit" name="clm_restore_files" class="button" value="Restore Originals">
            </form>
        </div>
        <?php
    }

    public function defer_css_callback() {
        $defer_css = get_option('clm_defer_css');
        echo '<textarea name="clm_defer_css" rows="5" cols="50">' . esc_textarea($defer_css) . '</textarea>';
    }

    public function defer_js_callback() {
        $defer_js = get_option('clm_defer_js');
        echo '<textarea name="clm_defer_js" rows="5" cols="50">' . esc_textarea($defer_js) . '</textarea>';
    }

    public function sanitize_textarea($input) {
        return sanitize_textarea_field($input);
    }
}
