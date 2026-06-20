/**
 * Virastai Auto Content Rewrite Metabox Scripts
 */
jQuery(document).ready(function ($) {

    // --- Regenerate Buttons ---
    $('.virastai-acr-regen-btn').on('click', function(e) {
        e.preventDefault();

        const btn = $(this);
        const type = btn.data('type'); // 'content' or 'title'
        const container = $('#virastai-acr-metabox-container');
        const loader = container.find('.virastai-acr-loader');

        // Disable UI
        btn.prop('disabled', true);
        loader.fadeIn();

        $.ajax({
            url: virastaiMetabox.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_regenerate_sandbox',
                nonce: virastaiMetabox.nonce,
                post_id: virastaiMetabox.postId,
                regen_type: type
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;

                    // Hide empty message
                    container.find('.virastai-acr-empty-msg').hide();
                    container.find('.virastai-acr-sandbox-fields').show();

                    // Update fields
                    if (data.title) {
                        $('#virastai_acr_sandbox_title_field').val(data.title);
                        container.find('.virastai-acr-title-group').show();
                    }
                    if (data.content) {
                        $('#virastai_acr_sandbox_content_field').val(data.content);
                        container.find('.virastai-acr-content-group').show();
                    }
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Request failed. Please check console.');
            },
            complete: function() {
                btn.prop('disabled', false);
                loader.hide();
            }
        });
    });

    // --- Clear Button ---
    $('.virastai-acr-clear-btn').on('click', function(e) {
        e.preventDefault();

        if (!confirm(virastaiMetabox.confirmClear)) {
            return;
        }

        const btn = $(this);
        const container = $('#virastai-acr-metabox-container');

        btn.prop('disabled', true);

        $.ajax({
            url: virastaiMetabox.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_clear_sandbox',
                nonce: virastaiMetabox.nonce,
                post_id: virastaiMetabox.postId
            },
            success: function(response) {
                if (response.success) {
                    $('#virastai_acr_sandbox_title_field').val('');
                    $('#virastai_acr_sandbox_content_field').val('');
                    container.find('.virastai-acr-sandbox-fields').hide();
                    container.find('.virastai-acr-empty-msg').show();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

});