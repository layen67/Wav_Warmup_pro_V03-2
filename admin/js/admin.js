/**
 * admin.js - Core Admin JS
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Auto-refresh Dashboard if enabled
        if ($('.pw-dashboard[data-refresh]').length && typeof pwAdmin !== 'undefined') {
            const refreshRate = parseInt(pwAdmin.refresh_rate) || 30000;
            if (refreshRate > 0) {
                setInterval(function() {
                    // Only refresh if tab is active
                    if (!document.hidden) {
                        // Ideally partial reload via AJAX, but simple reload is safer for now
                        // window.location.reload();
                        // Actually, let's implement AJAX refresh for stats
                    }
                }, refreshRate);
            }
        }

        // Global Copy Button (e.g. Webhook URL)
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

        // Tab Handling (Settings Page)
        // Handled via PHP routing usually, but if JS tabs exist:
        $('.pw-js-tab-nav a').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            $('.pw-js-tab-nav a').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.pw-js-tab-content').hide();
            $(target).show();
        });

    });

})(jQuery);
