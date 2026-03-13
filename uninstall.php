<?php
/**
 * Uninstall script
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
$options = [
    'qrcs_default_foreground',
    'qrcs_default_background',
    'qrcs_qr_size',
    'qrcs_error_correction',
    'qrcs_db_storage',
    'qrcs_flush_rewrite_rules'
];

foreach ($options as $option) {
    delete_option($option);
}

// Drop custom table
global $wpdb;
$table = $wpdb->prefix . 'qrcs_qr_codes';
$wpdb->query("DROP TABLE IF EXISTS {$table}");