<?php

namespace CLM;

if (!defined('ABSPATH')) {
    exit;
}

use FilesystemIterator;
use MatthiasMullie\Minify;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CLM_Minifier {

    public function __construct() {
        // Schedule daily minification
        add_action('clm_daily_minification', array($this, 'minify_files'));

        // Handle manual minification and restoration
        add_action('admin_init', array($this, 'handle_actions'));
    }

    public function minify_files() {
        $plugins_dir = WP_PLUGIN_DIR;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($plugins_dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, array('css', 'js'))) {
                    $filepath = $file->getRealPath();
                    $relative_path = str_replace($plugins_dir, '', $filepath);

                    // Backup original file
                    $backup_path = CLM_BACKUP_DIR . $relative_path;
                    if (!file_exists(dirname($backup_path))) {
                        wp_mkdir_p(dirname($backup_path));
                    }
                    if (!file_exists($backup_path)) {
                        copy($filepath, $backup_path);
                    }

                    // Initialize minifier
                    if ($ext === 'css') {
                        $minifier = new Minify\CSS($filepath);
                    } else {
                        $minifier = new Minify\JS($filepath);
                    }

                    // Minify and overwrite the original file
                    try {
                        $minifier->minify($filepath);
                    } catch (Exception $e) {
                        $this->log_error("Error minifying {$ext} file {$filepath}: " . $e->getMessage());
                        continue;
                    }
                }
            }
        }
    }

    public function restore_files() {
        $backup_dir = CLM_BACKUP_DIR;
        if (!file_exists($backup_dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $backup_path = $file->getRealPath();
                $relative_path = str_replace($backup_dir, '', $backup_path);
                $original_path = WP_PLUGIN_DIR . $relative_path;

                // Restore the original file
                copy($backup_path, $original_path);
            }
        }
    }

    public function handle_actions() {
        // Manual Minification
        if (isset($_POST['clm_manual_minify'])) {
            if (check_admin_referer('clm_manual_minify_action', 'clm_manual_minify_nonce')) {
                $this->minify_files();
                add_action('admin_notices', array($this, 'minify_notice'));
            }
        }

        // Restore Files
        if (isset($_POST['clm_restore_files'])) {
            if (check_admin_referer('clm_restore_files_action', 'clm_restore_files_nonce')) {
                $this->restore_files();
                add_action('admin_notices', array($this, 'restore_notice'));
            }
        }
    }

    public function minify_notice() {
        echo '<div class="notice notice-success is-dismissible"><p>Minification completed successfully.</p></div>';
    }

    public function restore_notice() {
        echo '<div class="notice notice-success is-dismissible"><p>Restoration completed successfully.</p></div>';
    }

    private function log_error($message) {
        if (WP_DEBUG === true) {
            error_log('[CLM Minifier] ' . $message);
        }
    }
}
