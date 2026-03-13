<?php
/**
 * Shortcode Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class QRCS_Shortcode {
    
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
        
        add_shortcode('qr_code_steel', [$this, 'render']);
    }
    
    /**
     * Render shortcode
     */
    public function render($atts) {
        $atts = shortcode_atts([
            'uuid' => '',
            'size' => get_option('qrcs_qr_size', 200),
            'class' => '',
            'align' => 'none',
            'show_copy' => false
        ], $atts, 'qr_code_steel');
        
        if (empty($atts['uuid'])) {
            return '<!-- QR Code UUID is required -->';
        }
        
        $qr = $this->database->get_qr_code_by_uuid($atts['uuid']);
        
        if (!$qr) {
            return '<!-- QR Code not found -->';
        }
        
        // Get image URL
        if (get_option('qrcs_db_storage', true)) {
            $image_url = $this->image_handler->get_image_url($qr['uuid'], $atts['size']);
        } else {
            $qr_url = home_url('/?qr=' . $qr['uuid'] . '/');
            $image_url = $this->image_handler->get_external_url(
                $qr_url,
                $atts['size'],
                $qr['foreground_color'],
                $qr['background_color']
            );
        }
        
        $class = esc_attr('qr-code-steel ' . $atts['class'] . ' align' . $atts['align']);
        $width = intval($atts['size']);
        
        $output = '<div class="qr-code-steel-container">';
        $output .= sprintf(
            '<img src="%s" alt="QR Code" class="%s" width="%d" height="%d" loading="lazy">',
            esc_url($image_url),
            $class,
            $width,
            $width
        );
        
        // Show copy button only for admins
        if ($atts['show_copy'] && current_user_can('manage_options')) {
            $shortcode = '[qr_code_steel uuid="' . $qr['uuid'] . '"]';
            $output .= '<div class="qr-code-shortcode-copy">';
            $output .= '<input type="text" value="' . esc_attr($shortcode) . '" readonly class="qr-code-shortcode-input">';
            $output .= '<button type="button" class="qr-code-copy-button" data-shortcode="' . esc_attr($shortcode) . '">' . __('Copy', 'qr-code-steel') . '</button>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
}