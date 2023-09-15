/* global wp */

jQuery(document).ready(($) => {
    'use strict';

    /**
     * Check all upload sections for uploaded files
     */
    $('code.uploaded-file-url').each((index, element) => {
        if ($(element).html().trim() !== '') {
            $(element).css('display', 'inline-block');
        }
    });

    $('img.uploaded-image').each((index, element) => {
        if ($(element).attr('src').trim() !== '') {
            $(element).css('display', 'block');
        }
    });

    // Upload attachment
    $('.upload, .image img, .url code').click((event) => {
        event.preventDefault();

        const target = $(event.target);
        const sendAttachmentBkp = wp.media.editor.send.attachment;
        const dataID = target.data('field-id');

        wp.media.editor.send.attachment = (props, attachment) => {
            const current = '[data-id="' + dataID + '"]';

            if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
                $(current + ' .image img').attr('src', attachment.sizes.thumbnail.url);
                $(current + ' .image img').css('display', 'block');
            }

            $(current + ' .url code').html(attachment.url).show();
            $(current + ' .attachment_id').val(attachment.id);
            $(current + ' .remove').show();
            $(current + ' .upload').hide();

            wp.media.editor.send.attachment = sendAttachmentBkp;
        };

        wp.media.editor.open();

        return false;
    });

    // Remove attachment
    $('.remove').click((event) => {
        event.preventDefault();

        const target = $(event.target);
        const dataID = target.parent().attr('data-id');
        const current = '[data-id="' + dataID + '"]';

        $(current + ' .url code').html('').hide();
        $(current + ' .attachment_id').val('');
        $(current + ' .image img').attr('src', '');
        $(current + ' .image img').css('display', 'none');
        $(current + ' .remove').hide();
        $(current + ' .upload').show();
    });

    // Add color picker to fields
    const elementColorPicker = $('.colorpicker');
    if (elementColorPicker.length) {
        elementColorPicker.wpColorPicker();
    }

    // Nav click toggle
    const elementNavTab = $('.nav-tab');
    if (elementNavTab.length) {
        elementNavTab.click((event) => {
            event.preventDefault();

            const target = $(event.target);
            const id = target.attr('href').substring(1);

            $('.tab-content').hide();
            $('#' + id).show();

            $('.nav-tab').removeClass('nav-tab-active');
            target.addClass('nav-tab-active');
        });
    }
});
