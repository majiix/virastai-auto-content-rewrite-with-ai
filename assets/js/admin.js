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

        const MAX_HIGHLIGHT_LEN = 30000;
        const MAX_DISPLAY_LEN   = 80000;
        let isTruncated = false;
        const originalLen = text.length;

        let displayText = text;
        if (text.length > MAX_DISPLAY_LEN) {
            displayText = text.substring(0, MAX_DISPLAY_LEN);
            isTruncated = true;
        }

        const truncationNotice = isTruncated
            ? '\n\n<span style="display:inline-block;padding:8px 12px;background:#3b2405;color:#fbbf24;border-radius:6px;font-weight:600;margin-top:12px;">' +
              '[Notice: Preview capped at 80 KB to preserve browser responsiveness. Full log size: ' + Math.round(originalLen / 1024) + ' KB. Use "Copy Content" below to copy the complete log.]</span>'
            : '';

        // Check if entire text is valid JSON
        const trimmed = displayText.trim();
        if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
            try {
                const parsed = JSON.parse(trimmed);
                const formatted = JSON.stringify(parsed, null, 2);
                const content = (formatted.length <= MAX_HIGHLIGHT_LEN)
                    ? virastaiAcrSyntaxHighlightJson(formatted)
                    : escapeHtml(formatted);
                return content + truncationNotice;
            } catch (e) {}
        }

        // Check for structured labels like Headers: and Body:
        const parts = displayText.split(/(Headers:\s*|Body:\s*)/g);
        if (parts.length > 1) {
            let result = '';
            for (let idx = 0; idx < parts.length; idx++) {
                const part = parts[idx];
                if (part === 'Headers: ' || part === 'Body: ') {
                    result += '<strong style="color: #818cf8;">' + escapeHtml(part) + '</strong>';
                } else if (idx > 0 && (parts[idx - 1] === 'Headers: ' || parts[idx - 1] === 'Body: ')) {
                    const subTrim = part.trim();
                    let parsed = null;
                    try {
                        parsed = JSON.parse(subTrim);
                    } catch (e) {}

                    if (parsed !== null) {
                        const formatted = JSON.stringify(parsed, null, 2);
                        if (formatted.length <= MAX_HIGHLIGHT_LEN) {
                            result += virastaiAcrSyntaxHighlightJson(formatted) + '\n';
                        } else {
                            result += escapeHtml(formatted) + '\n';
                        }
                    } else {
                        result += escapeHtml(part);
                    }
                } else {
                    result += escapeHtml(part);
                }
            }
            return result + truncationNotice;
        }

        return escapeHtml(displayText) + truncationNotice;
    }

    function openLogModal(date, type, message, badgeClass) {
        currentModalRawMessage = message;
        modalDate.text(date);
        modalBadge.text(type).attr('class', 'log-badge ' + (badgeClass || 'type-info'));

        const processed = virastaiAcrBeautifyMessage(message);
        modalCode.html(processed);

        modalCopyBtn.html('<span class="dashicons dashicons-admin-page"></span> Copy Content');

        modal.show();
        requestAnimationFrame(function() {
            modal.addClass('active');
        });
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
            modalCopyBtn.html('<span class="dashicons dashicons-yes" style="color: #10b981;"></span> Copied!');
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
        const badgeClass = badge.attr('class') ? badge.attr('class').replace('log-badge ', '').trim() : 'type-info';
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
            btn.html('<span class="dashicons dashicons-yes" style="color: #10b981;"></span> Copied!');
            setTimeout(function() {
                btn.html(originalHtml);
            }, 2000);
        }).catch(function(err) {
            alert('Failed to copy logs: ' + err);
        });
    });

    // --- Custom Automation Logic ---
    let caPollInterval = null;

    function updateCaUI(data) {
        if (!data || !data.state) return;
        const state = data.state;
        const percentage = typeof data.percentage !== 'undefined' ? data.percentage : 0;

        const badge = $('#virastai-acr-ca-status-badge');
        badge.removeClass('badge-idle badge-running badge-paused badge-completed badge-stopped')
             .addClass('badge-' + state.status)
             .text(state.status.toUpperCase());

        const jobScope = $('#virastai-acr-ca-job-scope');
        if (state.start_date && state.end_date && state.total_posts > 0) {
            jobScope.text('(Job Scope: ' + state.start_date + ' to ' + state.end_date + ')').show();
        } else {
            jobScope.hide();
        }

        const activeItem = $('#virastai-acr-ca-active-item');
        if (state.status === 'running') {
            if (state.current_post_title) {
                activeItem.text('Processing: ' + state.current_post_title);
            } else if (typeof data.seconds_until_next !== 'undefined' && data.seconds_until_next > 0) {
                const ppm = state.posts_per_minute || 5;
                activeItem.text('Next rewrite in ' + data.seconds_until_next + 's (rate: ' + ppm + '/min)');
            } else {
                activeItem.text('Preparing next rewrite...');
            }
        } else {
            activeItem.text('');
        }

        $('#virastai-acr-ca-percent-text').text(percentage + '%');
        $('#virastai-acr-ca-progress-fill').css('width', percentage + '%');

        $('#virastai-acr-ca-metric-total').text(state.total_posts);
        $('#virastai-acr-ca-metric-processed').text(state.processed_posts);
        $('#virastai-acr-ca-metric-failed').text(state.failed_posts);

        const isRunning = state.status === 'running';
        const isPaused = state.status === 'paused';
        const isActive = isRunning || isPaused;

        $('#virastai-acr-ca-start-btn').prop('disabled', isRunning);
        if (isPaused) {
            $('#virastai-acr-ca-start-btn .btn-text').text('Resume Automation');
        } else {
            $('#virastai-acr-ca-start-btn .btn-text').text('Start Automation');
        }
        $('#virastai-acr-ca-pause-btn').prop('disabled', !isRunning);
        $('#virastai-acr-ca-stop-btn').prop('disabled', !isActive);
        $('#virastai-acr-ca-retry-btn').prop('disabled', !state.failed_posts || state.failed_posts <= 0);

        $('#virastai_acr_ca_start_date, #virastai_acr_ca_end_date, #virastai_acr_ca_posts_per_minute, input[name="virastai_acr_ca_post_types[]"], #virastai_acr_ca_include_rewritten')
            .prop('disabled', isActive);

        if (isRunning) {
            startCaPolling();
        } else {
            stopCaPolling();
        }
    }

    let caPollTimer = null;
    let isCaPolling = false;

    function pollCaStatus() {
        if (isCaPolling) return;
        isCaPolling = true;

        $.ajax({
            url: virastaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_ca_status',
                nonce: virastaiData.nonce
            },
            success: function(res) {
                if (res.success && res.data) {
                    updateCaUI(res.data);
                }
            },
            complete: function() {
                isCaPolling = false;
                if ($('#virastai-acr-ca-status-badge').hasClass('badge-running')) {
                    scheduleNextCaPoll();
                }
            }
        });
    }

    function scheduleNextCaPoll() {
        clearTimeout(caPollTimer);
        caPollTimer = setTimeout(pollCaStatus, 1500);
    }

    function startCaPolling() {
        clearTimeout(caPollTimer);
        scheduleNextCaPoll();
    }

    function stopCaPolling() {
        clearTimeout(caPollTimer);
        caPollTimer = null;
        isCaPolling = false;
    }

    if ($('#virastai-acr-ca-status-badge').hasClass('badge-running')) {
        startCaPolling();
    }

    // Start Button Click
    $('#virastai-acr-ca-start-btn').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        const originalText = btn.find('.btn-text').text();

        const startDate = $('#virastai_acr_ca_start_date').val();
        const endDate = $('#virastai_acr_ca_end_date').val();
        if (!startDate || !endDate) {
            alert('Please select both start and end publication dates.');
            return;
        }

        const postTypes = [];
        $('input[name="virastai_acr_ca_post_types[]"]:checked').each(function() {
            postTypes.push($(this).val());
        });
        if (postTypes.length === 0) {
            alert('Please select at least one Target Post Type.');
            return;
        }

        let ppm = parseInt($('#virastai_acr_ca_posts_per_minute').val(), 10);
        if (isNaN(ppm) || ppm < 1) ppm = 5;
        if (ppm > 60) ppm = 60;
        $('#virastai_acr_ca_posts_per_minute').val(ppm);

        btn.prop('disabled', true).find('.btn-text').text('Starting...');

        $.ajax({
            url: virastaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_ca_start',
                nonce: virastaiData.nonce,
                start_date: startDate,
                end_date: endDate,
                posts_per_minute: ppm,
                post_types: postTypes,
                include_already_rewritten: $('#virastai_acr_ca_include_rewritten').is(':checked') ? 1 : 0
            },
            success: function(res) {
                if (res.success && res.data) {
                    updateCaUI(res.data);
                    startCaPolling();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error starting automation.');
                    btn.prop('disabled', false).find('.btn-text').text(originalText);
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
                btn.prop('disabled', false).find('.btn-text').text(originalText);
            }
        });
    });

    // Pause Button Click
    $('#virastai-acr-ca-pause-btn').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: virastaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_ca_pause',
                nonce: virastaiData.nonce
            },
            success: function(res) {
                if (res.success && res.data) {
                    updateCaUI(res.data);
                }
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    // Stop Button Click
    $('#virastai-acr-ca-stop-btn').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to stop custom automation? Active execution will halt.')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: virastaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_ca_stop',
                nonce: virastaiData.nonce
            },
            success: function(res) {
                if (res.success && res.data) {
                    updateCaUI(res.data);
                    fetchCaCounts();
                }
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    // Dynamic Preview Counts on Job Parameters Change
    let caCountsTimeout = null;

    function fetchCaCounts() {
        const startDate = $('#virastai_acr_ca_start_date').val();
        const endDate = $('#virastai_acr_ca_end_date').val();
        if (!startDate || !endDate) return;

        const postTypes = [];
        $('input[name="virastai_acr_ca_post_types[]"]:checked').each(function() {
            postTypes.push($(this).val());
        });

        const badge = $('#virastai-acr-ca-counts-badge');
        badge.addClass('is-loading');

        $.ajax({
            url: virastaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_ca_preview_counts',
                nonce: virastaiData.nonce,
                start_date: startDate,
                end_date: endDate,
                post_types: postTypes,
                include_already_rewritten: $('#virastai_acr_ca_include_rewritten').is(':checked') ? 1 : 0
            },
            success: function(res) {
                if (res.success && res.data && res.data.formatted_text) {
                    $('#virastai-acr-ca-counts-badge .virastai-acr-ca-counts-text').text(res.data.formatted_text);
                }
            },
            complete: function() {
                badge.removeClass('is-loading');
            }
        });
    }

    function triggerCaCountsChange() {
        clearTimeout(caCountsTimeout);
        caCountsTimeout = setTimeout(fetchCaCounts, 250);
    }

    $('#virastai_acr_ca_start_date, #virastai_acr_ca_end_date, #virastai_acr_ca_include_rewritten').on('change', triggerCaCountsChange);
    $(document).on('change', 'input[name="virastai_acr_ca_post_types[]"]', triggerCaCountsChange);

    // Retry Failed Posts Click
    $('#virastai-acr-ca-retry-btn').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        btn.prop('disabled', true).find('.retry-btn-text').text('Queueing...');

        $.ajax({
            url: virastaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'virastai_acr_ca_retry_failed',
                nonce: virastaiData.nonce
            },
            success: function(res) {
                if (res.success && res.data) {
                    updateCaUI(res.data);
                    pollCaStatus();
                    fetchCaCounts();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error retrying failed posts.');
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
            },
            complete: function() {
                btn.find('.retry-btn-text').text('Retry Failed');
            }
        });
    });

});

