/**
 * BD Speed Optimizer — Admin Dashboard JS
 */
(function($) {
    'use strict';

    // ─── Toast ───────────────────────────────────────────────────────
    function showToast(msg, type) {
        var $toast = $('#bdso-toast');
        $toast.text(msg).removeClass('success error').addClass(type).fadeIn(200);
        setTimeout(function() { $toast.fadeOut(300); }, 3000);
    }

    // ─── Score Animation ─────────────────────────────────────────────
    function animateScore(score) {
        var circumference = 2 * Math.PI * 70; // r=70
        var $ring = $('#bdso-score-ring');
        var $number = $('#bdso-score-number');
        var $circle = $('#bdso-score-circle');

        // Set color class based on score.
        $circle.removeClass('bdso-score-red bdso-score-amber bdso-score-green');
        if (score < 50) {
            $circle.addClass('bdso-score-red');
        } else if (score < 80) {
            $circle.addClass('bdso-score-amber');
        } else {
            $circle.addClass('bdso-score-green');
        }

        // Animate ring.
        var target = (score / 100) * circumference;
        $ring.css('transition', 'stroke-dasharray 1.5s ease');
        $ring.attr('stroke-dasharray', target + ' ' + circumference);

        // Animate number.
        var current = { val: 0 };
        $(current).animate({ val: score }, {
            duration: 1500,
            easing: 'swing',
            step: function() {
                $number.text(Math.round(this.val));
            },
            complete: function() {
                $number.text(score);
            }
        });
    }

    // ─── Render Check Items ──────────────────────────────────────────
    function renderChecks(checks) {
        var $frontend = $('#bdso-frontend-checks').empty();
        var $database = $('#bdso-database-checks').empty();

        $.each(checks, function(i, check) {
            var iconClass = check.status;
            var iconChar = check.status === 'pass' ? '&#10003;' : (check.status === 'warning' ? '!' : '&#10007;');

            var html = '<div class="bdso-check-item">' +
                '<div class="bdso-check-icon ' + iconClass + '">' + iconChar + '</div>' +
                '<div class="bdso-check-info">' +
                    '<strong>' + check.label + '</strong>' +
                    '<span>' + check.message + '</span>' +
                '</div>' +
                '<span class="bdso-check-weight">+' + check.weight + '</span>' +
            '</div>';

            if (check.category === 'frontend') {
                $frontend.append(html);
            } else {
                $database.append(html);
            }
        });
    }

    // ─── Run Scan ────────────────────────────────────────────────────
    $(document).on('click', '#bdso-scan-btn', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="bdso-spinner"></span> ' + bdsoAdmin.strings.scanning);

        $.post(bdsoAdmin.ajaxUrl, {
            action: 'bdso_run_scan',
            nonce: bdsoAdmin.nonce
        }, function(res) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Run Scan');
            if (res.success) {
                animateScore(res.data.score);
                renderChecks(res.data.checks);
                $('#bdso-results').slideDown(300);
            } else {
                showToast(res.data || 'Scan failed.', 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Run Scan');
            showToast('Scan request failed.', 'error');
        });
    });

    // ─── DB Cleanup Buttons ──────────────────────────────────────────
    $(document).on('click', '.bdso-clean-btn', function() {
        var $btn = $(this);
        var action = $btn.data('action');
        $btn.prop('disabled', true).html('<span class="bdso-spinner"></span>');

        $.post(bdsoAdmin.ajaxUrl, {
            action: action,
            nonce: bdsoAdmin.nonce
        }, function(res) {
            if (res.success) {
                showToast(res.data.message, 'success');
                $btn.text('Done').prop('disabled', true);

                // Update counts.
                if (action === 'bdso_clean_revisions') {
                    $('#bdso-count-revisions').text('0').addClass('clean');
                } else if (action === 'bdso_clean_spam') {
                    $('#bdso-count-spam').text('0').addClass('clean');
                } else if (action === 'bdso_clean_transients') {
                    $('#bdso-count-transients').text('0').addClass('clean');
                }
            } else {
                showToast(res.data || 'Cleanup failed.', 'error');
                $btn.prop('disabled', false).text('Clean Now');
            }
        }).fail(function() {
            showToast('Request failed.', 'error');
            $btn.prop('disabled', false).text('Clean Now');
        });
    });

    // ─── Optimize Tables ─────────────────────────────────────────────
    $(document).on('click', '#bdso-optimize-tables', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="bdso-spinner"></span>');

        $.post(bdsoAdmin.ajaxUrl, {
            action: 'bdso_optimize_tables',
            nonce: bdsoAdmin.nonce
        }, function(res) {
            if (res.success) {
                showToast(res.data.message, 'success');
                $btn.text('Done').prop('disabled', true);
            } else {
                showToast(res.data || 'Optimization failed.', 'error');
                $btn.prop('disabled', false).text('Optimize');
            }
        }).fail(function() {
            showToast('Request failed.', 'error');
            $btn.prop('disabled', false).text('Optimize');
        });
    });

    // ─── Save Settings ───────────────────────────────────────────────
    $(document).on('submit', '#bdso-settings-form', function(e) {
        e.preventDefault();
        var $btn = $('#bdso-save-btn');
        $btn.prop('disabled', true).text(bdsoAdmin.strings.saving);

        var formData = $(this).serializeArray();
        var settings = {};

        // Collect fields.
        $.each(formData, function(i, field) {
            var match = field.name.match(/^settings\[(.+?)\]$/);
            if (match) {
                settings[match[1]] = field.value;
            }
        });

        // Ensure unchecked checkboxes are sent as empty.
        var booleans = [
            'defer_js', 'delay_js', 'remove_jquery_migrate', 'minify_html',
            'lazy_load_images', 'lazy_load_iframes', 'disable_emojis',
            'disable_embeds', 'remove_query_strings'
        ];
        $.each(booleans, function(i, key) {
            if (!settings[key]) settings[key] = '';
        });

        $.post(bdsoAdmin.ajaxUrl, {
            action: 'bdso_save_settings',
            nonce: bdsoAdmin.nonce,
            settings: settings
        }, function(res) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Settings');
            if (res.success) {
                showToast(bdsoAdmin.strings.saved, 'success');
            } else {
                showToast(res.data || bdsoAdmin.strings.error, 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Settings');
            showToast(bdsoAdmin.strings.error, 'error');
        });
    });

    // ─── License Activate ────────────────────────────────────────────
    $(document).on('click', '#bdso-activate-license', function() {
        var $btn = $(this);
        var key = $('#bdso-license-key').val().trim();
        if (!key) return;

        $btn.prop('disabled', true).text('Activating...');
        $('#bdso-license-status').text('');

        $.post(bdsoAdmin.ajaxUrl, {
            action: 'bdso_activate_license',
            nonce: bdsoAdmin.nonce,
            license_key: key
        }, function(res) {
            $btn.prop('disabled', false).text('Activate');
            if (res.success) {
                showToast(res.data.message, 'success');
                setTimeout(function() { location.reload(); }, 500);
            } else {
                $('#bdso-license-status').text(res.data).css('color', 'var(--bdso-danger)');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Activate');
            $('#bdso-license-status').text('Request failed.').css('color', 'var(--bdso-danger)');
        });
    });

    // ─── License Deactivate ──────────────────────────────────────────
    $(document).on('click', '#bdso-deactivate-license', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Deactivating...');

        $.post(bdsoAdmin.ajaxUrl, {
            action: 'bdso_deactivate_license',
            nonce: bdsoAdmin.nonce
        }, function(res) {
            $btn.prop('disabled', false).text('Deactivate');
            if (res.success) {
                showToast(res.data.message, 'success');
                setTimeout(function() { location.reload(); }, 500);
            }
        });
    });

})(jQuery);
