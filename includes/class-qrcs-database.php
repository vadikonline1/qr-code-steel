<?php
/**
 * Database Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class QRCS_Database {
    
    private static $instance = null;
    private $wpdb;
    private $table;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . QRCS_TABLE;
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
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
            qr_image longblob,
            qr_image_mime varchar(100) DEFAULT 'image/png',
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
        $defaults = [
            'qrcs_default_foreground' => '#000000',
            'qrcs_default_background' => '#FFFFFF',
            'qrcs_qr_size' => 200,
            'qrcs_error_correction' => 'M',
            'qrcs_db_storage' => true,
            'qrcs_debug_mode' => false // Default false
        ];
        
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
        return [
            'default_foreground' => get_option('qrcs_default_foreground', '#000000'),
            'default_background' => get_option('qrcs_default_background', '#FFFFFF'),
            'qr_size' => get_option('qrcs_qr_size', 200),
            'error_correction' => get_option('qrcs_error_correction', 'M'),
            'db_storage' => get_option('qrcs_db_storage', true),
            'debug_mode' => get_option('qrcs_debug_mode', false)
        ];
    }
    
    /**
     * Save settings
     */
    public function save_settings($data) {
        update_option('qrcs_default_foreground', sanitize_hex_color($data['default_foreground']));
        update_option('qrcs_default_background', sanitize_hex_color($data['default_background']));
        update_option('qrcs_qr_size', intval($data['qr_size']));
        update_option('qrcs_error_correction', sanitize_text_field($data['error_correction']));
        update_option('qrcs_db_storage', isset($data['db_storage']) ? 1 : 0);
        update_option('qrcs_debug_mode', isset($data['debug_mode']) ? 1 : 0);
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
     * Get QR code by ID
     */
    public function get_qr_code($id) {
        if (!$id) {
            return $this->get_empty_qr_data();
        }
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
    }
    
    /**
     * Get QR code by UUID
     */
    public function get_qr_code_by_uuid($uuid) {
        if (empty($uuid)) {
            if (QRCS_DEBUG) {
                error_log('DB: Empty UUID provided');
            }
            return false;
        }
        
        // Elimină orice slash-uri care ar putea fi rămase
        $uuid = rtrim($uuid, '/');
        
        if (QRCS_DEBUG) {
            error_log('DB: Searching for UUID: ' . $uuid);
        }
        
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE uuid = %s", $uuid),
            ARRAY_A
        );
        
        if (QRCS_DEBUG) {
            error_log('DB: Query: ' . $this->wpdb->last_query);
            error_log('DB: Found: ' . ($result ? 'yes' : 'no'));
        }
        
        return $result;
    }
    
    /**
     * Get empty QR data
     */
    private function get_empty_qr_data() {
        return [
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
        ];
    }
    
    /**
     * Save QR code
     */
    public function save_qr_code($id, $data) {
        if ($id) {
            $result = $this->wpdb->update($this->table, $data, ['id' => $id]);
            return $result !== false ? $id : false;
        } else {
            $result = $this->wpdb->insert($this->table, $data);
            return $result ? $this->wpdb->insert_id : false;
        }
    }
    
    /**
     * Delete QR code
     */
    public function delete_qr_code($id) {
        return $this->wpdb->delete($this->table, ['id' => $id], ['%d']);
    }
    
    /**
     * Increment scan count
     */
    public function increment_scan_count($id) {
        return $this->wpdb->query(
            $this->wpdb->prepare("UPDATE {$this->table} SET scan_count = scan_count + 1 WHERE id = %d", $id)
        );
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
                return $this->wpdb->query("DELETE FROM {$this->table} WHERE id IN ({$ids_str})");
            case 'enable':
                return $this->wpdb->query("UPDATE {$this->table} SET disabled = 0 WHERE id IN ({$ids_str})");
            case 'disable':
                return $this->wpdb->query("UPDATE {$this->table} SET disabled = 1 WHERE id IN ({$ids_str})");
            case 'reset_scans':
                return $this->wpdb->query("UPDATE {$this->table} SET scan_count = 0 WHERE id IN ({$ids_str})");
            default:
                return false;
        }
    }
    
    /**
     * Get QR codes for list table
     */
    public function get_qr_codes($args = []) {
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'status' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['1=1'];
        
        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare("(description LIKE %s OR uuid LIKE %s)", $search, $search);
        }
        
        if ($args['status'] === 'active') {
            $where[] = "disabled = 0";
        } elseif ($args['status'] === 'disabled') {
            $where[] = "disabled = 1";
        }
        
        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Ensure orderby is sanitized
        $orderby = in_array($args['orderby'], ['id', 'description', 'scan_count', 'created_at']) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, uuid, description, redirect_url, active_from, active_to, 
                        url_before, url_after, priority, max_scans, url_max_scans,
                        foreground_color, background_color, disabled, scan_count,
                        created_at, updated_at
                 FROM {$this->table} 
                 WHERE {$where_clause} 
                 ORDER BY {$orderby} {$order} 
                 LIMIT %d OFFSET %d",
                $args['per_page'],
                $offset
            ),
            ARRAY_A
        );
        
        $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE {$where_clause}");
        
        return [
            'items' => $results ?: [],
            'total' => intval($total)
        ];
    }
    
    /**
     * Get QR image data
     */
    public function get_qr_image($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT qr_image, qr_image_mime FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
    }
    
    /**
     * Get QR image by UUID
     */
    public function get_qr_image_by_uuid($uuid) {
        if (empty($uuid)) {
            return false;
        }
        
        $uuid = rtrim($uuid, '/');
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT qr_image, qr_image_mime FROM {$this->table} WHERE uuid = %s", $uuid),
            ARRAY_A
        );
    }
    
    /**
     * Update QR image
     */
    public function update_qr_image($id, $image_data, $mime = 'image/png') {
        return $this->wpdb->update(
            $this->table,
            [
                'qr_image' => $image_data,
                'qr_image_mime' => $mime
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Delete QR image
     */
    public function delete_qr_image($id) {
        return $this->wpdb->update(
            $this->table,
            [
                'qr_image' => null,
                'qr_image_mime' => null
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Get last error
     */
    public function get_last_error() {
        return $this->wpdb->last_error;
    }
}