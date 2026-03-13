<?php
/**
 * AJAX Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class QRCS_Ajax_Handler {
    
    private static $instance = null;
    private $database;
    private $image_handler;
    
    public static function get_instance($database, $image_handler) {
        if (null === self::$instance) {
            self::$instance = new self($database, $image_handler);
        }
        return self::$instance;
    }
    
    private function __construct($database, $image_handler) {
        $this->database = $database;
        $this->image_handler = $image_handler;
        
        // Register AJAX handlers
        $actions = [
            'get_qr_code_data',
            'save_qr_code',
            'delete_qr_code',
            'bulk_action',
            'generate_qr_image',
            'download_qr_image',
            'regenerate_image'
        ];
        
        foreach ($actions as $action) {
            add_action('wp_ajax_qrcs_' . $action, [$this, 'handle_' . $action]);
        }
    }
    
    /**
     * Verify nonce and permissions
     */
    private function verify_request($nonce_action = 'qrcs_admin_ajax') {
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'qr-code-steel')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'qr-code-steel')]);
        }
        
        return true;
    }
    
    /**
     * Get QR code data
     */
    public function handle_get_qr_code_data() {
        $this->verify_request();
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $data = $this->database->get_qr_code($id);
        
        if ($data) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(['message' => __('QR code not found', 'qr-code-steel')]);
        }
    }
    
    /**
     * Save QR code
     */
    public function handle_save_qr_code() {
        $this->verify_request('qrcs_save_qr_code');
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        $data = [
            'description' => sanitize_textarea_field($_POST['description']),
            'redirect_url' => esc_url_raw($_POST['redirect_url']),
            'active_from' => !empty($_POST['active_from']) ? $this->format_date($_POST['active_from']) : null,
            'active_to' => !empty($_POST['active_to']) ? $this->format_date($_POST['active_to']) : null,
            'url_before' => esc_url_raw($_POST['url_before']),
            'url_after' => esc_url_raw($_POST['url_after']),
            'priority' => intval($_POST['priority']),
            'max_scans' => !empty($_POST['max_scans']) ? intval($_POST['max_scans']) : null,
            'url_max_scans' => esc_url_raw($_POST['url_max_scans']),
            'foreground_color' => sanitize_hex_color($_POST['foreground_color']),
            'background_color' => sanitize_hex_color($_POST['background_color']),
            'disabled' => isset($_POST['disabled']) ? 1 : 0
        ];
        
        if (!$id) {
            $data['uuid'] = $this->database->generate_uuid();
        }
        
        $result = $this->database->save_qr_code($id, $data);
        
        if ($result !== false) {
            // Generate image if DB storage is enabled
            if (get_option('qrcs_db_storage', true)) {
                $uuid = $id ? $this->database->get_qr_code($result)['uuid'] : $data['uuid'];
                $this->image_handler->generate_and_store($result, $uuid, get_option('qrcs_qr_size', 300));
            }
            
            wp_send_json_success([
                'id' => $result,
                'message' => __('QR code saved successfully.', 'qr-code-steel')
            ]);
        } else {
            wp_send_json_error(['message' => __('Error saving QR code.', 'qr-code-steel')]);
        }
    }
    
    /**
     * Delete QR code
     */
    public function handle_delete_qr_code() {
        $this->verify_request();
        
        $id = intval($_POST['id']);
        $result = $this->database->delete_qr_code($id);
        
        if ($result) {
            wp_send_json_success(['message' => __('QR code deleted successfully.', 'qr-code-steel')]);
        } else {
            wp_send_json_error(['message' => __('Error deleting QR code.', 'qr-code-steel')]);
        }
    }
    
    /**
     * Bulk action
     */
    public function handle_bulk_action() {
        $this->verify_request();
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $ids = array_map('intval', $_POST['ids']);
        
        $result = $this->database->bulk_action($action, $ids);
        
        if ($result) {
            wp_send_json_success(['message' => __('Bulk action completed successfully.', 'qr-code-steel')]);
        } else {
            wp_send_json_error(['message' => __('Error performing bulk action.', 'qr-code-steel')]);
        }
    }
    
    /**
     * Generate QR image
     */
    public function handle_generate_qr_image() {
        $this->verify_request();
        
        $uuid = sanitize_text_field($_POST['uuid']);
        $size = isset($_POST['size']) ? intval($_POST['size']) : 300;
        
        $qr = $this->database->get_qr_code_by_uuid($uuid);
        
        if (!$qr) {
            wp_send_json_error(['message' => 'QR code not found']);
        }
        
        if (get_option('qrcs_db_storage', true)) {
            $image = $this->database->get_qr_image($qr['id']);
            
            if (!$image || empty($image['qr_image'])) {
                $this->image_handler->generate_and_store($qr['id'], $uuid, $size);
            }
            
            $image_url = add_query_arg([
                'qrcs_image' => '1',
                'uuid' => $uuid
            ], home_url('/'));
        } else {
            $qr_url = home_url('/?qr=' . $uuid . '/');
            $image_url = $this->image_handler->get_external_url(
                $qr_url,
                $size,
                $qr['foreground_color'],
                $qr['background_color']
            );
        }
        
        wp_send_json_success([
            'image_url' => $image_url,
            'uuid' => $uuid
        ]);
    }
    
    /**
     * Download QR image
     */
    public function handle_download_qr_image() {
        if (!check_ajax_referer('qrcs_admin_ajax', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        $uuid = sanitize_text_field($_GET['uuid']);
        $qr = $this->database->get_qr_code_by_uuid($uuid);
        
        if (!$qr) {
            wp_die('QR code not found');
        }
        
        if (get_option('qrcs_db_storage', true)) {
            $image = $this->database->get_qr_image($qr['id']);
            
            if ($image && !empty($image['qr_image'])) {
                header('Content-Type: ' . $image['qr_image_mime']);
                header('Content-Disposition: attachment; filename="qr-' . substr($uuid, 0, 8) . '.png"');
                header('Content-Length: ' . strlen($image['qr_image']));
                echo $image['qr_image'];
                exit;
            }
        }
        
        // Fallback to external API
        $qr_url = home_url('/?qr=' . $uuid . '/');
        $size = isset($_GET['size']) ? intval($_GET['size']) : 300;
        $api_url = $this->image_handler->get_external_url(
            $qr_url,
            $size,
            $qr['foreground_color'],
            $qr['background_color']
        );
        
        $response = wp_remote_get($api_url, ['timeout' => 30]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            wp_die('Error generating QR code');
        }
        
        $image_data = wp_remote_retrieve_body($response);
        
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="qr-' . substr($uuid, 0, 8) . '.png"');
        header('Content-Length: ' . strlen($image_data));
        echo $image_data;
        exit;
    }
    
    /**
     * Regenerate image
     */
    public function handle_regenerate_image() {
        $this->verify_request();
        
        $uuid = sanitize_text_field($_POST['uuid']);
        $size = isset($_POST['size']) ? intval($_POST['size']) : 300;
        
        $qr = $this->database->get_qr_code_by_uuid($uuid);
        
        if (!$qr) {
            wp_send_json_error(['message' => 'QR code not found']);
        }
        
        // Delete old image and generate new
        $this->database->delete_qr_image($qr['id']);
        $result = $this->image_handler->generate_and_store($qr['id'], $uuid, $size);
        
        if ($result) {
            $image_url = add_query_arg([
                'qrcs_image' => '1',
                'uuid' => $uuid
            ], home_url('/'));
            
            wp_send_json_success([
                'image_url' => $image_url,
                'message' => __('QR image regenerated successfully.', 'qr-code-steel')
            ]);
        } else {
            wp_send_json_error(['message' => __('Error regenerating QR image.', 'qr-code-steel')]);
        }
    }
    
    /**
     * Format date for database
     */
    private function format_date($date) {
        $timestamp = strtotime(str_replace('/', '-', $date));
        return date('Y-m-d H:i:s', $timestamp);
    }
}