jQuery(document).ready(function($) {
    'use strict';
    
    // Copy shortcode functionality
    $('.qr-code-copy-button').on('click', function() {
        var shortcode = $(this).data('shortcode');
        var $button = $(this);
        
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(shortcode).select();
        document.execCommand('copy');
        $temp.remove();
        
        var originalText = $button.text();
        $button.text('Copied!');
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
});