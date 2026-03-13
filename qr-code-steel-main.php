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
define('QRCS_DEBUG', true);

// Debug logging function - only for errors
function qrcs_debug_log($message, $data = null, $is_error = false) {
    if (!QRCS_DEBUG || !$is_error) {
        return;
    }
    
    $log_entry = '[QR Code Steel ERROR] ' . $message;
    if ($data !== null) {
        $log_entry .= ' | Data: ' . print_r($data, true);
    }
    error_log($log_entry);
}

// Include required files
require_once QRCS_PLUGIN_DIR . 'includes/class-qrcs-database.php';
require_once QRCS_PLUGIN_DIR . 'includes/class-qrcs-list-table.php';

/**
 * Main plugin class
 */
class QR_Code_Steel {
    
    private static $instance = null;
    private $database;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . QRCS_TABLE;
        $this->database = QRCS_Database::get_instance();
        
        // Hook into WordPress
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_qrcs_get_qr_code_data', array($this, 'ajax_get_qr_code_data'));
        add_action('wp_ajax_qrcs_save_qr_code', array($this, 'ajax_save_qr_code'));
        add_action('wp_ajax_qrcs_delete_qr_code', array($this, 'ajax_delete_qr_code'));
        add_action('wp_ajax_qrcs_bulk_action', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_qrcs_generate_qr_image', array($this, 'ajax_generate_qr_image'));
        add_action('wp_ajax_qrcs_download_qr_image', array($this, 'ajax_download_qr_image'));
        
        // QR code redirect handler
        add_action('init', array($this, 'handle_qr_redirect'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('wp_loaded', array($this, 'flush_rewrite_rules'));
        
        // Shortcode
        add_shortcode('qr_code_steel', array($this, 'render_qr_shortcode'));
        
        // Filter for query vars
        add_filter('query_vars', array($this, 'add_query_vars'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->database->create_tables();
        $this->database->set_default_options();
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Flush rewrite rules on wp_loaded
     */
    public function flush_rewrite_rules() {
        if (get_option('qrcs_flush_rewrite_rules', false)) {
            flush_rewrite_rules();
            delete_option('qrcs_flush_rewrite_rules');
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('qr-code-steel', false, basename(dirname(__FILE__)) . '/languages');
    }
    
    /**
     * Add rewrite rules
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^qr/([a-f0-9-]+)/?$',
            'index.php?qr=$matches[1]',
            'top'
        );
        
        // Add option to flush rules
        update_option('qrcs_flush_rewrite_rules', true);
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'qr';
        return $vars;
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        add_menu_page(
            __('QR Code Steel', 'qr-code-steel'),
            __('QR Code Steel', 'qr-code-steel'),
            'manage_options',
            'qr-code-steel',
            array($this, 'render_qr_list_page'),
            'dashicons-qrcode',
            30
        );
        
        add_submenu_page(
            'qr-code-steel',
            __('QR Codes', 'qr-code-steel'),
            __('QR Codes', 'qr-code-steel'),
            'manage_options',
            'qr-code-steel',
            array($this, 'render_qr_list_page')
        );
        
        add_submenu_page(
            'qr-code-steel',
            __('Add New QR Code', 'qr-code-steel'),
            __('Add New', 'qr-code-steel'),
            'manage_options',
            'qr-code-steel-add',
            array($this, 'render_qr_edit_page')
        );
        
        add_submenu_page(
            'qr-code-steel',
            __('Settings', 'qr-code-steel'),
            __('Settings', 'qr-code-steel'),
            'manage_options',
            'qr-code-steel-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render QR list page
     */
    public function render_qr_list_page() {
        $list_table = new QRCS_List_Table($this->table_name);
        $list_table->process_bulk_action();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('QR Codes', 'qr-code-steel'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=qr-code-steel-add'); ?>" class="page-title-action">
                <?php _e('Add New', 'qr-code-steel'); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <form method="post">
                <?php
                $list_table->prepare_items();
                $list_table->search_box(__('Search QR Codes', 'qr-code-steel'), 'qr-code-search');
                $list_table->display();
                ?>
            </form>
        </div>
        
        <!-- QR Code Modal -->
        <div id="qrcs-qr-modal" class="qrcs-modal" style="display:none;">
            <div class="qrcs-modal-content">
                <div class="qrcs-modal-header">
                    <h3><?php _e('QR Code', 'qr-code-steel'); ?></h3>
                    <span class="qrcs-modal-close">&times;</span>
                </div>
                <div class="qrcs-modal-body">
                    <div id="qrcs-modal-image" class="qrcs-qr-preview"></div>
                    <div class="qrcs-modal-actions">
                        <button id="qrcs-download-qr" class="button button-primary">
                            <?php _e('Download PNG', 'qr-code-steel'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render QR edit page
     */
    public function render_qr_edit_page() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        ?>
        <div class="wrap">
            <h1><?php echo $id ? __('Edit QR Code', 'qr-code-steel') : __('Add New QR Code', 'qr-code-steel'); ?></h1>
            
            <form id="qrcs-edit-form" method="post">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php wp_nonce_field('qrcs_save_qr_code', 'qrcs_nonce'); ?>
                
                <div id="qrcs-edit-form-container">
                    <div class="qrcs-loading"><?php _e('Loading...', 'qr-code-steel'); ?></div>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Save QR Code', 'qr-code-steel'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=qr-code-steel'); ?>" class="button">
                        <?php _e('Cancel', 'qr-code-steel'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
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
        ?>
        <div class="wrap">
            <h1><?php _e('QR Code Settings', 'qr-code-steel'); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('qrcs_save_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default_foreground"><?php _e('Default Foreground Color', 'qr-code-steel'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="default_foreground" id="default_foreground" 
                                   value="<?php echo esc_attr($settings['default_foreground']); ?>" class="qrcs-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_background"><?php _e('Default Background Color', 'qr-code-steel'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="default_background" id="default_background" 
                                   value="<?php echo esc_attr($settings['default_background']); ?>" class="qrcs-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="qr_size"><?php _e('QR Code Size (pixels)', 'qr-code-steel'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="qr_size" id="qr_size" 
                                   value="<?php echo esc_attr($settings['qr_size']); ?>" min="100" max="1000" step="10">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="error_correction"><?php _e('Error Correction Level', 'qr-code-steel'); ?></label>
                        </th>
                        <td>
                            <select name="error_correction" id="error_correction">
                                <option value="L" <?php selected($settings['error_correction'], 'L'); ?>><?php _e('Low (7%)', 'qr-code-steel'); ?></option>
                                <option value="M" <?php selected($settings['error_correction'], 'M'); ?>><?php _e('Medium (15%)', 'qr-code-steel'); ?></option>
                                <option value="Q" <?php selected($settings['error_correction'], 'Q'); ?>><?php _e('Quartile (25%)', 'qr-code-steel'); ?></option>
                                <option value="H" <?php selected($settings['error_correction'], 'H'); ?>><?php _e('High (30%)', 'qr-code-steel'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="qr_base_url"><?php _e('QR Code Base URL', 'qr-code-steel'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="qr_base_url" id="qr_base_url" 
                                   value="<?php echo esc_attr($settings['qr_base_url']); ?>" class="regular-text">
                            <p class="description"><?php _e('Base URL for QR code redirects (leave empty for site URL)', 'qr-code-steel'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" 
                           value="<?php _e('Save Changes', 'qr-code-steel'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX: Get QR code data
     */
    public function ajax_get_qr_code_data() {
        // Check nonce
        if (!check_ajax_referer('qrcs_admin_ajax', 'nonce', false)) {
            qrcs_debug_log('Nonce verification failed in get_qr_code_data', $_POST, true);
            wp_send_json_error(array('message' => __('Security check failed.', 'qr-code-steel')));
        }
        
        // Check capability
        if (!current_user_can('manage_options')) {
            qrcs_debug_log('Capability check failed in get_qr_code_data', get_current_user_id(), true);
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'qr-code-steel')));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $qr_data = $this->database->get_qr_code($id);
        
        if ($qr_data) {
            wp_send_json_success($qr_data);
        } else {
            qrcs_debug_log('QR code not found for ID: ' . $id, null, true);
            wp_send_json_error(array('message' => __('QR code not found', 'qr-code-steel')));
        }
    }
    
    /**
     * AJAX: Save QR code
     */
    public function ajax_save_qr_code() {
        // Check nonce
        if (!check_ajax_referer('qrcs_save_qr_code', 'nonce', false)) {
            qrcs_debug_log('Save nonce verification failed', $_POST, true);
            wp_send_json_error(array('message' => __('Security check failed.', 'qr-code-steel')));
        }
        
        // Check capability
        if (!current_user_can('manage_options')) {
            qrcs_debug_log('Save capability check failed', get_current_user_id(), true);
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'qr-code-steel')));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        // Validate and sanitize data
        $data = array(
            'description' => sanitize_textarea_field($_POST['description']),
            'redirect_url' => esc_url_raw($_POST['redirect_url']),
            'active_from' => !empty($_POST['active_from']) ? $this->format_date_for_db($_POST['active_from']) : null,
            'active_to' => !empty($_POST['active_to']) ? $this->format_date_for_db($_POST['active_to']) : null,
            'url_before' => esc_url_raw($_POST['url_before']),
            'url_after' => esc_url_raw($_POST['url_after']),
            'priority' => intval($_POST['priority']),
            'max_scans' => !empty($_POST['max_scans']) ? intval($_POST['max_scans']) : null,
            'url_max_scans' => esc_url_raw($_POST['url_max_scans']),
            'foreground_color' => sanitize_hex_color($_POST['foreground_color']),
            'background_color' => sanitize_hex_color($_POST['background_color']),
            'disabled' => isset($_POST['disabled']) ? 1 : 0
        );
        
        // Only set UUID for new QR codes
        if (!$id) {
            $data['uuid'] = $this->database->generate_uuid();
        }
        
        $result = $this->database->save_qr_code($id, $data);
        
        if ($result !== false) {
            // Set flag to flush rewrite rules
            update_option('qrcs_flush_rewrite_rules', true);
            
            wp_send_json_success(array(
                'id' => $result,
                'message' => __('QR code saved successfully.', 'qr-code-steel')
            ));
        } else {
            qrcs_debug_log('Error saving QR code', $this->database->get_last_error(), true);
            wp_send_json_error(array('message' => __('Error saving QR code.', 'qr-code-steel')));
        }
    }
    
    /**
     * AJAX: Delete QR code
     */
    public function ajax_delete_qr_code() {
        if (!check_ajax_referer('qrcs_admin_ajax', 'nonce', false)) {
            qrcs_debug_log('Delete nonce verification failed', $_POST, true);
            wp_send_json_error(array('message' => __('Security check failed.', 'qr-code-steel')));
        }
        
        if (!current_user_can('manage_options')) {
            qrcs_debug_log('Delete capability check failed', get_current_user_id(), true);
            wp_send_json_error(array('message' => __('Unauthorized', 'qr-code-steel')));
        }
        
        $id = intval($_POST['id']);
        $result = $this->database->delete_qr_code($id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('QR code deleted successfully.', 'qr-code-steel')));
        } else {
            qrcs_debug_log('Error deleting QR code ID: ' . $id, null, true);
            wp_send_json_error(array('message' => __('Error deleting QR code.', 'qr-code-steel')));
        }
    }
    
    /**
     * AJAX: Bulk actions
     */
    public function ajax_bulk_action() {
        if (!check_ajax_referer('qrcs_admin_ajax', 'nonce', false)) {
            qrcs_debug_log('Bulk nonce verification failed', $_POST, true);
            wp_send_json_error(array('message' => __('Security check failed.', 'qr-code-steel')));
        }
        
        if (!current_user_can('manage_options')) {
            qrcs_debug_log('Bulk capability check failed', get_current_user_id(), true);
            wp_send_json_error(array('message' => __('Unauthorized', 'qr-code-steel')));
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $ids = array_map('intval', $_POST['ids']);
        
        $result = $this->database->bulk_action($action, $ids);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Bulk action completed successfully.', 'qr-code-steel')));
        } else {
            qrcs_debug_log('Bulk action failed: ' . $action, $ids, true);
            wp_send_json_error(array('message' => __('Error performing bulk action.', 'qr-code-steel')));
        }
    }
    
    /**
     * AJAX: Generate QR image for preview/download
     */
    public function ajax_generate_qr_image() {
        if (!check_ajax_referer('qrcs_admin_ajax', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $uuid = sanitize_text_field($_POST['uuid']);
        $size = isset($_POST['size']) ? intval($_POST['size']) : 300;
        
        $qr = $this->database->get_qr_code_by_uuid($uuid);
        
        if (!$qr) {
            wp_send_json_error(array('message' => 'QR code not found'));
        }
        
        $qr_url = $this->get_qr_redirect_url($qr['uuid']);
        $image_url = $this->generate_qr_image_url($qr_url, $size, $qr['foreground_color'], $qr['background_color']);
        
        wp_send_json_success(array(
            'image_url' => $image_url,
            'qr_url' => $qr_url,
            'uuid' => $qr['uuid']
        ));
    }
    
    /**
     * AJAX: Download QR image
     */
    public function ajax_download_qr_image() {
        if (!check_ajax_referer('qrcs_admin_ajax', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        $uuid = sanitize_text_field($_GET['uuid']);
        $size = isset($_GET['size']) ? intval($_GET['size']) : 300;
        
        $qr = $this->database->get_qr_code_by_uuid($uuid);
        
        if (!$qr) {
            wp_die('QR code not found');
        }
        
        $qr_url = $this->get_qr_redirect_url($qr['uuid']);
        $image_url = $this->generate_qr_image_url($qr_url, $size, $qr['foreground_color'], $qr['background_color']);
        
        // Get image content
        $response = wp_remote_get($image_url, array('timeout' => 30));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            qrcs_debug_log('Error downloading QR image', $image_url, true);
            wp_die('Error generating QR code');
        }
        
        $image_data = wp_remote_retrieve_body($response);
        
        // Set headers for download
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="qr-code-' . substr($qr['uuid'], 0, 8) . '.png"');
        header('Content-Length: ' . strlen($image_data));
        
        echo $image_data;
        exit;
    }
    
    /**
     * Format date for database
     */
    private function format_date_for_db($date) {
        $timestamp = strtotime(str_replace('/', '-', $date));
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Handle QR code redirect
     */
    public function handle_qr_redirect() {
        $uuid = get_query_var('qr');
        
        if (empty($uuid) && isset($_GET['qr'])) {
            $uuid = sanitize_text_field($_GET['qr']);
            // Remove trailing slash if present
            $uuid = rtrim($uuid, '/');
        }
        
        if (empty($uuid)) {
            return;
        }
        
        $qr = $this->database->get_qr_code_by_uuid($uuid);
        
        if (!$qr || $qr['disabled']) {
            return;
        }
        
        // Increment scan count
        $this->database->increment_scan_count($qr['id']);
        
        // Determine redirect URL
        $redirect_url = $this->determine_redirect_url($qr);
        
        if ($redirect_url) {
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Get QR redirect URL
     */
    private function get_qr_redirect_url($uuid) {
        return home_url('/?qr=' . $uuid . '/');
    }
    
    /**
     * Determine redirect URL based on conditions
     */
    private function determine_redirect_url($qr) {
        $current_time = current_time('mysql');
        
        // Check max scans limit
        if ($qr['max_scans'] !== null && $qr['scan_count'] >= $qr['max_scans']) {
            return $qr['url_max_scans'];
        }
        
        // Check date conditions
        if ($qr['active_from'] && $current_time < $qr['active_from']) {
            return $qr['url_before'];
        }
        
        if ($qr['active_to'] && $current_time > $qr['active_to']) {
            return $qr['url_after'];
        }
        
        return $qr['redirect_url'];
    }
    
    /**
     * Render QR shortcode
     */
    public function render_qr_shortcode($atts) {
        $atts = shortcode_atts(array(
            'uuid' => '',
            'size' => get_option('qrcs_qr_size', 200),
            'class' => '',
            'align' => 'none',
            'show_copy' => true
        ), $atts, 'qr_code_steel');
        
        if (empty($atts['uuid'])) {
            return '<!-- QR Code UUID is required -->';
        }
        
        $qr = $this->database->get_qr_code_by_uuid($atts['uuid']);
        
        if (!$qr) {
            return '<!-- QR Code not found -->';
        }
        
        // Generate QR code URL
        $qr_url = $this->get_qr_redirect_url($qr['uuid']);
        
        // Generate QR image URL
        $image_url = $this->generate_qr_image_url(
            $qr_url, 
            $atts['size'], 
            $qr['foreground_color'], 
            $qr['background_color']
        );
        
        $class = esc_attr('qr-code-steel ' . $atts['class'] . ' align' . $atts['align']);
        $width = intval($atts['size']);
        $height = intval($atts['size']);
        
        $output = '<div class="qr-code-steel-container">';
        $output .= '<img src="' . $image_url . '" alt="QR Code" class="' . $class . '" width="' . $width . '" height="' . $height . '" loading="lazy">';
        
        if ($atts['show_copy']) {
            $shortcode_text = '[qr_code_steel uuid="' . $qr['uuid'] . '"]';
            $output .= '<div class="qr-code-shortcode-copy">';
            $output .= '<input type="text" value="' . esc_attr($shortcode_text) . '" readonly class="qr-code-shortcode-input">';
            $output .= '<button type="button" class="qr-code-copy-button" data-shortcode="' . esc_attr($shortcode_text) . '">' . __('Copy', 'qr-code-steel') . '</button>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    
    /**
     * Generate QR image URL
     */
    private function generate_qr_image_url($data, $size, $foreground = '#000000', $background = '#FFFFFF') {
        $error_correction = get_option('qrcs_error_correction', 'M');
        
        // Remove # from colors
        $fg = ltrim($foreground, '#');
        $bg = ltrim($background, '#');
        
        // Use QR Server API with colors
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . 
               '&data=' . urlencode($data) . 
               '&ecc=' . $error_correction .
               '&color=' . $fg .
               '&bgcolor=' . $bg;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'qr-code-steel') === false) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('jquery-ui-style', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        // Admin JS with file timestamp
        $js_file = QRCS_PLUGIN_DIR . 'assets/js/admin.js';
        $js_url = QRCS_PLUGIN_URL . 'assets/js/admin.js';
        $js_version = file_exists($js_file) ? filemtime($js_file) : QRCS_VERSION;
        
        wp_enqueue_script(
            'qrcs-admin',
            $js_url,
            array('jquery', 'jquery-ui-datepicker', 'jquery-ui-dialog', 'wp-color-picker'),
            $js_version,
            true
        );
        
        $admin_nonce = wp_create_nonce('qrcs_admin_ajax');
        $save_nonce = wp_create_nonce('qrcs_save_qr_code');
        
        wp_localize_script('qrcs-admin', 'qrcs_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_nonce' => $admin_nonce,
            'save_nonce' => $save_nonce,
            'pages' => $this->get_pages_list(),
            'posts' => $this->get_posts_list(),
            'user_id' => get_current_user_id(),
            'user_can' => current_user_can('manage_options'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this QR code?', 'qr-code-steel'),
                'delete_success' => __('QR code deleted successfully.', 'qr-code-steel'),
                'delete_error' => __('Error deleting QR code.', 'qr-code-steel'),
                'save_success' => __('QR code saved successfully.', 'qr-code-steel'),
                'save_error' => __('Error saving QR code.', 'qr-code-steel'),
                'loading' => __('Loading...', 'qr-code-steel'),
                'show_qr' => __('Show QR Code', 'qr-code-steel'),
                'download' => __('Download', 'qr-code-steel'),
                'copy_shortcode' => __('Copy Shortcode', 'qr-code-steel'),
                'copied' => __('Copied!', 'qr-code-steel')
            )
        ));
        
        // Admin CSS with file timestamp
        $css_file = QRCS_PLUGIN_DIR . 'assets/css/admin.css';
        $css_url = QRCS_PLUGIN_URL . 'assets/css/admin.css';
        $css_version = file_exists($css_file) ? filemtime($css_file) : QRCS_VERSION;
        
        wp_enqueue_style(
            'qrcs-admin',
            $css_url,
            array('wp-color-picker'),
            $css_version
        );
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        $js_file = QRCS_PLUGIN_DIR . 'assets/js/frontend.js';
        $js_url = QRCS_PLUGIN_URL . 'assets/js/frontend.js';
        $js_version = file_exists($js_file) ? filemtime($js_file) : QRCS_VERSION;
        
        wp_enqueue_script(
            'qrcs-frontend',
            $js_url,
            array('jquery'),
            $js_version,
            true
        );
        
        wp_localize_script('qrcs-frontend', 'qrcs_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('qrcs_frontend')
        ));
        
        // Frontend CSS with inline to avoid 403
        wp_register_style('qrcs-frontend', false);
        wp_enqueue_style('qrcs-frontend');
        
        $inline_css = "
            .qr-code-steel-container {
                display: inline-block;
                max-width: 100%;
                margin: 0 auto;
                padding: 10px;
                background: #fff;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .qr-code-steel {
                display: block;
                max-width: 100%;
                height: auto;
            }
            .qr-code-shortcode-copy {
                margin-top: 10px;
                display: flex;
                gap: 5px;
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
        ";
        
        wp_add_inline_style('qrcs-frontend', $inline_css);
    }
    
    /**
     * Get pages list for select dropdown
     */
    private function get_pages_list() {
        $pages = get_pages();
        $options = array();
        
        foreach ($pages as $page) {
            $options[] = array(
                'id' => $page->ID,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID)
            );
        }
        
        return $options;
    }
    
    /**
     * Get posts list for select dropdown
     */
    private function get_posts_list() {
        $posts = get_posts(array(
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        
        $options = array();
        
        foreach ($posts as $post) {
            $options[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID)
            );
        }
        
        return $options;
    }
}

// Initialize plugin
QR_Code_Steel::get_instance();