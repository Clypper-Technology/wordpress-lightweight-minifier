<?php
/*
Plugin Name: Clypper's Lightweight JS & CSS Minifier
Description: Minifies CSS and JS files in the plugins directory with backup and restoration options.
Version: 1.0.0
Author: Your Name
License: GPL2
Text Domain: clypper-lightweight-minifier
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CLM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLM_PLUGIN_FILE', __FILE__);

// Include Composer autoloader
require_once CLM_PLUGIN_DIR . 'vendor/autoload.php';

// Include the main plugin class
require_once CLM_PLUGIN_DIR . 'includes/class-clm-plugin.php';

// Initialize the plugin
function clm_init() {
    $plugin = new CLM_Plugin();
}
add_action('plugins_loaded', 'clm_init');
