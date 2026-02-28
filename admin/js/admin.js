/**
 * admin.js - Core Admin JS (Updated for Scenarios)
 */
(function($) {
    'use strict';

    const pwAdmin = window.pwAdmin || {};

    $(document).ready(function() {

        // Auto-refresh Dashboard if enabled
        if ($('.pw-dashboard[data-refresh]').length && typeof pwAdmin !== 'undefined') {
            const refreshRate = parseInt(pwAdmin.refresh_rate) || 30000;
            if (refreshRate > 0) {
                setInterval(function() {
                    if (!document.hidden && !$('.pw-modal:visible').length) {
                        // Reload or Ajax Refresh
                    }
                }, refreshRate);
            }
        }

        // Global Copy Button
        $('.pw-copy-btn').on('click', function(e) {
            e.preventDefault();
            const target = $(this).data('target');
            const $input = $(target);
            if ($input.length) {
                $input.select();
                document.execCommand('copy');
                const originalText = $(this).text();
                $(this).text('Copié !');
                setTimeout(() => $(this).text(originalText), 2000);
            }
        });

        // Tab Handling
        $('.pw-js-tab-nav a').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            $('.pw-js-tab-nav a').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.pw-js-tab-content').hide();
            $(target).show();
        });

        // --- LIFECYCLE MODALS ---

        // Deactivate Server
        $('.pw-deactivate-server-btn').on('click', function(e) {
            e.preventDefault();
            if(!confirm('Désactiver ce serveur ?\n- Les conversations seront mises en pause\n- La route Postal reste active\n- Réactivation possible à tout moment')) return;

            const id = $(this).data('id');
            const $row = $(this).closest('tr');

            $.post(pwAdmin.ajaxurl, {
                action: 'pw_deactivate_server_with_pause',
                nonce: pwAdmin.nonce,
                server_id: id
            }, function(res) {
                if(res.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + res.data.message);
                }
            });
        });

        // Reactivate Server
        $('.pw-reactivate-server-btn').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');

            $.post(pwAdmin.ajaxurl, {
                action: 'pw_reactivate_server_with_resume',
                nonce: pwAdmin.nonce,
                server_id: id
            }, function(res) {
                if(res.success) {
                    alert('Serveur réactivé.\n' + res.data.resumed + ' conversations reprises.');
                    location.reload();
                } else {
                    alert('Erreur: ' + res.data.message);
                }
            });
        });

        // Configure Route
        $('.pw-configure-route-btn').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const $btn = $(this);
            $btn.prop('disabled', true).text('Configuration...');

            $.post(pwAdmin.ajaxurl, {
                action: 'pw_configure_incoming_route',
                nonce: pwAdmin.nonce,
                server_id: id
            }, function(res) {
                if(res.success) {
                    location.reload();
                } else {
                    alert('Erreur Postal API: ' + res.data.message);
                    $btn.prop('disabled', false).text('Réessayer');
                }
            });
        });

        // DELETE SERVER FLOW
        $('.pw-delete-server-btn').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const domain = $(this).data('domain');

            // Open Modal Step 1
            $('#pw-delete-server-id').val(id);
            $('#pw-delete-server-domain').text(domain);

            // Get stats first
            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_server_conversation_stats',
                nonce: pwAdmin.nonce,
                server_id: id
            }, function(res) {
                if(res.success) {
                    const s = res.data;
                    $('#pw-del-stats-active').text(s.active);
                    $('#pw-del-stats-paused').text(s.paused);
                    $('#pw-del-stats-pending').text(s.pending_reply);
                    $('#pw-delete-server-modal').show();
                    $('#pw-del-step-1').show();
                    $('#pw-del-step-2, #pw-del-step-3').hide();
                }
            });
        });

        // Delete Options
        $('#pw-del-opt-migrate').on('click', function() {
            $('#pw-del-options-panel').hide();
            $('#pw-del-migration-panel').show();
        });

        $('#pw-del-start-migration').on('click', function() {
            const fromId = $('#pw-delete-server-id').val();
            const toId = $('#pw-migration-target').val();

            if(!toId) { alert('Sélectionnez un serveur cible'); return; }

            $('#pw-del-step-2').hide();
            $('#pw-del-step-3').show();

            // Execute
            $.post(pwAdmin.ajaxurl, {
                action: 'pw_execute_server_migration',
                nonce: pwAdmin.nonce,
                from_server_id: fromId,
                to_server_id: toId
            }, function(res) {
                $('#pw-migration-progress').text('Terminé !');
                if(res.success) {
                    $('#pw-migration-result').html('✅ ' + res.data.success + ' conversations migrées.');
                    $('#pw-del-final-btn').prop('disabled', false);
                } else {
                    $('#pw-migration-result').html('❌ Erreur: ' + res.data.error);
                }
            });
        });

        $('#pw-del-final-btn').on('click', function() {
            const id = $('#pw-delete-server-id').val();
            $.post(pwAdmin.ajaxurl, {
                action: 'pw_delete_server_post_migration',
                nonce: pwAdmin.nonce,
                server_id: id
            }, function() {
                location.reload();
            });
        });

        $('.pw-modal-close, .pw-modal-cancel').on('click', function() {
            $(this).closest('.pw-modal').hide();
        });

    });

})(jQuery);
