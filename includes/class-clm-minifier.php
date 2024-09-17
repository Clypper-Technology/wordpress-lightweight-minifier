<?php

namespace CLM;

if (!defined('ABSPATH')) {
    exit;
}

use FilesystemIterator;
use MatthiasMullie\Minify;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveCallbackFilterIterator;

class CLM_Minifier {

    /**
     * Counters for tracking progress.
     */
    private int $total_files = 0;
    private int $success_files = 0;
    private int $failed_files = 0;

    /**
     * Array to store error details.
     *
     * @var array
     */
    private array $error_details = [];

    /**
     * Summary data for display.
     */
    private $last_minification_summary = null;
    private $last_restoration_summary = null;

    /**
     * Backup directory path.
     */
    private string $backup_dir;

    public function __construct() {
        // Define the backup directory
        $this->backup_dir = trailingslashit(WP_CONTENT_DIR) . 'clm-backups/';

        // Ensure the backup directory exists
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }

        // Schedule daily minification
        add_action('clm_daily_minification', array($this, 'minify_files'));

        // Handle manual minification and restoration
        add_action('admin_init', array($this, 'handle_actions'));

        // Hook admin notices
        add_action('admin_notices', array($this, 'minify_notice'));
        add_action('admin_notices', array($this, 'restore_notice'));
    }

    /**
     * Minify CSS and JS files in specified directories.
     *
     * @param bool $is_manual Indicates if the minification is triggered manually.
     */
    public function minify_files($is_manual = false) {
        // Directories to process
        $directories = array(ABSPATH . 'wp-includes', ABSPATH . 'wp-content');

        foreach ($directories as $base_dir) {
            // Normalize base directory path
            $base_dir = rtrim($base_dir, '/\\') . DIRECTORY_SEPARATOR;

            // Initialize Recursive Directory Iterator with a filter to exclude the backup directory
            $directory_iterator = new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS);
            $filter_iterator = new RecursiveCallbackFilterIterator($directory_iterator, function ($current, $key, $iterator) use ($base_dir) {
                // Exclude the backup directory
                if ($current->isDir()) {
                    $dir_path = $current->getRealPath();
                    if ($dir_path === false) {
                        return false;
                    }
                    // Normalize backup directory path
                    $normalized_backup_dir = realpath($this->backup_dir);
                    if ($normalized_backup_dir === false) {
                        return false;
                    }
                    return strpos($dir_path, $normalized_backup_dir) !== 0;
                }
                return true;
            });

            $iterator = new RecursiveIteratorIterator($filter_iterator);

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $ext = strtolower($file->getExtension());

                if (in_array($ext, array('css', 'js'))) {
                    $this->total_files++;

                    $filepath = $file->getRealPath();
                    if ($filepath === false) {
                        $this->failed_files++;
                        $this->error_details[] = "Invalid file path: {$file->getPathname()}";
                        continue;
                    }

                    // Get path relative to ABSPATH
                    $relative_path = str_replace(ABSPATH, '', $filepath);
                    $relative_path = ltrim($relative_path, '/\\');

                    // Define backup path
                    $backup_path = $this->backup_dir . $relative_path;

                    // Ensure the backup directory exists
                    $backup_subdir = dirname($backup_path);
                    if (!file_exists($backup_subdir)) {
                        if (wp_mkdir_p($backup_subdir)) {
                            // Directory created successfully
                        } else {
                            $this->failed_files++;
                            $this->error_details[] = "Failed to create backup directory: {$backup_subdir}";
                            continue; // Skip this file if backup directory can't be created
                        }
                    }

                    // Move the original file to backup directory
                    if (!rename($filepath, $backup_path)) {
                        $this->failed_files++;
                        $this->error_details[] = "Failed to move file to backup: {$filepath}";
                        continue; // Skip minification if backup fails
                    }

                    // Initialize minifier with the backup file
                    try {
                        if ($ext === 'css') {
                            $minifier = new Minify\CSS($backup_path);
                        } else {
                            $minifier = new Minify\JS($backup_path);
                        }

                        // Minify and overwrite the original file path with minified content
                        $minifier->minify($filepath);
                        $this->success_files++;
                    } catch (\Exception $e) {
                        $this->failed_files++;
                        $this->error_details[] = "Error minifying {$ext} file {$filepath}: " . $e->getMessage();
                        continue;
                    }
                }
            }
        }

        if ($is_manual) {
            // Store summary data for display
            $this->last_minification_summary = array(
                'total'    => $this->total_files,
                'success'  => $this->success_files,
                'failed'   => $this->failed_files,
                'errors'   => $this->error_details,
            );

            // Reset counters for next operation
            $this->reset_counters();
        }
    }

    /**
     * Restore original CSS and JS files from backups.
     */
    public function restore_files() {
        $backup_dir = $this->backup_dir;
        if (!file_exists($backup_dir)) {
            // Store error for display
            $this->last_restoration_summary = array(
                'total'    => 0,
                'success'  => 0,
                'failed'   => 0,
                'errors'   => array("Backup directory does not exist: {$backup_dir}"),
            );
            return;
        }

        // Initialize Recursive Directory Iterator
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup_dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $this->total_files++;

            $backup_path = $file->getRealPath();
            if ($backup_path === false) {
                $this->failed_files++;
                $this->error_details[] = "Invalid backup file path: {$file->getPathname()}";
                continue;
            }

            // Get path relative to backup directory
            $relative_path = str_replace($backup_dir, '', $backup_path);
            $relative_path = ltrim($relative_path, '/\\');

            // Define original file path
            $original_path = ABSPATH . $relative_path;

            // Ensure the original directory exists
            $original_dir = dirname($original_path);
            if (!file_exists($original_dir)) {
                if (wp_mkdir_p($original_dir)) {
                    // Directory created successfully
                } else {
                    $this->failed_files++;
                    $this->error_details[] = "Failed to create directory for restoration: {$original_dir}";
                    continue; // Skip restoration if directory can't be created
                }
            }

            // Delete the minified file before restoring
            if (file_exists($original_path)) {
                if (!unlink($original_path)) {
                    $this->failed_files++;
                    $this->error_details[] = "Failed to delete minified file before restoration: {$original_path}";
                    continue;
                }
            }

            // Move the backup file back to original location
            if (rename($backup_path, $original_path)) {
                $this->success_files++;
                // Delete the backup file after successful restoration
                // (Since it's already moved, no need to delete unless backups are kept for history)
            } else {
                $this->failed_files++;
                $this->error_details[] = "Failed to restore: {$original_path}";
            }
        }

        // Store summary data for display
        $this->last_restoration_summary = array(
            'total'    => $this->total_files,
            'success'  => $this->success_files,
            'failed'   => $this->failed_files,
            'errors'   => $this->error_details,
        );

        // Reset counters for next operation
        $this->reset_counters();
    }

    /**
     * Handle manual minification and restoration actions.
     */
    public function handle_actions() {
        // Manual Minification
        if (isset($_POST['clm_manual_minify'])) {
            if (check_admin_referer('clm_manual_minify_action', 'clm_manual_minify_nonce')) {
                $this->minify_files(true);
                // Notices are handled in admin_notices hook
            }
        }

        // Restore Files
        if (isset($_POST['clm_restore_files'])) {
            if (check_admin_referer('clm_restore_files_action', 'clm_restore_files_nonce')) {
                $this->restore_files();
                // Notices are handled in admin_notices hook
            }
        }
    }

    /**
     * Display minification progress as an admin notice.
     */
    public function minify_notice() {
        if ($this->last_minification_summary) {
            $summary = $this->last_minification_summary;

            $message  = "<strong>Minification Process:</strong><br>";
            $message .= "Total Files: {$summary['total']}<br>";
            $message .= "Successfully Minified: {$summary['success']}<br>";
            $message .= "Failed: {$summary['failed']}<br>";

            if (!empty($summary['errors'])) {
                $message .= "<strong>Errors:</strong><br>";
                foreach ($summary['errors'] as $error) {
                    $message .= "- " . esc_html($error) . "<br>";
                }
            }

            echo '<div class="notice notice-info is-dismissible"><p>' . $message . '</p></div>';

            // Reset the summary after displaying
            $this->last_minification_summary = null;
        }
    }

    /**
     * Display restoration progress as an admin notice.
     */
    public function restore_notice() {
        if ($this->last_restoration_summary) {
            $summary = $this->last_restoration_summary;

            $message  = "<strong>Restoration Process:</strong><br>";
            $message .= "Total Files: {$summary['total']}<br>";
            $message .= "Successfully Restored: {$summary['success']}<br>";
            $message .= "Failed: {$summary['failed']}<br>";

            if (!empty($summary['errors'])) {
                $message .= "<strong>Errors:</strong><br>";
                foreach ($summary['errors'] as $error) {
                    $message .= "- " . esc_html($error) . "<br>";
                }
            }

            echo '<div class="notice notice-warning is-dismissible"><p>' . $message . '</p></div>';

            // Reset the summary after displaying
            $this->last_restoration_summary = null;
        }
    }

    /**
     * Reset counters and error details.
     */
    private function reset_counters() {
        $this->total_files    = 0;
        $this->success_files  = 0;
        $this->failed_files   = 0;
        $this->error_details  = [];
    }

    /**
     * Log error messages to the debug log.
     *
     * @param string $message The error message to log.
     */
    private function log_error($message) {
        if (WP_DEBUG === true) {
            error_log('[CLM Minifier] ' . $message);
        }
        $this->error_details[] = $message;
    }
}