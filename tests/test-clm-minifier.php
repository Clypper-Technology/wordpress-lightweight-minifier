<?php

use CLM\CLM_Minifier;

class Test_CLM_Minifier extends WP_UnitTestCase {

    protected $minifier;

    public function setUp(): void {
        parent::setUp();

        // Initialize the minifier class.
        $this->minifier = new CLM_Minifier();

        // Set up a mock plugins directory and backup directory.
        $this->plugins_dir = WP_CONTENT_DIR . '/plugins';
        $this->backup_dir = WP_CONTENT_DIR . '/clm-backups';

        // Create test directories if they don't exist.
        if (!file_exists($this->plugins_dir)) {
            wp_mkdir_p($this->plugins_dir);
        }
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }

        // Clean up before tests.
        $this->recursive_delete($this->plugins_dir);
        $this->recursive_delete($this->backup_dir);

        // Set up test files.
        $this->set_up_test_files();
    }

    public function tearDown(): void {
        parent::tearDown();

        // Clean up after tests.
        $this->recursive_delete($this->plugins_dir);
        $this->recursive_delete($this->backup_dir);
    }

    private function set_up_test_files() {
        // Create sample CSS and JS files.
        $sample_css = 'body { color: red; }';
        $sample_js = 'function test() { console.log("Hello World"); }';

        file_put_contents($this->plugins_dir . '/test.css', $sample_css);
        file_put_contents($this->plugins_dir . '/test.js', $sample_js);
    }

    private function recursive_delete($dir) {
        if (!file_exists($dir)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($dir);
    }

    public function test_minify_files() {
        // Run the minification.
        $this->minifier->minify_files();

        // Check that the backup files exist.
        $this->assertFileExists($this->backup_dir . '/test.css');
        $this->assertFileExists($this->backup_dir . '/test.js');

        // Check that the original files have been minified.
        $minified_css = file_get_contents($this->plugins_dir . '/test.css');
        $minified_js = file_get_contents($this->plugins_dir . '/test.js');

        $this->assertNotEquals('body { color: red; }', $minified_css);
        $this->assertNotEquals('function test() { console.log("Hello World"); }', $minified_js);
    }

    public function test_restore_files() {
        // Run the minification first.
        $this->minifier->minify_files();

        // Run the restoration.
        $this->minifier->restore_files();

        // Check that the original files have been restored.
        $restored_css = file_get_contents($this->plugins_dir . '/test.css');
        $restored_js = file_get_contents($this->plugins_dir . '/test.js');

        $this->assertEquals('body { color: red; }', $restored_css);
        $this->assertEquals('function test() { console.log("Hello World"); }', $restored_js);
    }

    public function test_handle_actions_minify() {
        // Simulate form submission.
        $_POST['clm_manual_minify'] = '1';
        $_POST['clm_manual_minify_nonce'] = wp_create_nonce('clm_manual_minify_action');

        // Run the action handler.
        $this->minifier->handle_actions();

        // Check that the files have been minified.
        $minified_css = file_get_contents($this->plugins_dir . '/test.css');
        $minified_js = file_get_contents($this->plugins_dir . '/test.js');

        $this->assertNotEquals('body { color: red; }', $minified_css);
        $this->assertNotEquals('function test() { console.log("Hello World"); }', $minified_js);
    }

    public function test_handle_actions_restore() {
        // Minify first.
        $this->minifier->minify_files();

        // Simulate form submission.
        $_POST['clm_restore_files'] = '1';
        $_POST['clm_restore_files_nonce'] = wp_create_nonce('clm_restore_files_action');

        // Run the action handler.
        $this->minifier->handle_actions();

        // Check that the files have been restored.
        $restored_css = file_get_contents($this->plugins_dir . '/test.css');
        $restored_js = file_get_contents($this->plugins_dir . '/test.js');

        $this->assertEquals('body { color: red; }', $restored_css);
        $this->assertEquals('function test() { console.log("Hello World"); }', $restored_js);
    }

    public function test_minify_files_with_empty_directory() {
        // Clean up plugins directory.
        $this->recursive_delete($this->plugins_dir);
        wp_mkdir_p($this->plugins_dir);

        // Run minification.
        $this->minifier->minify_files();

        // Ensure no exceptions are thrown and process completes.
        $this->assertTrue(true);
    }
}
