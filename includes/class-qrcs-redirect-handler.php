<?php
/**
 * Redirect Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class QRCS_Redirect_Handler {
    
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
     * Add rewrite rules
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^qr/([a-f0-9-]+)/?$',
            'index.php?qr=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'qr';
        return $vars;
    }
    
    /**
     * Handle redirect
     */
    public function handle_redirect() {
        // Inițializare variabilă
        $uuid = '';
        
        // 1. Încearcă din query vars (pentru URL-uri de tip /qr/uuid/)
        $uuid = get_query_var('qr');
        
        // 2. Dacă nu e în query vars, încearcă din GET (pentru ?qr=uuid/)
        if (empty($uuid) && isset($_GET['qr'])) {
            $uuid = $_GET['qr'];
        }
        
        // 3. Dacă tot e gol, ieșim
        if (empty($uuid)) {
            return;
        }
        
        // 4. Curăță UUID-ul - elimină slash-ul de la sfârșit DACĂ EXISTĂ
        $uuid = rtrim($uuid, '/');
        
        // 5. Validare UUID (opțional, dar recomandat)
        if (!preg_match('/^[a-f0-9-]+$/', $uuid)) {
            if (QRCS_DEBUG) {
                error_log('QR: Invalid UUID format: ' . $uuid);
            }
            return;
        }
        
        // Debug
        if (QRCS_DEBUG) {
            error_log('QR: Processing UUID: ' . $uuid);
        }
        
        // Caută în baza de date
        $qr = $this->database->get_qr_code_by_uuid($uuid);
        
        if (QRCS_DEBUG) {
            error_log('QR: Found in DB: ' . ($qr ? 'yes' : 'no'));
        }
        
        if (!$qr || $qr['disabled']) {
            if (QRCS_DEBUG) {
                error_log('QR: Not found or disabled');
            }
            return;
        }
        
        // Increment scan count
        $this->database->increment_scan_count($qr['id']);
        
        // Determină URL-ul de redirect
        $url = $this->determine_url($qr);
        
        if (QRCS_DEBUG) {
            error_log('QR: Redirecting to: ' . $url);
        }
        
        if ($url) {
            wp_redirect($url);
            exit;
        }
    }
    
    /**
     * Determine redirect URL based on conditions
     */
    private function determine_url($qr) {
        $now = current_time('mysql');
        
        // Check max scans
        if (!empty($qr['max_scans']) && $qr['scan_count'] >= $qr['max_scans']) {
            return $qr['url_max_scans'];
        }
        
        // Check date conditions
        if (!empty($qr['active_from']) && $now < $qr['active_from']) {
            return $qr['url_before'];
        }
        
        if (!empty($qr['active_to']) && $now > $qr['active_to']) {
            return $qr['url_after'];
        }
        
        return $qr['redirect_url'];
    }
    
    /**
     * Get QR redirect URL
     */
    public function get_redirect_url($uuid) {
        return home_url('/?qr=' . $uuid . '/');
    }
}