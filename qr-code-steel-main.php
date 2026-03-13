<?php
/**
 * Plugin Name: QR Code by Steel..xD
 * Plugin URI: https://github.com/vadikonline1/
 * Description: Allows you to create DYNAMIC QR CODES: you can modify what happens when scanning your QR code without actually modifying (and reprinting) the QR code.
 * Version: 0.0.1
 * Author: Steel..xD
 * Author URI: https://github.com/vadikonline1/
 * GitHub Username: vadikonline1
 * GitHub Repository: qr-code-steel
 * License: GPL2
 * Text Domain: qr-code-steel
 * Requires Plugins: github-plugin-manager-main
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('QRCS_VERSION', '0.0.1');
define('QRCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QRCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QRCS_TABLE', 'qrcs_qr_codes');

// Debug mode - controlat din setări, default false
define('QRCS_DEBUG', get_option('qrcs_debug_mode', false));

// Include required files
require_once QRCS_PLUGIN_DIR . 'includes/class-qrcs-database.php';
require_once QRCS_PLUGIN_DIR . 'includes/class-qrcs-image-handler.php';
require_once QRCS_PLUGIN_DIR . 'includes/class-qrcs-redirect-handler.php';
require_once QRCS_PLUGIN_DIR . 'includes/class-qrcs-shortcode.php';
require_once QRCS_PLUGIN_DIR . 'includes/class-qrcs-ajax-handler.php';
require_once QRCS_PLUGIN_DIR . 'includes/class-qrcs-list-table.php';
require_once QRCS_PLUGIN_DIR . 'admin/class-qrcs-admin.php';
require_once QRCS_PLUGIN_DIR . 'public/class-qrcs-public.php';

/**
 * Main plugin class
 */
final class QR_Code_Steel {
    
    private static $instance = null;
    
    // Class instances
    public $database;
    public $image;
    public $redirect;
    public $shortcode;
    public $ajax;
    public $admin;
    public $public;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        $this->database = QRCS_Database::get_instance();
        $this->image = QRCS_Image_Handler::get_instance($this->database);
        $this->redirect = QRCS_Redirect_Handler::get_instance($this->database);
        $this->shortcode = QRCS_Shortcode::get_instance($this->database, $this->image);
        $this->ajax = QRCS_Ajax_Handler::get_instance($this->database, $this->image);
        $this->admin = QRCS_Admin::get_instance($this->database, $this->ajax);
        $this->public = QRCS_Public::get_instance($this->shortcode);
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this->redirect, 'add_rewrite_rules']);
        add_filter('query_vars', [$this->redirect, 'add_query_vars']);
        add_action('template_redirect', [$this->redirect, 'handle_redirect'], 1);
        add_action('init', [$this->image, 'serve_image']);
    }
    
    public function activate() {
        $this->database->create_tables();
        $this->database->set_default_options();
        $this->redirect->add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function init() {
        load_plugin_textdomain('qr-code-steel', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Initialize plugin
QR_Code_Steel::get_instance();