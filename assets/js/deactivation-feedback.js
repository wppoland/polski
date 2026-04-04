(function($) {
    'use strict';

    $(document).ready(function() {
        const config = window.polskiDeactivation;
        if (!config) return;

        const pluginBasename = config.plugin_basename;
        const rowSlug = config.plugin_row_slug;
        const rowSelector = `tr[data-plugin="${pluginBasename}"], tr[data-slug="${rowSlug}"]`;
        const pluginRow = $(rowSelector).first();
        const deactivationLink = pluginRow.find('a.deactivate, .deactivate a').first();

        if (!deactivationLink.length || !pluginRow.length) return;

        const modalHtml = `
            <div id="polski-deactivation-modal" class="polski-modal-overlay" style="display:none;">
                <div class="polski-modal-content">
                    <div class="polski-modal-header">
                        <h2>${config.i18n.title}</h2>
                        <p>${config.i18n.subtitle}</p>
                    </div>
                    <div class="polski-modal-body">
                        <div class="polski-modal-section">
                            <label class="polski-field-label">${config.i18n.reasonLabel}</label>
                        </div>
                        <ul class="polski-reasons-list">
                            ${Object.entries(config.i18n.reasons).map(([key, label]) => `
                                <li>
                                    <label>
                                        <input type="radio" name="reasons" value="${key}">
                                        <span>${label}</span>
                                    </label>
                                </li>
                            `).join('')}
                        </ul>
                        <div class="polski-modal-section">
                            <label class="polski-field-label" for="polski-feedback-improvement">${config.i18n.improveLabel}</label>
                            <textarea id="polski-feedback-improvement" placeholder="${config.i18n.improvePlaceholder}"></textarea>
                        </div>
                        <div class="polski-modal-section">
                            <label class="polski-field-label" for="polski-feedback-feature">${config.i18n.featureLabel}</label>
                            <textarea id="polski-feedback-feature" placeholder="${config.i18n.featurePlaceholder}"></textarea>
                        </div>
                    </div>
                    <div class="polski-modal-footer">
                        <div class="polski-goodbye-message" style="margin-bottom:16px;text-align:left;font-size:13px;color:#666;">
                            <p>${config.i18n.goodbye}</p>
                            <a href="${config.i18n.githubUrl}" target="_blank" style="text-decoration:none;font-weight:bold;color:#2271b1;">
                                <span class="dashicons dashicons-external" style="font-size:16px;vertical-align:middle;margin-right:4px;"></span>
                                ${config.i18n.githubLabel}
                            </a>
                        </div>
                        <div class="polski-footer-actions">
                            <button id="polski-submit-feedback" class="button button-primary">${config.i18n.submit}</button>
                            <button id="polski-skip-feedback" class="button button-link">${config.i18n.skip}</button>
                            <button id="polski-close-feedback" class="button">${config.i18n.close}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        const modal = $('#polski-deactivation-modal');
        const submitBtn = $('#polski-submit-feedback');
        const skipBtn = $('#polski-skip-feedback');
        const closeBtn = $('#polski-close-feedback');

        deactivationLink.on('click', function(e) {
            e.preventDefault();
            modal.fadeIn(300);
        });

        skipBtn.on('click', function() {
            modal.fadeOut(200, function() {
                window.location.href = deactivationLink.attr('href');
            });
        });

        closeBtn.on('click', function() {
            modal.fadeOut(200);
        });

        submitBtn.on('click', function() {
            const reason = modal.find('input[name="reasons"]:checked').val();
            const improvement = $('#polski-feedback-improvement').val();
            const requestedFeature = $('#polski-feedback-feature').val();

            if (!reason) {
                alert(config.i18n.validation);
                return;
            }

            submitBtn.text(config.i18n.wait).addClass('disabled').prop('disabled', true);

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: config.ajax_action,
                    nonce: config.nonce,
                    reason: reason,
                    improvement: improvement,
                    requested_feature: requestedFeature
                },
                complete: function() {
                    window.location.href = deactivationLink.attr('href');
                }
            });
        });

        modal.on('click', function(e) {
            if (e.target === this) {
                modal.fadeOut(200);
            }
        });
    });

})(jQuery);
