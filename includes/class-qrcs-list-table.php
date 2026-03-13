<?php
/**
 * QR Code List Table Class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class QRCS_List_Table extends WP_List_Table {
    
    private $table_name;
    private $database;
    
    public function __construct($table_name) {
        $this->table_name = $table_name;
        $this->database = QRCS_Database::get_instance();
        
        parent::__construct(array(
            'singular' => 'qr_code',
            'plural' => 'qr_codes',
            'ajax' => false
        ));
    }
    
    /**
     * Get columns
     */
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'qr-code-steel'),
            'description' => __('Description', 'qr-code-steel'),
            'qr_actions' => __('QR Code', 'qr-code-steel'),
            'date_range' => __('Active Period', 'qr-code-steel'),
            'status' => __('Status', 'qr-code-steel'),
            'scan_count' => __('Scans', 'qr-code-steel'),
            'created_at' => __('Created', 'qr-code-steel')
        );
        
        return $columns;
    }
    
    /**
     * Get sortable columns
     */
    protected function get_sortable_columns() {
        return array(
            'id' => array('id', false),
            'description' => array('description', false),
            'scan_count' => array('scan_count', false),
            'created_at' => array('created_at', true),
            'status' => array('disabled', false)
        );
    }
    
    /**
     * Get bulk actions
     */
    protected function get_bulk_actions() {
        return array(
            'delete' => __('Delete', 'qr-code-steel'),
            'enable' => __('Enable', 'qr-code-steel'),
            'disable' => __('Disable', 'qr-code-steel'),
            'reset_scans' => __('Reset Scan Count', 'qr-code-steel')
        );
    }
    
    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        $action = $this->current_action();
        
        if (empty($action)) {
            return;
        }
        
        $ids = isset($_POST['qr_codes']) ? array_map('intval', $_POST['qr_codes']) : array();
        
        if (empty($ids)) {
            return;
        }
        
        $result = $this->database->bulk_action($action, $ids);
        
        if ($result) {
            echo '<div class="notice notice-success"><p>' . 
                 sprintf(__('Bulk action completed. %d items affected.', 'qr-code-steel'), count($ids)) . 
                 '</p></div>';
        }
    }
    
    /**
     * Prepare items
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Get sorting
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        
        // Get search
        $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        
        // Get status filter
        $status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        
        // Get items
        $result = $this->database->get_qr_codes(array(
            'per_page' => $per_page,
            'page' => $current_page,
            'orderby' => $orderby,
            'order' => $order,
            'search' => $search,
            'status' => $status
        ));
        
        $this->items = $result['items'];
        
        $this->set_pagination_args(array(
            'total_items' => $result['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($result['total'] / $per_page)
        ));
    }
    
    /**
     * Default column render
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $this->column_id($item);
            
            case 'description':
                return esc_html($item['description'] ?: '-');
            
            case 'scan_count':
                return number_format(intval($item['scan_count']));
            
            case 'created_at':
                return date_i18n(get_option('date_format'), strtotime($item['created_at']));
            
            case 'qr_actions':
                return '<div class="qrcs-qr-actions">' .
                       '<button type="button" class="button qrcs-copy-shortcode" data-uuid="' . $item['uuid'] . '">' . __('Copy', 'qr-code-steel') . '</button>' .
                       '<button type="button" class="button qrcs-show-qr" data-uuid="' . $item['uuid'] . '">' . __('Show', 'qr-code-steel') . '</button>' .
                       '<a href="' . admin_url('admin-ajax.php?action=qrcs_download_qr_image&uuid=' . $item['uuid'] . '&nonce=' . wp_create_nonce('qrcs_admin_ajax')) . '" class="button qrcs-download-qr" download>' . __('Download', 'qr-code-steel') . '</a>' .
                       '</div>';
            
            case 'date_range':
                return $this->format_date_range($item);
            
            case 'status':
                return $this->get_status_badge($item);
            
            default:
                return print_r($item, true);
        }
    }
    
    /**
     * Column checkbox
     */
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="qr_codes[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Column ID with actions
     */
    protected function column_id($item) {
        $qr_url = $this->get_qr_url($item['uuid']);
        
        $actions = array(
            'edit' => sprintf(
                '<a href="?page=qr-code-steel-add&id=%s">%s</a>',
                $item['id'],
                __('Edit', 'qr-code-steel')
            ),
            'delete' => sprintf(
                '<a href="#" class="qrcs-delete-qr" data-id="%s">%s</a>',
                $item['id'],
                __('Delete', 'qr-code-steel')
            ),
            'qr_url' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                $qr_url,
                __('View QR URL', 'qr-code-steel')
            )
        );
        
        return sprintf(
            '<strong>%d</strong> %s',
            $item['id'],
            $this->row_actions($actions)
        );
    }
    
    /**
     * Get QR URL
     */
    private function get_qr_url($uuid) {
        return home_url('/?qr=' . $uuid . '/');
    }
    
    /**
     * Get status badge
     */
    private function get_status_badge($item) {
        if ($item['disabled']) {
            return '<span class="qrcs-status disabled">' . __('Disabled', 'qr-code-steel') . '</span>';
        }
        
        $now = current_time('mysql');
        
        if (!empty($item['active_from']) && $now < $item['active_from']) {
            return '<span class="qrcs-status scheduled">' . __('Scheduled', 'qr-code-steel') . '</span>';
        }
        
        if (!empty($item['active_to']) && $now > $item['active_to']) {
            return '<span class="qrcs-status expired">' . __('Expired', 'qr-code-steel') . '</span>';
        }
        
        if ($item['max_scans'] && $item['scan_count'] >= $item['max_scans']) {
            return '<span class="qrcs-status limit-reached">' . __('Limit Reached', 'qr-code-steel') . '</span>';
        }
        
        return '<span class="qrcs-status active">' . __('Active', 'qr-code-steel') . '</span>';
    }
    
    /**
     * Format date range
     */
    private function format_date_range($item) {
        $from = !empty($item['active_from']) ? date_i18n(get_option('date_format'), strtotime($item['active_from'])) : '-';
        $to = !empty($item['active_to']) ? date_i18n(get_option('date_format'), strtotime($item['active_to'])) : '-';
        
        return $from . ' → ' . $to;
    }
    
    /**
     * Extra controls in top and bottom
     */
    protected function extra_tablenav($which) {
        if ($which == 'top') {
            ?>
            <div class="alignleft actions">
                <select name="filter_status">
                    <option value=""><?php _e('All statuses', 'qr-code-steel'); ?></option>
                    <option value="active" <?php selected(isset($_GET['filter_status']) && $_GET['filter_status'] == 'active'); ?>>
                        <?php _e('Active', 'qr-code-steel'); ?>
                    </option>
                    <option value="disabled" <?php selected(isset($_GET['filter_status']) && $_GET['filter_status'] == 'disabled'); ?>>
                        <?php _e('Disabled', 'qr-code-steel'); ?>
                    </option>
                    <option value="scheduled" <?php selected(isset($_GET['filter_status']) && $_GET['filter_status'] == 'scheduled'); ?>>
                        <?php _e('Scheduled', 'qr-code-steel'); ?>
                    </option>
                    <option value="expired" <?php selected(isset($_GET['filter_status']) && $_GET['filter_status'] == 'expired'); ?>>
                        <?php _e('Expired', 'qr-code-steel'); ?>
                    </option>
                </select>
                <?php submit_button(__('Filter', 'qr-code-steel'), 'button', 'filter_action', false); ?>
            </div>
            <?php
        }
    }
    
    /**
     * Message for no items
     */
    public function no_items() {
        _e('No QR codes found.', 'qr-code-steel');
    }
}