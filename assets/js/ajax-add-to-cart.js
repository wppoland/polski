/**
 * Polski AJAX Add to Cart - handles variable products and single product pages.
 */
(function ($) {
    'use strict';

    if (typeof polskiAjaxCart === 'undefined') {
        return;
    }

    var config = polskiAjaxCart;

    // Handle single product page add-to-cart via AJAX.
    $(document).on('submit', 'form.cart', function (e) {
        var $form = $(this);
        var $button = $form.find('[type="submit"]');

        // Skip external/grouped products or if AJAX is not appropriate.
        if ($form.find('.single_add_to_cart_button').hasClass('disabled')) {
            return;
        }

        // Only intercept on single product pages.
        if (!$('body').hasClass('single-product')) {
            return;
        }

        e.preventDefault();

        var formData = new FormData($form[0]);
        formData.append('action', 'polski_ajax_add_to_cart');
        formData.append('security', config.nonce);

        // Extract product_id from hidden input or button.
        if (!formData.get('product_id')) {
            var productId = $button.val() || $form.find('input[name="product_id"]').val();
            if (productId) {
                formData.append('product_id', productId);
            }
        }

        $button.addClass('loading').prop('disabled', true);

        $(document.body).trigger('polski_adding_to_cart', [$button, formData]);

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.error) {
                    showNotice(response.data ? response.data.message : config.i18n.error, 'error');
                    return;
                }

                // Replace cart fragments.
                if (response.fragments) {
                    $.each(response.fragments, function (key, value) {
                        $(key).replaceWith(value);
                    });
                }

                $button.removeClass('loading').addClass('added');

                showNotice(
                    config.i18n.added + ' <a href="' + config.cartUrl + '">' + config.i18n.viewCart + '</a>',
                    'success'
                );

                $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $button]);
                $(document.body).trigger('polski_added_to_cart', [$button, response]);

                setTimeout(function () {
                    $button.removeClass('added');
                }, 2000);
            },
            error: function () {
                showNotice(config.i18n.error, 'error');
            },
            complete: function () {
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });

    function showNotice(message, type) {
        var $notice = $('<div class="polski-ajax-cart-notice' + (type === 'error' ? ' error' : '') + '">' + message + '</div>');
        $('body').append($notice);

        setTimeout(function () {
            $notice.fadeOut(300, function () {
                $(this).remove();
            });
        }, 4000);
    }

})(jQuery);
