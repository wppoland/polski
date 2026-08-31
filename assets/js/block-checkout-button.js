/**
 * Block checkout "Place order" button label.
 *
 * The PHP `woocommerce_order_button_text` filter only reaches the classic
 * checkout. On the block checkout the label is rendered by React and resolved
 * through WooCommerce's own checkout filter registry, so a server-side gettext
 * filter can never touch it. This registers the supported filter instead.
 */
( function ( wc, config ) {
	if ( ! wc || ! wc.blocksCheckout || ! wc.blocksCheckout.registerCheckoutFilters ) {
		return;
	}

	if ( ! config || ! config.label ) {
		return;
	}

	wc.blocksCheckout.registerCheckoutFilters( 'polski', {
		placeOrderButtonLabel: function () {
			return config.label;
		},
	} );
} )( window.wc, window.polskiCheckoutButton );
