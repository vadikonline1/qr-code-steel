<div class="wrap">
    <h1><?php _e('QR Code Settings', 'qr-code-steel'); ?></h1>
    
    <form method="post">
        <?php wp_nonce_field('qrcs_save_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="default_foreground"><?php _e('Default Foreground Color', 'qr-code-steel'); ?></label>
                </th>
                <td>
                    <input type="text" name="default_foreground" id="default_foreground" 
                           value="<?php echo esc_attr($settings['default_foreground']); ?>" class="qrcs-color-picker">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="default_background"><?php _e('Default Background Color', 'qr-code-steel'); ?></label>
                </th>
                <td>
                    <input type="text" name="default_background" id="default_background" 
                           value="<?php echo esc_attr($settings['default_background']); ?>" class="qrcs-color-picker">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="qr_size"><?php _e('QR Code Size', 'qr-code-steel'); ?></label>
                </th>
                <td>
                    <input type="number" name="qr_size" id="qr_size" 
                           value="<?php echo esc_attr($settings['qr_size']); ?>" min="100" max="1000" step="10">
                    <p class="description"><?php _e('Default size in pixels', 'qr-code-steel'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="error_correction"><?php _e('Error Correction', 'qr-code-steel'); ?></label>
                </th>
                <td>
                    <select name="error_correction" id="error_correction">
                        <option value="L" <?php selected($settings['error_correction'], 'L'); ?>>Low (7%)</option>
                        <option value="M" <?php selected($settings['error_correction'], 'M'); ?>>Medium (15%)</option>
                        <option value="Q" <?php selected($settings['error_correction'], 'Q'); ?>>Quartile (25%)</option>
                        <option value="H" <?php selected($settings['error_correction'], 'H'); ?>>High (30%)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="db_storage"><?php _e('Store in Database', 'qr-code-steel'); ?></label>
                </th>
                <td>
                    <input type="checkbox" name="db_storage" id="db_storage" 
                           value="1" <?php checked($settings['db_storage'], 1); ?>>
                    <p class="description">
                        <?php _e('Store QR images in database. Recommended for better performance.', 'qr-code-steel'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="debug_mode"><?php _e('Debug Mode', 'qr-code-steel'); ?></label>
                </th>
                <td>
                    <input type="checkbox" name="debug_mode" id="debug_mode" 
                           value="1" <?php checked($settings['debug_mode'], 1); ?>>
                    <p class="description">
                        <?php _e('Enable debug logging to wp-content/debug.log. Only enable for troubleshooting.', 'qr-code-steel'); ?>
                        <?php if (QRCS_DEBUG): ?>
                            <br><strong style="color:green">✓ Debug mode is currently ACTIVE</strong>
                        <?php else: ?>
                            <br><strong style="color:gray">○ Debug mode is currently inactive</strong>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" class="button-primary" 
                   value="<?php _e('Save Changes', 'qr-code-steel'); ?>">
        </p>
    </form>
</div>