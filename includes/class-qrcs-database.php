<?php
/**
 * QR Code Steel Database Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class QRCS_Database {
    
    private static $instance = null;
    private $table_name;
    private $wpdb;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . QRCS_TABLE;
    }
    
    /**
     * Get last database error
     */
    public function get_last_error() {
        return $this->wpdb->last_error;
    }
    
    /**
     * Generate UUID v4
     */
    public function generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            uuid varchar(36) NOT NULL,
            description text,
            redirect_url text,
            active_from datetime DEFAULT NULL,
            active_to datetime DEFAULT NULL,
            url_before text,
            url_after text,
            priority int(11) DEFAULT 0,
            max_scans int(11) DEFAULT NULL,
            url_max_scans text,
            foreground_color varchar(7) DEFAULT '#000000',
            background_color varchar(7) DEFAULT '#FFFFFF',
            disabled tinyint(1) DEFAULT 0,
            scan_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY idx_priority (priority),
            KEY idx_disabled (disabled),
            KEY idx_dates (active_from, active_to),
            KEY idx_uuid_lookup (uuid, disabled)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default options
     */
    public function set_default_options() {
        $defaults = array(
            'qrcs_default_foreground' => '#000000',
            'qrcs_default_background' => '#FFFFFF',
            'qrcs_qr_size' => 200,
            'qrcs_error_correction' => 'M',
            'qrcs_qr_base_url' => home_url('/')
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Get settings
     */
    public function get_settings() {
        return array(
            'default_foreground' => get_option('qrcs_default_foreground', '#000000'),
            'default_background' => get_option('qrcs_default_background', '#FFFFFF'),
            'qr_size' => get_option('qrcs_qr_size', 200),
            'error_correction' => get_option('qrcs_error_correction', 'M'),
            'qr_base_url' => get_option('qrcs_qr_base_url', home_url('/'))
        );
    }
    
    /**
     * Save settings
     */
    public function save_settings($data) {
        update_option('qrcs_default_foreground', sanitize_hex_color($data['default_foreground']));
        update_option('qrcs_default_background', sanitize_hex_color($data['default_background']));
        update_option('qrcs_qr_size', intval($data['qr_size']));
        update_option('qrcs_error_correction', sanitize_text_field($data['error_correction']));
        update_option('qrcs_qr_base_url', esc_url_raw($data['qr_base_url']));
    }
    
    /**
     * Get QR code by ID
     */
    public function get_qr_code($id) {
        if (!$id) {
            return $this->get_empty_qr_data();
        }
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ), ARRAY_A);
    }
    
    /**
     * Get QR code by UUID
     */
    public function get_qr_code_by_uuid($uuid) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE uuid = %s",
            $uuid
        ), ARRAY_A);
    }
    
    /**
     * Get empty QR data for new codes
     */
    private function get_empty_qr_data() {
        return array(
            'id' => 0,
            'uuid' => '',
            'description' => '',
            'redirect_url' => '',
            'active_from' => '',
            'active_to' => '',
            'url_before' => '',
            'url_after' => '',
            'priority' => 0,
            'max_scans' => '',
            'url_max_scans' => '',
            'foreground_color' => get_option('qrcs_default_foreground', '#000000'),
            'background_color' => get_option('qrcs_default_background', '#FFFFFF'),
            'disabled' => 0,
            'scan_count' => 0
        );
    }
    
    /**
     * Save QR code
     */
    public function save_qr_code($id, $data) {
        if ($id) {
            $result = $this->wpdb->update(
                $this->table_name,
                $data,
                array('id' => $id)
            );
            return $result !== false ? $id : false;
        } else {
            $result = $this->wpdb->insert(
                $this->table_name,
                $data
            );
            return $result ? $this->wpdb->insert_id : false;
        }
    }
    
    /**
     * Delete QR code
     */
    public function delete_qr_code($id) {
        return $this->wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Increment scan count
     */
    public function increment_scan_count($id) {
        return $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name} SET scan_count = scan_count + 1 WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Bulk actions
     */
    public function bulk_action($action, $ids) {
        if (empty($ids)) {
            return false;
        }
        
        $ids_str = implode(',', array_map('intval', $ids));
        
        switch ($action) {
            case 'delete':
                return $this->wpdb->query("DELETE FROM {$this->table_name} WHERE id IN ({$ids_str})");
            
            case 'enable':
                return $this->wpdb->query("UPDATE {$this->table_name} SET disabled = 0 WHERE id IN ({$ids_str})");
            
            case 'disable':
                return $this->wpdb->query("UPDATE {$this->table_name} SET disabled = 1 WHERE id IN ({$ids_str})");
            
            case 'reset_scans':
                return $this->wpdb->query("UPDATE {$this->table_name} SET scan_count = 0 WHERE id IN ({$ids_str})");
            
            default:
                return false;
        }
    }
    
    /**
     * Get QR codes for list table
     */
    public function get_qr_codes($args = array()) {
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'status' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare(
                "(description LIKE %s OR uuid LIKE %s)",
                $search,
                $search
            );
        }
        
        if ($args['status'] === 'active') {
            $where[] = "disabled = 0";
        } elseif ($args['status'] === 'disabled') {
            $where[] = "disabled = 1";
        }
        
        $where_clause = implode(' AND ', $where);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE {$where_clause} 
                ORDER BY {$orderby} 
                LIMIT %d OFFSET %d",
                $args['per_page'],
                $offset
            ),
            ARRAY_A
        );
        
        $total = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}"
        );
        
        return array(
            'items' => $results,
            'total' => $total
        );
    }
}