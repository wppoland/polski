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
 *
 * It acts only on the shopper's own clicks (change events), never on store
 * updates. WooCommerce sends each changed checkout field to the server shortly
 * after it changes, skipping only fields that carry a validation error, and a
 * required box has no error until it is clicked. Reacting to store updates
 * (1.50.0) meant any update while the form was loading could start a write
 * that unticked the required boxes; WooCommerce sent them, the server answered
 * 400 and the checkout showed an error banner before anyone clicked.
 */
( function ( wp, config ) {
	if ( ! wp || ! wp.data || ! config || ! config.field || ! config.boxes ) {
		return;
	}

	var STORE = 'wc/store/checkout';
	var required = config.required || {};

	// The rendered input of a field: WooCommerce ids it <location>-polski-<id>.
	function input( id ) {
		var domId = id.replace( '/', '-' );

		return document.getElementById( 'order-' + domId ) || document.getElementById( 'contact-' + domId );
	}

	// WooCommerce keys a field's error as <location>_<id>.
	function errorKey( id ) {
		var el = input( id );

		return ( el && el.id.indexOf( 'contact-' ) === 0 ? 'contact_' : 'order_' ) + id;
	}

	// A box hidden for this cart (service, age) is not rendered, so not ticked.
	function shownBoxes() {
		return config.boxes.filter( input );
	}

	/**
	 * Set field values, leaving each box's "required" error as a click would:
	 * cleared when ticked, set (hidden until the order is placed) when a
	 * required box is unticked. The error goes in first, so WooCommerce never
	 * holds an unticked required box without one and never sends it.
	 */
	function set( patch ) {
		var validation = wp.data.dispatch( 'wc/store/validation' );
		var errors = {};

		Object.keys( patch ).forEach( function ( id ) {
			if ( id === config.field ) {
				return;
			}

			if ( patch[ id ] ) {
				validation.clearValidationError( errorKey( id ) );
			} else if ( required.hasOwnProperty( id ) ) {
				var el = input( id );
				var label = el && el.closest( 'label, div' );

				errors[ errorKey( id ) ] = {
					message: required[ id ] || ( label ? label.textContent.trim() : id ),
					hidden: true,
				};
			}
		} );

		if ( Object.keys( errors ).length > 0 ) {
			validation.setValidationErrors( errors );
		}

		wp.data.dispatch( STORE ).setAdditionalFields( patch );
	}

	// Runs after WooCommerce's own handler has stored the clicked value.
	function onChange( id ) {
		var current = wp.data.select( STORE ).getAdditionalFields() || {};
		var boxes = shownBoxes();
		var patch = {};

		if ( id === config.field ) {
			var all = !! current[ config.field ];

			boxes.forEach( function ( box ) {
				if ( !! current[ box ] !== all ) {
					patch[ box ] = all;
				}
			} );
		} else if ( config.boxes.indexOf( id ) !== -1 ) {
			var allTicked = boxes.length > 0 && boxes.every( function ( box ) {
				return !! current[ box ];
			} );

			if ( allTicked !== !! current[ config.field ] ) {
				patch[ config.field ] = allTicked;
			}
		}

		if ( Object.keys( patch ).length > 0 ) {
			set( patch );
		}
	}

	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		var match = target && target.type === 'checkbox' ? /^(?:order|contact)-polski-(.+)$/.exec( target.id || '' ) : null;

		if ( ! match ) {
			return;
		}

		var id = 'polski/' + match[ 1 ];

		// React stores the value in its own change handler; read it after.
		window.setTimeout( function () {
			onChange( id );
		}, 0 );
	} );
} )( window.wp, window.polskiAcceptAll );
