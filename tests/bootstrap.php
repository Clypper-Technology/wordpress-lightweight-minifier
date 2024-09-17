<?php
/**
 * PHPUnit bootstrap file
 */

$_tests_dir = getenv('WP_PHPUNIT__DIR');

if (!$_tests_dir) {
    $_tests_dir = dirname(__FILE__) . '/../vendor/wp-phpunit/wp-phpunit';
}

if (!file_exists("$_tests_dir/includes/functions.php")) {
    echo "Could not find $_tests_dir/includes/functions.php, have you run 'composer install --dev'?";
    exit(1);
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/clypper-lightweight-minifier.php';
});

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
