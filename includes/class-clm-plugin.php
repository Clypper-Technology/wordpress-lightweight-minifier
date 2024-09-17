<?php

namespace CLM;

if (!defined('ABSPATH')) {
    exit;
}

class CLM_Plugin {

    public function __construct() {
        // Define constants
        $this->define_constants();

        // Include required files
        $this->includes();

        // Initialize classes
        $this->init_classes();

        // Register activation and deactivation hooks
        register_activation_hook(CLM_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(CLM_PLUGIN_FILE, array($this, 'deactivate'));
    }

    private function define_constants() {
        define('CLM_VERSION', '1.0.0');
        define('CLM_BACKUP_DIR', WP_CONTENT_DIR . '/clm-backups/');
    }

    private function includes() {
        require_once CLM_PLUGIN_DIR . 'includes/class-clm-minifier.php';
        require_once CLM_PLUGIN_DIR . 'includes/class-clm-settings.php';
        require_once CLM_PLUGIN_DIR . 'includes/class-clm-defer.php';
    }

    private function init_classes() {
        new CLM_Minifier();
        new CLM_Settings();
        new CLM_Defer();
    }

    public function activate() {
        if (!wp_next_scheduled('clm_daily_minification')) {
            wp_schedule_event(time(), 'daily', 'clm_daily_minification');
        }
        if (!file_exists(CLM_BACKUP_DIR)) {
            wp_mkdir_p(CLM_BACKUP_DIR);
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('clm_daily_minification');
    }
}
