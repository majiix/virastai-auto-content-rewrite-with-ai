/**
 * Virastai Auto Content Rewrite Metabox Scripts
 */
jQuery(document).ready(function ($) {

    // Helper to check if the post has unsaved changes or is a new unsaved post
    function virastaiAcrIsPostUnsaved() {
        // 1. Check Gutenberg Block Editor
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            const editorSelect = wp.data.select('core/editor');
            
            // Check if post is dirty (has unsaved edits)
            if (typeof editorSelect.isEditedPostDirty === 'function' && editorSelect.isEditedPostDirty()) {
                return true;
            }
            
            // Check if post has a new/auto-draft status (i.e. not saved to DB yet)
            if (typeof editorSelect.getCurrentPostStatus === 'function') {
                const status = editorSelect.getCurrentPostStatus();
                if (status === 'auto-draft') {
                    return true;
                }
            }
        }

        // 2. Check Classic Editor
        // Check if TinyMCE editor content has unsaved changes
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
            if (typeof tinyMCE.get('content').isDirty === 'function' && tinyMCE.get('content').isDirty()) {
                return true;
            }
        }
        
        // Check if the hidden post_status field is set to 'auto-draft'
        const postStatusInput = $('#post_status');
        if (postStatusInput.length && postStatusInput.val() === 'auto-draft') {
            return true;
        }
        
        // Monitor standard title/content fields compared to their original values
        const titleField = $('#title');
        if (titleField.length) {
            const originalTitle = titleField.prop('defaultValue');
            const currentTitle = titleField.val();
            if (originalTitle !== undefined && currentTitle !== originalTitle) {
                return true;
            }
        }
        
        const contentField = $('#content');
        if (contentField.length) {
            const originalContent = contentField.prop('defaultValue');
            const currentContent = contentField.val();
            if (originalContent !== undefined && currentContent !== originalContent) {
                return true;
            }
        }

        return false;
    }

    // --- Regenerate Buttons ---
    $('.virastai-acr-regen-btn, .virastai-acr-regen-all-btn').on('click', function(e) {
        e.preventDefault();

        // Check if the post needs to be saved first
        if (virastaiAcrIsPostUnsaved()) {
            const warningMsg = (typeof virastaiMetabox !== 'undefined' && virastaiMetabox.savePostFirst)
                ? virastaiMetabox.savePostFirst
                : 'Please save the post first before generating, as the content is retrieved from the database.';
            alert(warningMsg);
            return;
        }

        const btn = $(this);
        const type = btn.data('type'); // 'content', 'title', 'excerpt', or 'all'
        const container = $('#virastai-acr-metabox-container');
        const loader = container.find('.virastai-acr-loader');

        // Disable all actions while processing
        container.find('.virastai-acr-regen-btn, .virastai-acr-regen-all-btn, .virastai-acr-clear-btn').prop('disabled', true);

        // Hide empty message and make sure fields container is shown
        container.find('.virastai-acr-empty-msg').hide();
        container.find('.virastai-acr-sandbox-fields').show();

        // Configure and show the skeleton loader based on the type
        const skeleton = container.find('.virastai-acr-skeleton-loader');
        
        if (type === 'all') {
            // Hide all groups that are currently displayed so we see only the full skeleton loader
            container.find('.virastai-acr-title-group, .virastai-acr-content-group, .virastai-acr-excerpt-group').hide();
            
            // Show all items of the skeleton
            skeleton.find('.title, .line').show();
        } else {
            // Hide ONLY the group being regenerated to keep other generated fields visible
            container.find('.virastai-acr-' + type + '-group').hide();

            if (type === 'title') {
                skeleton.find('.title').show();
                skeleton.find('.line').hide();
            } else if (type === 'excerpt') {
                skeleton.find('.title').hide();
                // Show only a few lines for excerpt
                skeleton.find('.line').hide().slice(0, 2).show();
            } else {
                skeleton.find('.title').hide();
                skeleton.find('.line').show();
            }
        }
        skeleton.fadeIn();
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

                    // Update fields and show/hide their respective groups based on presence of data
                    if (data.title) {
                        $('#virastai_acr_sandbox_title_field').val(data.title);
                        container.find('.virastai-acr-title-group').slideDown(300);
                    } else {
                        container.find('.virastai-acr-title-group').slideUp(300);
                    }

                    if (data.content) {
                        $('#virastai_acr_sandbox_content_field').val(data.content);
                        container.find('.virastai-acr-content-group').slideDown(300);
                    } else {
                        container.find('.virastai-acr-content-group').slideUp(300);
                    }

                    if (data.excerpt) {
                        $('#virastai_acr_sandbox_excerpt_field').val(data.excerpt);
                        container.find('.virastai-acr-excerpt-group').slideDown(300);
                    } else {
                        container.find('.virastai-acr-excerpt-group').slideUp(300);
                    }

                    // If absolutely nothing is generated, fall back to empty layout
                    if (!data.title && !data.content && !data.excerpt) {
                        container.find('.virastai-acr-sandbox-fields').slideUp(300);
                        container.find('.virastai-acr-empty-msg').fadeIn(300);
                        container.find('.virastai-acr-clear-btn').fadeOut(300);
                        container.find('.virastai-acr-metabox-apply-container').slideUp(300);
                    } else {
                        // Only show apply button container and clear button after a successful process
                        container.find('.virastai-acr-metabox-apply-container').slideDown(300);
                        container.find('.virastai-acr-clear-btn').fadeIn(300);
                    }
                } else {
                    alert('Error: ' + response.data);
                    // Re-show the hidden groups if they originally had content
                    if (type === 'all') {
                        container.find('.virastai-acr-field-group').each(function() {
                            if ($(this).find('input, textarea').val()) {
                                $(this).show();
                            }
                        });
                    } else {
                        const group = container.find('.virastai-acr-' + type + '-group');
                        if (group.find('input, textarea').val()) {
                            group.show();
                        }
                    }
                }
            },
            error: function() {
                alert('Request failed. Please check console.');
                if (type === 'all') {
                    container.find('.virastai-acr-field-group').each(function() {
                        if ($(this).find('input, textarea').val()) {
                            $(this).show();
                        }
                    });
                } else {
                    const group = container.find('.virastai-acr-' + type + '-group');
                    if (group.find('input, textarea').val()) {
                        group.show();
                    }
                }
            },
            complete: function() {
                container.find('.virastai-acr-regen-btn, .virastai-acr-regen-all-btn, .virastai-acr-clear-btn').prop('disabled', false);
                container.find('.virastai-acr-skeleton-loader').hide();
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
                    $('#virastai_acr_sandbox_excerpt_field').val('');
                    container.find('.virastai-acr-sandbox-fields').hide();
                    container.find('.virastai-acr-empty-msg').show();
                    container.find('.virastai-acr-metabox-apply-container').hide();
                    container.find('.virastai-acr-clear-btn').hide();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    // --- Apply Generations Button ---
    $('.virastai-acr-apply-btn').on('click', function(e) {
        e.preventDefault();

        const warningMsg = (typeof virastaiMetabox !== 'undefined' && virastaiMetabox.confirmApply)
            ? virastaiMetabox.confirmApply
            : 'Warning: This action will overwrite your current post data with the generated content. You will lose access to the old data. Are you sure you want to proceed?';

        if (!confirm(warningMsg)) {
            return;
        }

        const btn = $(this);
        const container = $('#virastai-acr-metabox-container');
        const loader = container.find('.virastai-acr-loader');

        btn.prop('disabled', true);
        container.find('.virastai-acr-regen-btn, .virastai-acr-regen-all-btn, .virastai-acr-clear-btn').prop('disabled', true);
        loader.fadeIn();

        $.ajax({
            url: virastaiMetabox.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_apply_sandbox',
                nonce: virastaiMetabox.nonce,
                post_id: virastaiMetabox.postId
            },
            success: function(response) {
                if (response.success) {
                    // Reload the page to reflect the applied changes
                    window.location.reload();
                } else {
                    alert('Error: ' + response.data);
                    btn.prop('disabled', false);
                    container.find('.virastai-acr-regen-btn, .virastai-acr-regen-all-btn, .virastai-acr-clear-btn').prop('disabled', false);
                    loader.fadeOut();
                }
            },
            error: function() {
                alert('Request failed. Please check console.');
                btn.prop('disabled', false);
                container.find('.virastai-acr-regen-btn, .virastai-acr-regen-all-btn, .virastai-acr-clear-btn').prop('disabled', false);
                loader.fadeOut();
            }
        });
    });

    // --- Copy Field Buttons ---
    $(document).on('click', '.virastai-acr-copy-field-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const targetId = btn.data('target');
        const targetField = $('#' + targetId);
        
        if (targetField.length) {
            const value = targetField.val();
            navigator.clipboard.writeText(value).then(function() {
                const originalHtml = btn.html();
                const copiedLabel = (typeof virastaiMetabox !== 'undefined' && virastaiMetabox.copiedText) ? virastaiMetabox.copiedText : 'Copied!';
                btn.html('<span class="dashicons dashicons-yes" style="color: #10b981;"></span> ' + copiedLabel);
                btn.prop('disabled', true);
                setTimeout(function() {
                    btn.html(originalHtml);
                    btn.prop('disabled', false);
                }, 2000);
            }).catch(function(err) {
                alert('Failed to copy text: ' + err);
            });
        }
    });

});
