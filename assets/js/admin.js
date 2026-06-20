/**
 * Virastai Auto Content Rewrite Admin Scripts
 */
jQuery(document).ready(function ($) {

    // --- Modern UI: Post Type Checkbox Cards ---
    $('.virastai-acr-checkbox-card input[type="checkbox"]').on('change', function() {
        const parentCard = $(this).closest('.virastai-acr-checkbox-card');
        if ($(this).is(':checked')) {
            parentCard.addClass('active');
        } else {
            parentCard.removeClass('active');
        }
    });

    // --- Form Validation ---
    const settingsForm = $('#virastai-acr-settings-form');
    const apiKeyField = $('#virastai_acr_openrouter_api_key');

    settingsForm.on('submit', function (e) {
        let hasError = false;

        const confirmMsg = ( typeof virastaiData !== 'undefined' && virastaiData.apiKeyEmpty )
            ? virastaiData.apiKeyEmpty
            : 'The OpenRouter API Key is empty. The plugin will not work without it. Do you want to continue?';

        if (apiKeyField.length && apiKeyField.val().trim() === '') {
            if (!confirm(confirmMsg)) {
                apiKeyField.focus();
                hasError = true;
            }
        }

        if (hasError) {
            e.preventDefault();
        }
    });

    // --- Reset Actions Confirmation ---
    $('#virastai-acr-form-reset-all').on('submit', function() {
        const msg = ( typeof virastaiData !== 'undefined' && virastaiData.confirmResetAll )
            ? virastaiData.confirmResetAll
            : 'Are you sure you want to reset ALL progress?';
        return confirm(msg);
    });

    $('#virastai-acr-form-reset-title').on('submit', function() {
        const msg = ( typeof virastaiData !== 'undefined' && virastaiData.confirmResetTitle )
            ? virastaiData.confirmResetTitle
            : 'Reset Title progress only?';
        return confirm(msg);
    });

    $('#virastai-acr-form-reset-content').on('submit', function() {
        const msg = ( typeof virastaiData !== 'undefined' && virastaiData.confirmResetContent )
            ? virastaiData.confirmResetContent
            : 'Reset Content progress only?';
        return confirm(msg);
    });

    // --- Clear Sandbox Confirmations ---
    $('#virastai-acr-form-clear-sandbox-title').on('submit', function() {
        const msg = ( typeof virastaiData !== 'undefined' && virastaiData.confirmClearSandboxTitle )
            ? virastaiData.confirmClearSandboxTitle
            : 'Are you sure you want to clear Sandbox Titles?';
        return confirm(msg);
    });

    $('#virastai-acr-form-clear-sandbox-content').on('submit', function() {
        const msg = ( typeof virastaiData !== 'undefined' && virastaiData.confirmClearSandboxContent )
            ? virastaiData.confirmClearSandboxContent
            : 'Are you sure you want to clear Sandbox Content?';
        return confirm(msg);
    });

});