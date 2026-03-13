jQuery(document).ready(function($) {
    'use strict';
    
    // Copy shortcode functionality for frontend
    $('.qr-code-copy-button').on('click', function() {
        var shortcode = $(this).data('shortcode');
        var $button = $(this);
        
        // Create temporary input
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(shortcode).select();
        document.execCommand('copy');
        $temp.remove();
        
        // Show feedback
        var originalText = $button.text();
        $button.text('Copied!');
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
});