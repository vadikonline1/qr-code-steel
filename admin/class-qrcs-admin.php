<?php
/**
 * Admin Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class QRCS_Admin {
    
    private static $instance = null;
    private $database;
    private $ajax_handler;
    
    public static function get_instance($database, $ajax_handler) {
        if (null === self::$instance) {
            self::$instance = new self($database, $ajax_handler);
        }
        return self::$instance;
    }
    
    private function __construct($database, $ajax_handler) {
        $this->database = $database;
        $this->ajax_handler = $ajax_handler;
        
        add_action('admin_menu', [$this, 'add_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Add admin menus
     */
    public function add_menus() {
        add_menu_page(
            __('QR Code Steel', 'qr-code-steel'),
            __('QR Code Steel', 'qr-code-steel'),
            'manage_options',
            'qr-code-steel',
            [$this, 'render_list_page'],
            'dashicons-format-aside',
            30
        );
        
        add_submenu_page(
            'qr-code-steel',
            __('QR Codes', 'qr-code-steel'),
            __('QR Codes', 'qr-code-steel'),
            'manage_options',
            'qr-code-steel',
            [$this, 'render_list_page']
        );
        
        add_submenu_page(
            'qr-code-steel',
            __('Add New', 'qr-code-steel'),
            __('Add New', 'qr-code-steel'),
            'manage_options',
            'qr-code-steel-add',
            [$this, 'render_edit_page']
        );
        
        add_submenu_page(
            'qr-code-steel',
            __('Settings', 'qr-code-steel'),
            __('Settings', 'qr-code-steel'),
            'manage_options',
            'qr-code-steel-settings',
            [$this, 'render_settings_page']
        );
    }
    
/**
 * Render list page
 */
public function render_list_page() {
    $list_table = new QRCS_List_Table($this->database);
    $list_table->process_bulk_action();
    
    include QRCS_PLUGIN_DIR . 'admin/views/list-page.php';
}
    
    /**
     * Render edit page
     */
    public function render_edit_page() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        include QRCS_PLUGIN_DIR . 'admin/views/edit-page.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['submit']) && check_admin_referer('qrcs_save_settings')) {
            $this->database->save_settings($_POST);
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'qr-code-steel') . '</p></div>';
        }
        
        $settings = $this->database->get_settings();
        include QRCS_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'qr-code-steel') === false) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('jquery-ui-style', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        // Admin JS
        $js_file = QRCS_PLUGIN_DIR . 'admin/js/admin.js';
        $js_url = QRCS_PLUGIN_URL . 'admin/js/admin.js';
        $js_version = file_exists($js_file) ? filemtime($js_file) : QRCS_VERSION;
        
        wp_enqueue_script(
            'qrcs-admin',
            $js_url,
            ['jquery', 'jquery-ui-datepicker', 'jquery-ui-dialog', 'wp-color-picker'],
            $js_version,
            true
        );
        
        wp_localize_script('qrcs-admin', 'qrcs_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_nonce' => wp_create_nonce('qrcs_admin_ajax'),
            'save_nonce' => wp_create_nonce('qrcs_save_qr_code'),
            'pages' => $this->get_pages(),
            'posts' => $this->get_posts(),
            'strings' => [
                'confirm_delete' => __('Are you sure?', 'qr-code-steel'),
                'copied' => __('Copied!', 'qr-code-steel'),
                'loading' => __('Loading...', 'qr-code-steel'),
                'regenerate_confirm' => __('Regenerate image?', 'qr-code-steel')
            ]
        ]);
        
        // Admin CSS
        $css_file = QRCS_PLUGIN_DIR . 'admin/css/admin.css';
        $css_url = QRCS_PLUGIN_URL . 'admin/css/admin.css';
        $css_version = file_exists($css_file) ? filemtime($css_file) : QRCS_VERSION;
        
        wp_enqueue_style(
            'qrcs-admin',
            $css_url,
            ['wp-color-picker'],
            $css_version
        );
    }
    
    /**
     * Get pages list
     */
    private function get_pages() {
        $pages = get_pages();
        $options = [];
        
        foreach ($pages as $page) {
            $options[] = [
                'id' => $page->ID,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID)
            ];
        }
        
        return $options;
    }
    
    /**
     * Get posts list
     */
    private function get_posts() {
        $posts = get_posts(['numberposts' => -1, 'post_status' => 'publish']);
        $options = [];
        
        foreach ($posts as $post) {
            $options[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID)
            ];
        }
        
        return $options;
    }
}