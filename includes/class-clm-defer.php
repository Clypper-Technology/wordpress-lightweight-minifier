<?php

namespace CLM;

if (!defined('ABSPATH')) {
    exit;
}

class CLM_Defer {

    public function __construct() {
        add_filter('script_loader_tag', array($this, 'add_defer_to_js'), 10, 2);
        add_filter('style_loader_tag', array($this, 'add_defer_to_css'), 10, 4);
    }

    public function add_defer_to_js($tag, $handle) {
        $defer_js = get_option('clm_defer_js');
        if ($defer_js) {
            $js_files = array_map('trim', explode("\n", $defer_js));
            global $wp_scripts;
            if (isset($wp_scripts->registered[$handle])) {
                $script = $wp_scripts->registered[$handle];
                foreach ($js_files as $js_file) {
                    if (strpos($script->src, $js_file) !== false) {
                        return str_replace(' src', ' defer="defer" src', $tag);
                    }
                }
            }
        }
        return $tag;
    }

    public function add_defer_to_css($html, $handle, $href, $media) {
        $defer_css = get_option('clm_defer_css');
        if ($defer_css) {
            $css_files = array_map('trim', explode("\n", $defer_css));
            foreach ($css_files as $css_file) {
                if (strpos($href, $css_file) !== false) {
                    return str_replace("rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $html);
                }
            }
        }
        return $html;
    }
}
