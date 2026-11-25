/**
 * Block checkout "Accept all" box.
 *
 * existing-checked: assets/js/block-checkout-button.js - that file registers a
 * checkout filter for the button label; this one syncs checkbox values and
 * loads only when the merchant switches "Accept all" on.
 *
 * A shortcut, not a consent: ticking it ticks every Polski box shown, and
 * unticking a box unticks it. Each box keeps its own value, validation and log
 * entry. Values go through the checkout data store, as React renders the boxes
 * from there and a click faked on the DOM would be undone on the next render.
 */
( function ( wp, config ) {
	if ( ! wp || ! wp.data || ! config || ! config.field || ! config.boxes ) {
		return;
	}

	var STORE = 'wc/store/checkout';
	var required = config.required || {};
	var busy = false;
	var last = null;

	// The rendered input of a box: WooCommerce ids it <location>-polski-<id>.
	function input( id ) {
		var domId = id.replace( '/', '-' );

		return document.getElementById( 'order-' + domId ) || document.getElementById( 'contact-' + domId );
	}

	// A box hidden for this cart (service, age) is not rendered, so not ticked.
	function shownBoxes() {
		return config.boxes.filter( input );
	}

	// WooCommerce keys a field's error as <location>_<id>.
	function errorKey( id ) {
		var el = input( id );

		return ( el && el.id.indexOf( 'contact-' ) === 0 ? 'contact_' : 'order_' ) + id;
	}

	function set( patch ) {
		var validation = wp.data.dispatch( 'wc/store/validation' );

		busy = true;
		wp.data.dispatch( STORE ).setAdditionalFields( patch );

		// A box sets and clears its "required" error when clicked, not when its
		// value is set from here. Without this, a box ticked by "Accept all"
		// keeps the error from when it was empty, and a required box it
		// unticks has none, so WooCommerce sends the empty value to the server
		// and announces the rejection to screen readers. The error stays hidden
		// until the shopper places the order, as for a box they unticked.
		Object.keys( patch ).forEach( function ( id ) {
			if ( ! validation || id === config.field ) {
				return;
			}

			if ( patch[ id ] ) {
				validation.clearValidationError( errorKey( id ) );
				return;
			}

			if ( required.hasOwnProperty( id ) ) {
				var errors = {};
				var label = input( id ) && input( id ).closest( 'label, div' );

				errors[ errorKey( id ) ] = {
					message: required[ id ] || ( label ? label.textContent.trim() : id ),
					hidden: true,
				};
				validation.setValidationErrors( errors );
			}
		} );
		busy = false;
	}

	wp.data.subscribe( function () {
		var store = wp.data.select( STORE );

		if ( busy || ! store || typeof store.getAdditionalFields !== 'function' ) {
			return;
		}

		var values = store.getAdditionalFields() || {};

		if ( values === last ) {
			return;
		}

		var all = !! values[ config.field ];
		// On load nothing was clicked: a value restored from the session must
		// never tick a consent, only be corrected to match the boxes.
		var wasAll = last ? !! last[ config.field ] : all;
		var boxes = shownBoxes();
		var patch = {};

		last = values;

		if ( all !== wasAll ) {
			// The shopper clicked "Accept all": follow it.
			boxes.forEach( function ( id ) {
				if ( !! values[ id ] !== all ) {
					patch[ id ] = all;
				}
			} );
		} else {
			// A box changed: "Accept all" is ticked only while every box is.
			var allTicked = boxes.length > 0 && boxes.every( function ( id ) {
				return !! values[ id ];
			} );

			if ( allTicked !== all ) {
				patch[ config.field ] = allTicked;
			}
		}

		if ( Object.keys( patch ).length > 0 ) {
			set( patch );
			last = wp.data.select( STORE ).getAdditionalFields();
		}
	} );
} )( window.wp, window.polskiAcceptAll );
