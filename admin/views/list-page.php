<?php
/**
 * List page view
 */

// $list_table este definit în render_list_page() din QRCS_Admin
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('QR Codes', 'qr-code-steel'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=qr-code-steel-add'); ?>" class="page-title-action">
        <?php _e('Add New', 'qr-code-steel'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <form method="post">
        <?php
        // Folosim variabila $list_table care este definită în clasa admin
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
                <button id="qrcs-regenerate-qr" class="button">
                    <?php _e('Regenerate', 'qr-code-steel'); ?>
                </button>
            </div>
        </div>
    </div>
</div>