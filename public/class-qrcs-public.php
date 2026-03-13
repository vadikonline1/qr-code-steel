<?php
/**
 * Public Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class QRCS_Public {
    
    private static $instance = null;
    private $shortcode;
    
    public static function get_instance($shortcode) {
        if (null === self::$instance) {
            self::$instance = new self($shortcode);
        }
        return self::$instance;
    }
    
    private function __construct($shortcode) {
        $this->shortcode = $shortcode;
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Frontend JS
        $js_file = QRCS_PLUGIN_DIR . 'public/js/public.js';
        $js_url = QRCS_PLUGIN_URL . 'public/js/public.js';
        $js_version = file_exists($js_file) ? filemtime($js_file) : QRCS_VERSION;
        
        wp_enqueue_script(
            'qrcs-public',
            $js_url,
            ['jquery'],
            $js_version,
            true
        );
        
        // Frontend CSS
        wp_register_style('qrcs-public', false);
        wp_enqueue_style('qrcs-public');
        
        $css = "
            .qr-code-steel-container {
                display: inline-block;
                max-width: 100%;
            }
            .qr-code-steel {
                display: block;
                max-width: 100%;
                height: auto;
            }
            .qr-code-steel.alignleft {
                float: left;
                margin-right: 20px;
                margin-bottom: 20px;
            }
            .qr-code-steel.alignright {
                float: right;
                margin-left: 20px;
                margin-bottom: 20px;
            }
            .qr-code-steel.aligncenter {
                display: block;
                margin-left: auto;
                margin-right: auto;
                margin-bottom: 20px;
            }
            .qr-code-shortcode-copy {
                margin-top: 10px;
                display: flex;
                gap: 5px;
                background: #f8f9fa;
                padding: 10px;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            .qr-code-shortcode-input {
                flex: 1;
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-family: monospace;
                font-size: 12px;
            }
            .qr-code-copy-button {
                padding: 5px 10px;
                background: #0073aa;
                color: #fff;
                border: none;
                border-radius: 3px;
                cursor: pointer;
            }
            .qr-code-copy-button:hover {
                background: #005a87;
            }
        ";
        
        wp_add_inline_style('qrcs-public', $css);
    }
}