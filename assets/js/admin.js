/**
 * Virastai Auto Content Rewrite Admin Scripts
 */
jQuery(document).ready(function ($) {

    // --- Tab Switching Logic ---
    $('.virastai-acr-tabs-nav .nav-tab').on('click', function(e) {
        e.preventDefault();
        const tabLink = $(this);
        const tabId = tabLink.data('tab');

        // Toggle active tab link class
        $('.virastai-acr-tabs-nav .nav-tab').removeClass('nav-tab-active');
        tabLink.addClass('nav-tab-active');

        // Toggle active tab content visibility
        $('.virastai-acr-tab-content').removeClass('active');
        $('#tab-' + tabId).addClass('active');
    });

    // --- Masked Password Field Visibility Toggle ---
    $('.virastai-acr-toggle-visibility-btn').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        const input = btn.siblings('.virastai-acr-input');
        const icon = btn.find('.dashicons');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // --- Post Type Checkbox Cards ---
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
