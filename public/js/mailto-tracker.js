/**
 * Tracking des clics sur les liens mailto warmup
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Tracker les clics sur les liens mailto
        $('.pw-mailto-link[data-track="true"]').on('click', function(e) {
            const $link = $(this);
            const template = $link.data('template');
            const server = $link.data('server');
            const pageUrl = window.location.href;
            
            // Envoyer la requête AJAX (non-bloquante)
            $.post(pwMailto.ajaxurl, {
                action: 'pw_track_mailto_click',
                nonce: pwMailto.nonce,
                template: template,
                server: server,
                page_url: pageUrl
            });
            
            // Le clic continue normalement (ouvre le client mail)
        });
        
        // Rotation des emails au survol
        $('.pw-mailto-link[data-rotate="true"]').on('mouseenter', function() {
            const $link = $(this);
            const emails = $link.data('rotate-emails');
            const domain = $link.data('server-domain');

            if (emails && domain) {
                const prefixList = emails.split(',');
                const randomPrefix = prefixList[Math.floor(Math.random() * prefixList.length)].trim();
                const newEmail = randomPrefix + '@' + domain;

                // Mettre à jour le href en conservant les params (subject, body)
                const currentHref = $link.attr('href');
                if (currentHref.indexOf('mailto:') === 0) {
                    const parts = currentHref.split('?');
                    const params = parts.length > 1 ? '?' + parts[1] : '';
                    $link.attr('href', 'mailto:' + newEmail + params);
                }
            }
        });

    });
    
})(jQuery);