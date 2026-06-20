/**
 * Virastai Auto Content Rewrite Admin Scripts
 */
jQuery(document).ready(function ($) {

    // --- Tab Switching Logic with Hash States & HTTP Referer Persistence ---
    function virastaiAcrSwitchTab(tabId) {
        const tabLink = $(`.virastai-acr-tabs-nav .nav-tab[data-tab="${tabId}"]`);
        if (tabLink.length) {
            $('.virastai-acr-tabs-nav .nav-tab').removeClass('nav-tab-active');
            tabLink.addClass('nav-tab-active');

            $('.virastai-acr-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
        }
    }

    function virastaiAcrUpdateReferer(tabId) {
        const refererInput = $('input[name="_wp_http_referer"]');
        if (refererInput.length) {
            let val = refererInput.val();
            // Remove any existing tab parameters
            val = val.replace(/&tab=[^&]*/g, '').replace(/\?tab=[^&]*/g, '');
            // Append new tab parameter
            if (val.indexOf('?') !== -1) {
                val += '&tab=' + tabId;
            } else {
                val += '?tab=' + tabId;
            }
            refererInput.val(val);
        }
    }

    $('.virastai-acr-tabs-nav .nav-tab').on('click', function(e) {
        e.preventDefault();
        const tabId = $(this).data('tab');
        virastaiAcrSwitchTab(tabId);
        history.pushState(null, null, '#tab-' + tabId);
        virastaiAcrUpdateReferer(tabId);
    });

    // Check hash or query param on page load
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    const initialHash = window.location.hash;

    if (initialHash && initialHash.indexOf('#tab-') === 0) {
        const tabId = initialHash.replace('#tab-', '');
        virastaiAcrSwitchTab(tabId);
        virastaiAcrUpdateReferer(tabId);
    } else if (tabParam) {
        virastaiAcrSwitchTab(tabParam);
        virastaiAcrUpdateReferer(tabParam);
    } else {
        // Ensure default active tab is set in referer url
        const activeTab = $('.virastai-acr-tabs-nav .nav-tab-active').data('tab');
        if (activeTab) {
            virastaiAcrUpdateReferer(activeTab);
        }
    }

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

    $('#virastai-acr-form-reset-excerpt').on('submit', function() {
        return confirm('Reset Excerpt progress only?');
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

    $('#virastai-acr-form-clear-sandbox-excerpt').on('submit', function() {
        return confirm('Are you sure you want to clear Sandbox Excerpts?');
    });

    // --- Reset Prompt to Default ---
    $('.virastai-acr-reset-btn').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        const targetId = btn.data('target');
        const defaultValue = btn.data('default');
        const targetTextarea = $('#' + targetId);

        if (targetTextarea.length && defaultValue !== undefined) {
            targetTextarea.val(defaultValue);
        }
    });

    // --- System Logs Table & AJAX Pagination ---
    function virastaiAcrLoadLogs(page) {
        const rowsContainer = $('#virastai-acr-logs-rows');
        const paginationContainer = $('#virastai-acr-logs-pagination');
        const refreshBtn = $('#virastai-acr-refresh-logs-btn');
        const icon = refreshBtn.find('.dashicons');

        refreshBtn.prop('disabled', true);
        icon.addClass('virastai-acr-rotating');
        rowsContainer.html('<tr><td colspan="4" class="virastai-acr-no-logs">Loading logs...</td></tr>');

        $.ajax({
            url: virastaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_refresh_logs',
                nonce: virastaiData.nonce,
                paged: page
            },
            success: function(response) {
                if (response.success) {
                    rowsContainer.html(response.data.rows_html);
                    paginationContainer.html(response.data.pagination_html);
                    $('#virastai-acr-logs-raw-content').val(response.data.raw_logs);
                } else {
                    rowsContainer.html('<tr><td colspan="4" class="virastai-acr-no-logs">Failed to load logs: ' + response.data + '</td></tr>');
                }
            },
            error: function() {
                rowsContainer.html('<tr><td colspan="4" class="virastai-acr-no-logs">Failed to load logs.</td></tr>');
            },
            complete: function() {
                refreshBtn.prop('disabled', false);
                icon.removeClass('virastai-acr-rotating');
            }
        });
    }

    // Trigger initial logs load
    if ($('#virastai-acr-logs-rows').length) {
        virastaiAcrLoadLogs(1);
    }

    // Refresh button click
    $('#virastai-acr-refresh-logs-btn').on('click', function(e) {
        e.preventDefault();
        virastaiAcrLoadLogs(1);
    });

    // Pagination click
    $('#virastai-acr-logs-pagination').on('click', '.page-numbers', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) {
            virastaiAcrLoadLogs(page);
        }
    });

    // --- Log Details Modal & JSON Beautifier ---
    
    // Append modal structure dynamically if it doesn't exist
    if ($('#virastai-acr-log-modal').length === 0) {
        $('body').append(
            '<div id="virastai-acr-log-modal" class="virastai-acr-modal" style="display: none;">' +
                '<div class="virastai-acr-modal-backdrop"></div>' +
                '<div class="virastai-acr-modal-content">' +
                    '<div class="virastai-acr-modal-header">' +
                        '<h3 class="virastai-acr-modal-title">System Log Details</h3>' +
                        '<div class="virastai-acr-modal-meta">' +
                            '<span class="virastai-acr-modal-date"></span>' +
                            '<span class="log-badge"></span>' +
                        '</div>' +
                        '<button type="button" class="virastai-acr-modal-close-btn">&times;</button>' +
                    '</div>' +
                    '<div class="virastai-acr-modal-body">' +
                        '<pre class="virastai-acr-modal-code"></pre>' +
                    '</div>' +
                    '<div class="virastai-acr-modal-footer">' +
                        '<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-secondary virastai-acr-modal-copy-btn">' +
                            '<span class="dashicons dashicons-admin-page"></span> Copy Content' +
                        '</button>' +
                        '<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-primary virastai-acr-modal-close-action-btn">' +
                            'Close' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
    }

    const modal = $('#virastai-acr-log-modal');
    const modalCode = modal.find('.virastai-acr-modal-code');
    const modalDate = modal.find('.virastai-acr-modal-date');
    const modalBadge = modal.find('.log-badge');
    const modalCopyBtn = modal.find('.virastai-acr-modal-copy-btn');
    let currentModalRawMessage = '';

    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function virastaiAcrSyntaxHighlightJson(json) {
        if (typeof json !== 'string') {
            json = JSON.stringify(json, undefined, 2);
        }
        json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
            let cls = 'virastai-acr-json-number';
            if (/^"/.test(match)) {
                if (/:$/.test(match)) {
                    cls = 'virastai-acr-json-key';
                } else {
                    cls = 'virastai-acr-json-string';
                }
            } else if (/true|false/.test(match)) {
                cls = 'virastai-acr-json-boolean';
            } else if (/null/.test(match)) {
                cls = 'virastai-acr-json-null';
            }
            return '<span class="' + cls + '">' + match + '</span>';
        });
    }

    function virastaiAcrBeautifyMessage(text) {
        if (!text) return '';
        
        // Collapse 3 or more consecutive newlines down to 2
        text = text.replace(/(\r?\n){3,}/g, '\n\n');
        
        // Check if the entire string is valid JSON
        try {
            const parsed = JSON.parse(text);
            return virastaiAcrSyntaxHighlightJson(JSON.stringify(parsed, null, 4));
        } catch (e) {
            // Check for JSON block inside
        }
        
        let result = '';
        let lastIndex = 0;
        
        for (let i = 0; i < text.length; i++) {
            if (text[i] === '{' || text[i] === '[') {
                let braceCount = 1;
                let j = i + 1;
                let inString = false;
                let escape = false;
                const startChar = text[i];
                const endChar = (startChar === '{') ? '}' : ']';
                
                while (j < text.length && braceCount > 0) {
                    const char = text[j];
                    if (escape) {
                        escape = false;
                    } else if (char === '\\') {
                        escape = true;
                    } else if (char === '"') {
                        inString = !inString;
                    } else if (!inString) {
                        if (char === startChar) {
                            braceCount++;
                        } else if (char === endChar) {
                            braceCount--;
                        }
                    }
                    j++;
                }
                
                if (braceCount === 0) {
                    const jsonCandidate = text.substring(i, j);
                    try {
                        const parsed = JSON.parse(jsonCandidate);
                        result += escapeHtml(text.substring(lastIndex, i));
                        result += virastaiAcrSyntaxHighlightJson(JSON.stringify(parsed, null, 4));
                        lastIndex = j;
                        i = j - 1;
                    } catch (e) {
                        // Not valid JSON, continue scanning
                    }
                }
            }
        }
        
        if (lastIndex < text.length) {
            result += escapeHtml(text.substring(lastIndex));
        }
        
        return result;
    }

    function openLogModal(date, type, message, badgeClass) {
        currentModalRawMessage = message;
        modalDate.text(date);
        modalBadge.text(type).attr('class', 'log-badge ' + badgeClass);
        
        const processed = virastaiAcrBeautifyMessage(message);
        modalCode.html(processed);
        
        modalCopyBtn.html('<span class="dashicons dashicons-admin-page"></span> Copy Content');
        
        modal.show();
        // Force reflow
        modal[0].offsetHeight;
        modal.addClass('active');
        $('body').addClass('virastai-acr-modal-open');
    }

    function closeLogModal() {
        modal.removeClass('active');
        $('body').removeClass('virastai-acr-modal-open');
        setTimeout(function() {
            modal.hide();
        }, 250);
    }

    // Modal Close event handlers
    modal.find('.virastai-acr-modal-close-btn, .virastai-acr-modal-close-action-btn, .virastai-acr-modal-backdrop').on('click', function(e) {
        e.preventDefault();
        closeLogModal();
    });

    // Close on ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && modal.hasClass('active')) {
            closeLogModal();
        }
    });

    // Copy Content in modal
    modalCopyBtn.on('click', function(e) {
        e.preventDefault();
        navigator.clipboard.writeText(currentModalRawMessage).then(function() {
            modalCopyBtn.html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span> Copied!');
            setTimeout(function() {
                modalCopyBtn.html('<span class="dashicons dashicons-admin-page"></span> Copy Content');
            }, 2000);
        }).catch(function(err) {
            alert('Failed to copy log: ' + err);
        });
    });

    // Details Toggle click
    $('#virastai-acr-logs-rows').on('click', '.virastai-acr-toggle-log-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const row = btn.closest('tr');
        const date = row.find('.col-date').text();
        const badge = row.find('.log-badge');
        const type = badge.text();
        const badgeClass = badge.attr('class').replace('log-badge ', '');
        const rawMessage = row.find('.log-details pre').text();
        
        openLogModal(date, type, rawMessage, badgeClass);
    });

    $('#virastai-acr-clear-logs-btn').on('click', function(e) {
        e.preventDefault();
        $('#virastai-acr-form-clear-logs').submit();
    });

    $('#virastai-acr-form-clear-logs').on('submit', function() {
        return confirm('Are you sure you want to delete ALL system logs? This action cannot be undone.');
    });

    // --- Automation Tab Reset Buttons ---
    $('#virastai-acr-tab-reset-all-btn').on('click', function(e) {
        e.preventDefault();
        $('#virastai-acr-form-reset-all').submit();
    });

    $('#virastai-acr-copy-logs-btn').on('click', function(e) {
        e.preventDefault();
        const logsText = $('#virastai-acr-logs-raw-content').val();
        const btn = $(this);
        const originalHtml = btn.html();

        navigator.clipboard.writeText(logsText).then(function() {
            btn.html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span> Copied!');
            setTimeout(function() {
                btn.html(originalHtml);
            }, 2000);
        }).catch(function(err) {
            alert('Failed to copy logs: ' + err);
        });
    });

});
