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
            showButtonPanel: true,
            yearRange: '-5:+5'
        });
    }
    
    // Copy shortcode functionality
    $(document).on('click', '.qrcs-copy-shortcode', function() {
        var uuid = $(this).data('uuid');
        var shortcode = '[qr_code_steel uuid="' + uuid + '"]';
        var $button = $(this);
        
        // Create temporary input
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(shortcode).select();
        document.execCommand('copy');
        $temp.remove();
        
        // Show feedback
        var originalText = $button.text();
        $button.text(qrcs_admin.strings.copied);
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
    
    // Show QR code modal
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
                    $('#qrcs-modal-image').html('<img src="' + response.data.image_url + '" style="max-width: 100%; height: auto;">');
                    $('#qrcs-download-qr').data('uuid', uuid);
                    $('#qrcs-qr-modal').show();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('Failed to generate QR code');
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
    
    // Close modal on outside click
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('qrcs-modal')) {
            $('#qrcs-qr-modal').hide();
        }
    });
    
    // Load edit form data
    if ($('#qrcs-edit-form').length) {
        var qrId = $('input[name="id"]').val();
        
        if (qrId > 0) {
            loadQRCodeData(qrId);
        } else {
            loadEmptyForm();
        }
    }
    
    function loadQRCodeData(id) {
        showLoading();
        
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
                } else {
                    showError(response.data.message || 'Failed to load QR code');
                }
            },
            error: function(xhr, status, error) {
                showError('Failed to load QR code data: ' + error);
            },
            complete: function() {
                hideLoading();
            }
        });
    }
    
    function loadEmptyForm() {
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
    
    function renderEditForm(data) {
        var html = '<table class="form-table">';
        
        if (data.id > 0) {
            html += '<tr>';
            html += '<th><label>ID</label></th>';
            html += '<td><strong>' + data.id + '</strong></td>';
            html += '</tr>';
            
            html += '<tr>';
            html += '<th><label>UUID</label></th>';
            html += '<td><code>' + data.uuid + '</code></td>';
            html += '</tr>';
        }
        
        // Description
        html += '<tr>';
        html += '<th><label for="description">Description</label></th>';
        html += '<td>';
        html += '<textarea name="description" id="description" rows="3" class="large-text">' + escapeHtml(data.description || '') + '</textarea>';
        html += '<p class="description">Enter a description for internal reference</p>';
        html += '</td>';
        html += '</tr>';
        
        // Redirect URL
        html += '<tr>';
        html += '<th><label for="redirect_url">Redirect URL</label></th>';
        html += '<td>';
        html += renderUrlField('redirect_url', data.redirect_url);
        html += '<p class="description">The main URL where users will be redirected</p>';
        html += '</td>';
        html += '</tr>';
        
        // Active from/to dates
        html += '<tr>';
        html += '<th><label for="active_from">Active from date</label></th>';
        html += '<td>';
        html += '<input type="text" name="active_from" id="active_from" value="' + (data.active_from ? formatDateForInput(data.active_from) : '') + '" class="qrcs-datepicker">';
        html += '</td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="active_to">Active to date</label></th>';
        html += '<td>';
        html += '<input type="text" name="active_to" id="active_to" value="' + (data.active_to ? formatDateForInput(data.active_to) : '') + '" class="qrcs-datepicker">';
        html += '</td>';
        html += '</tr>';
        
        // URL before activation
        html += '<tr>';
        html += '<th><label for="url_before">URL before activation</label></th>';
        html += '<td>';
        html += renderUrlField('url_before', data.url_before);
        html += '<p class="description">URL to redirect to before the activation date</p>';
        html += '</td>';
        html += '</tr>';
        
        // URL after expiration
        html += '<tr>';
        html += '<th><label for="url_after">URL after expiration</label></th>';
        html += '<td>';
        html += renderUrlField('url_after', data.url_after);
        html += '<p class="description">URL to redirect to after the expiration date</p>';
        html += '</td>';
        html += '</tr>';
        
        // Priority
        html += '<tr>';
        html += '<th><label for="priority">Priority</label></th>';
        html += '<td>';
        html += '<input type="number" name="priority" id="priority" value="' + (data.priority || 0) + '" class="small-text" min="0" max="999">';
        html += '<p class="description">Higher priority QR codes will be used first</p>';
        html += '</td>';
        html += '</tr>';
        
        // Max scans
        html += '<tr>';
        html += '<th><label for="max_scans">Max total scans</label></th>';
        html += '<td>';
        html += '<input type="number" name="max_scans" id="max_scans" value="' + (data.max_scans || '') + '" class="small-text" min="1">';
        html += '<p class="description">Leave empty for unlimited scans</p>';
        html += '</td>';
        html += '</tr>';
        
        // URL for max scans limit
        html += '<tr>';
        html += '<th><label for="url_max_scans">URL for max scans limit</label></th>';
        html += '<td>';
        html += renderUrlField('url_max_scans', data.url_max_scans);
        html += '<p class="description">URL to redirect to when max scans limit is reached</p>';
        html += '</td>';
        html += '</tr>';
        
        // Colors
        html += '<tr>';
        html += '<th><label for="foreground_color">Foreground color</label></th>';
        html += '<td><input type="text" name="foreground_color" id="foreground_color" value="' + data.foreground_color + '" class="qrcs-color-picker"></td>';
        html += '</tr>';
        
        html += '<tr>';
        html += '<th><label for="background_color">Background color</label></th>';
        html += '<td><input type="text" name="background_color" id="background_color" value="' + data.background_color + '" class="qrcs-color-picker"></td>';
        html += '</tr>';
        
        // Disabled
        html += '<tr>';
        html += '<th><label for="disabled">Disabled</label></th>';
        html += '<td>';
        html += '<input type="checkbox" name="disabled" id="disabled" value="1" ' + (data.disabled ? '' : '') + '>';
        html += '<label for="disabled">Disable this QR code (no redirects)</label>';
        html += '</td>';
        html += '</tr>';
        
        html += '</table>';
        
        $('#qrcs-edit-form-container').html(html);
        
        // Reinitialize color pickers
        if ($.fn.wpColorPicker) {
            $('.qrcs-color-picker').wpColorPicker();
        }
        
        // Reinitialize date pickers
        if ($.fn.datepicker) {
            $('.qrcs-datepicker').datepicker({
                dateFormat: 'dd/mm/yy',
                changeMonth: true,
                changeYear: true,
                showButtonPanel: true,
                yearRange: '-5:+5'
            });
        }
        
        // Initialize URL type selects
        $('.url-type-select').each(function() {
            setupUrlSelect($(this));
        });
    }
    
    function renderUrlField(name, value) {
        var html = '<div class="url-field-container">';
        html += '<select id="' + name + '_type" class="url-type-select">';
        html += '<option value="custom">Custom URL</option>';
        html += '<option value="page">Page</option>';
        html += '<option value="post">Post</option>';
        html += '</select>';
        html += '<input type="url" name="' + name + '" id="' + name + '" value="' + escapeHtml(value || '') + '" class="regular-text" placeholder="https://example.com">';
        
        // Page select
        html += '<select id="' + name + '_page" class="url-select" style="display:none;">';
        html += '<option value="">Select a page</option>';
        $.each(qrcs_admin.pages, function(i, page) {
            html += '<option value="' + escapeHtml(page.url) + '">' + escapeHtml(page.title) + '</option>';
        });
        html += '</select>';
        
        // Post select
        html += '<select id="' + name + '_post" class="url-select" style="display:none;">';
        html += '<option value="">Select a post</option>';
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
        
        // Set initial type based on URL value
        if ($urlInput.val()) {
            if ($pageSelect.find('option[value="' + $urlInput.val() + '"]').length) {
                $typeSelect.val('page');
                $urlInput.hide();
                $pageSelect.show();
                $postSelect.hide();
                $pageSelect.val($urlInput.val());
            } else if ($postSelect.find('option[value="' + $urlInput.val() + '"]').length) {
                $typeSelect.val('post');
                $urlInput.hide();
                $pageSelect.hide();
                $postSelect.show();
                $postSelect.val($urlInput.val());
            }
        }
        
        $typeSelect.off('change').on('change', function() {
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
        
        $pageSelect.off('change').on('change', function() {
            $urlInput.val($(this).val());
        });
        
        $postSelect.off('change').on('change', function() {
            $urlInput.val($(this).val());
        });
    }
    
    // Handle form submission
    $('#qrcs-edit-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serializeArray();
        formData.push({
            name: 'action',
            value: 'qrcs_save_qr_code'
        });
        formData.push({
            name: 'nonce',
            value: qrcs_admin.save_nonce
        });
        
        showLoading();
        
        $.ajax({
            url: qrcs_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    setTimeout(function() {
                        window.location.href = 'admin.php?page=qr-code-steel';
                    }, 1500);
                } else {
                    showError(response.data.message || 'Failed to save QR code');
                }
            },
            error: function(xhr, status, error) {
                showError('Failed to save QR code: ' + error);
            },
            complete: function() {
                hideLoading();
            }
        });
    });
    
    // Handle delete
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
                    showSuccess(response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showError(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                showError('Delete failed: ' + error);
            }
        });
    });
    
    // Helper functions
    function formatDateForInput(dateString) {
        var date = new Date(dateString);
        var dd = String(date.getDate()).padStart(2, '0');
        var mm = String(date.getMonth() + 1).padStart(2, '0');
        var yyyy = date.getFullYear();
        return dd + '/' + mm + '/' + yyyy;
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function showLoading() {
        $('.qrcs-loading').show();
    }
    
    function hideLoading() {
        $('.qrcs-loading').hide();
    }
    
    function showSuccess(message) {
        var notice = '<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>';
        $('.wrap h1').after(notice);
        setTimeout(function() {
            $('.notice-success').fadeOut();
        }, 3000);
    }
    
    function showError(message) {
        var notice = '<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>';
        $('.wrap h1').after(notice);
    }
});