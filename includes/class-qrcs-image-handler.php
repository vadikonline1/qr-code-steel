<?php
/**
 * Image Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class QRCS_Image_Handler {
    
    private static $instance = null;
    private $database;
    
    public static function get_instance($database) {
        if (null === self::$instance) {
            self::$instance = new self($database);
        }
        return self::$instance;
    }
    
    private function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * Generate QR image URL
     */
    public function get_image_url($uuid, $size = 200) {
        $qr = $this->database->get_qr_code_by_uuid($uuid);
        
        if (!$qr) {
            return false;
        }
        
        // Check if image exists in DB
        $db_image = $this->database->get_qr_image($qr['id']);
        
        if (!$db_image || empty($db_image['qr_image'])) {
            // Generate and store
            $this->generate_and_store($qr['id'], $uuid, $size);
        }
        
        return add_query_arg([
            'qrcs_image' => '1',
            'uuid' => $uuid
        ], home_url('/'));
    }
    
    /**
     * Generate and store QR image
     */
    public function generate_and_store($id, $uuid, $size = 300) {
        $qr = $this->database->get_qr_code($id);
        if (!$qr) {
            return false;
        }
        
        $qr_url = home_url('/?qr=' . $uuid . '/');
        $api_url = $this->build_api_url($qr_url, $size, $qr['foreground_color'], $qr['background_color']);
        
        $response = wp_remote_get($api_url, ['timeout' => 30]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            if (QRCS_DEBUG) {
                error_log('QR Image: Generation failed for UUID: ' . $uuid);
            }
            return false;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        return $this->database->update_qr_image($id, $image_data);
    }
    
    /**
     * Build API URL
     */
    private function build_api_url($data, $size, $foreground, $background) {
        $error_correction = get_option('qrcs_error_correction', 'M');
        $fg = ltrim($foreground, '#');
        $bg = ltrim($background, '#');
        
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => $size . 'x' . $size,
            'data' => $data,
            'ecc' => $error_correction,
            'color' => $fg,
            'bgcolor' => $bg
        ]);
    }
    
    /**
     * Serve image from database
     */
    public function serve_image() {
        if (!isset($_GET['qrcs_image']) || !isset($_GET['uuid'])) {
            return;
        }
        
        $uuid = sanitize_text_field($_GET['uuid']);
        $uuid = rtrim($uuid, '/');
        
        if (QRCS_DEBUG) {
            error_log('QR Image: Serving image for UUID: ' . $uuid);
        }
        
        $image = $this->database->get_qr_image_by_uuid($uuid);
        
        if (!$image || empty($image['qr_image'])) {
            if (QRCS_DEBUG) {
                error_log('QR Image: Not found for UUID: ' . $uuid);
            }
            wp_die('Image not found', 404);
        }
        
        // Set headers
        header('Content-Type: ' . $image['qr_image_mime']);
        header('Content-Length: ' . strlen($image['qr_image']));
        header('Cache-Control: public, max-age=31536000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        echo $image['qr_image'];
        exit;
    }
    
    /**
     * Generate external URL (fallback)
     */
    public function get_external_url($data, $size, $foreground, $background) {
        return $this->build_api_url($data, $size, $foreground, $background);
    }
}