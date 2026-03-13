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