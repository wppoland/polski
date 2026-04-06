/**
 * Polski Social Proof Notifications.
 * Displays floating purchase notifications to boost conversions.
 */
(function ($) {
    'use strict';

    if (typeof polskiSocialProof === 'undefined') return;

    var config = polskiSocialProof;
    var notifications = [];
    var currentIndex = 0;
    var container;

    function init() {
        container = document.getElementById('polski-social-proof-container');
        if (!container) return;

        // Fetch notifications via AJAX.
        $.post(config.ajaxUrl, {
            action: 'polski_social_proof',
            nonce: config.nonce
        }, function (response) {
            if (response.success && response.data && response.data.length > 0) {
                notifications = response.data;
                // Start showing after a short delay.
                setTimeout(showNext, 3000);
            }
        });
    }

    function showNext() {
        if (notifications.length === 0) return;

        var n = notifications[currentIndex % notifications.length];
        currentIndex++;

        var imgHtml = n.image
            ? '<img src="' + n.image + '" alt="" loading="lazy">'
            : '';

        var toast = document.createElement('a');
        toast.href = n.url;
        toast.className = 'polski-sp-toast';
        toast.setAttribute('role', 'status');
        toast.innerHTML = imgHtml +
            '<div>' +
                '<div><strong>' + escHtml(n.name) + '</strong> ' + escHtml(n.city) + '</div>' +
                '<div>' + escHtml(n.product) + '</div>' +
                '<div class="sp-time">' + escHtml(n.time) + '</div>' +
            '</div>' +
            '<span class="sp-close" onclick="event.preventDefault();event.stopPropagation();this.parentElement.remove()">&times;</span>';

        container.appendChild(toast);

        // Auto-hide after duration.
        setTimeout(function () {
            toast.classList.add('hiding');
            setTimeout(function () {
                if (toast.parentElement) toast.remove();
            }, 400);
        }, config.duration);

        // Schedule next.
        setTimeout(showNext, config.interval);
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Init after page load.
    if (document.readyState === 'complete') {
        init();
    } else {
        window.addEventListener('load', init);
    }
})(jQuery);
