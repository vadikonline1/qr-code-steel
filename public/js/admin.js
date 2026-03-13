jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize color pickers
    if ($.fn.wpColorPicker) {
        $('.qrcs-color-picker').wpColorPicker();
    }
    
    // Initialize date pickers
    if ($.fn.datepicker) {
        $('.qrcs-datepicker').datepicker({
            dateFormat: 'dd/mm/yy',
            changeMonth: true,
            changeYear: true,
            yearRange: '-5:+5'
        });
    }
    
    // Copy shortcode
    $(document).on('click', '.qrcs-copy-shortcode', function() {
        var uuid = $(this).data('uuid');
        var shortcode = '[qr_code_steel uuid="' + uuid + '"]';
        var $button = $(this);
        
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(shortcode).select();
        document.execCommand('copy');
        $temp.remove();
        
        var originalText = $button.text();
        $button.text(qrcs_admin.strings.copied);
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
    
    // Show QR modal
    $(document).on('click', '.qrcs-show-qr', function() {
        var uuid = $(this).data('uuid');
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(qrcs_admin.strings.loading);
        
        $.ajax({
            url: qrcs_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qrcs_generate_qr_image',
                uuid: uuid,
                size: 300,
                nonce: qrcs_admin.admin_nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#qrcs-modal-image').html('<img src="' + response.data.image_url + '">');
                    $('#qrcs-download-qr').data('uuid', uuid);
                    $('#qrcs-regenerate-qr').data('uuid', uuid);
                    $('#qrcs-qr-modal').show();
                }
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Regenerate image
    $('#qrcs-regenerate-qr').on('click', function() {
        if (!confirm(qrcs_admin.strings.regenerate_confirm)) {
            return;
        }
        
        var uuid = $(this).data('uuid');
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(qrcs_admin.strings.loading);
        
        $.ajax({
            url: qrcs_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qrcs_regenerate_image',
                uuid: uuid,
                size: 300,
                nonce: qrcs_admin.admin_nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#qrcs-modal-image').html('<img src="' + response.data.image_url + '">');
                }
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Close modal
    $('.qrcs-modal-close').on('click', function() {
        $('#qrcs-qr-modal').hide();
    });
    
    // Download from modal
    $('#qrcs-download-qr').on('click', function() {
        var uuid = $(this).data('uuid');
        window.location.href = qrcs_admin.ajax_url + '?action=qrcs_download_qr_image&uuid=' + uuid + '&nonce=' + qrcs_admin.admin_nonce;
    });
    
    // Load edit form
    if ($('#qrcs-edit-form').length) {
        var qrId = $('input[name="id"]').val();
        
        if (qrId > 0) {
            loadQRCodeData(qrId);
        } else {
            renderEditForm({
                id: 0,
                uuid: '',
                description: '',
                redirect_url: '',
                active_from: '',
                active_to: '',
                url_before: '',
                url_after: '',
                priority: 0,
                max_scans: '',
                url_max_scans: '',
                foreground_color: '#000000',
                background_color: '#FFFFFF',
                disabled: 0
            });
        }
    }
    
    function loadQRCodeData(id) {
        $('.qrcs-loading').show();
        
        $.ajax({
            url: qrcs_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qrcs_get_qr_code_data',
                id: id,
                nonce: qrcs_admin.admin_nonce
            },
            success: function(response) {
                if (response.success) {
                    renderEditForm(response.data);
                }
            },
            complete: function() {
                $('.qrcs-loading').hide();
            }
        });
    }
    
    function renderEditForm(data) {
        var html = '<table class="form-table">';
        
        if (data.id > 0) {
            html += '<tr><th>ID</th><td><strong>' + data.id + '</strong></td></tr>';
            html += '<tr><th>UUID</th><td><code>' + data.uuid + '</code></td></tr>';
        }
        
        html += '<tr>';
        html += '<th><label for="description">Description</label></th>';
        html += '<td><textarea name="description" id="description" rows="3" class="large-text">' + escapeHtml(data.description) + '</textarea></td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="redirect_url">Redirect URL</label></th>';
        html += '<td>' + renderUrlField('redirect_url', data.redirect_url) + '</td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="active_from">Active from</label></th>';
        html += '<td><input type="text" name="active_from" id="active_from" value="' + formatDate(data.active_from) + '" class="qrcs-datepicker"></td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="active_to">Active to</label></th>';
        html += '<td><input type="text" name="active_to" id="active_to" value="' + formatDate(data.active_to) + '" class="qrcs-datepicker"></td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="url_before">URL before</label></th>';
        html += '<td>' + renderUrlField('url_before', data.url_before) + '</td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="url_after">URL after</label></th>';
        html += '<td>' + renderUrlField('url_after', data.url_after) + '</td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="priority">Priority</label></th>';
        html += '<td><input type="number" name="priority" id="priority" value="' + (data.priority || 0) + '" class="small-text"></td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="max_scans">Max scans</label></th>';
        html += '<td><input type="number" name="max_scans" id="max_scans" value="' + (data.max_scans || '') + '" class="small-text"></td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="url_max_scans">URL at limit</label></th>';
        html += '<td>' + renderUrlField('url_max_scans', data.url_max_scans) + '</td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="foreground_color">Foreground</label></th>';
        html += '<td><input type="text" name="foreground_color" id="foreground_color" value="' + data.foreground_color + '" class="qrcs-color-picker"></td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="background_color">Background</label></th>';
        html += '<td><input type="text" name="background_color" id="background_color" value="' + data.background_color + '" class="qrcs-color-picker"></td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="disabled">Disabled</label></th>';
        html += '<td><input type="checkbox" name="disabled" id="disabled" value="1" ' + (data.disabled ? 'checked' : '') + '> Disable this QR code</td>';
        html += '</tr>';
        
        html += '</table>';
        
        $('#qrcs-edit-form-container').html(html);
        
        // Reinitialize pickers
        if ($.fn.wpColorPicker) {
            $('.qrcs-color-picker').wpColorPicker();
        }
        
        if ($.fn.datepicker) {
            $('.qrcs-datepicker').datepicker({
                dateFormat: 'dd/mm/yy',
                changeMonth: true,
                changeYear: true,
                yearRange: '-5:+5'
            });
        }
        
        $('.url-type-select').each(function() {
            setupUrlSelect($(this));
        });
    }
    
    function renderUrlField(name, value) {
        var html = '<div class="url-field-container">';
        html += '<select id="' + name + '_type" class="url-type-select">';
        html += '<option value="custom">Custom</option>';
        html += '<option value="page">Page</option>';
        html += '<option value="post">Post</option>';
        html += '</select>';
        html += '<input type="url" name="' + name + '" id="' + name + '" value="' + escapeHtml(value) + '" class="regular-text">';
        
        html += '<select id="' + name + '_page" class="url-select" style="display:none;">';
        html += '<option value="">Select page</option>';
        $.each(qrcs_admin.pages, function(i, page) {
            html += '<option value="' + escapeHtml(page.url) + '">' + escapeHtml(page.title) + '</option>';
        });
        html += '</select>';
        
        html += '<select id="' + name + '_post" class="url-select" style="display:none;">';
        html += '<option value="">Select post</option>';
        $.each(qrcs_admin.posts, function(i, post) {
            html += '<option value="' + escapeHtml(post.url) + '">' + escapeHtml(post.title) + '</option>';
        });
        html += '</select>';
        
        html += '</div>';
        
        return html;
    }
    
    function setupUrlSelect($typeSelect) {
        var targetId = $typeSelect.attr('id').replace('_type', '');
        var $urlInput = $('#' + targetId);
        var $pageSelect = $('#' + targetId + '_page');
        var $postSelect = $('#' + targetId + '_post');
        
        if ($urlInput.val()) {
            if ($pageSelect.find('option[value="' + $urlInput.val() + '"]').length) {
                $typeSelect.val('page');
                $urlInput.hide();
                $pageSelect.show();
                $pageSelect.val($urlInput.val());
            } else if ($postSelect.find('option[value="' + $urlInput.val() + '"]').length) {
                $typeSelect.val('post');
                $urlInput.hide();
                $postSelect.show();
                $postSelect.val($urlInput.val());
            }
        }
        
        $typeSelect.on('change', function() {
            var type = $(this).val();
            
            $urlInput.hide();
            $pageSelect.hide();
            $postSelect.hide();
            
            if (type === 'custom') {
                $urlInput.show();
            } else if (type === 'page') {
                $pageSelect.show();
            } else if (type === 'post') {
                $postSelect.show();
            }
        });
        
        $pageSelect.on('change', function() {
            $urlInput.val($(this).val());
        });
        
        $postSelect.on('change', function() {
            $urlInput.val($(this).val());
        });
    }
    
    // Form submit
    $('#qrcs-edit-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'qrcs_save_qr_code'});
        formData.push({name: 'nonce', value: qrcs_admin.save_nonce});
        
        $.ajax({
            url: qrcs_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    window.location.href = 'admin.php?page=qr-code-steel';
                }
            }
        });
    });
    
    // Delete
    $(document).on('click', '.qrcs-delete-qr', function(e) {
        e.preventDefault();
        
        if (!confirm(qrcs_admin.strings.confirm_delete)) {
            return;
        }
        
        var id = $(this).data('id');
        
        $.ajax({
            url: qrcs_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qrcs_delete_qr_code',
                id: id,
                nonce: qrcs_admin.admin_nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });
    
    // Helper functions
    function formatDate(dateString) {
        if (!dateString) return '';
        var date = new Date(dateString);
        var dd = String(date.getDate()).padStart(2, '0');
        var mm = String(date.getMonth() + 1).padStart(2, '0');
        var yyyy = date.getFullYear();
        return dd + '/' + mm + '/' + yyyy;
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});