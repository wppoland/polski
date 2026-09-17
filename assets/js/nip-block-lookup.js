/**
 * GUS lookup on the block checkout.
 *
 * The classic script fills #billing_company and friends by writing to the DOM.
 * Block checkout renders those inputs from a data store, so a written value is
 * overwritten on the next render. Everything here goes through the store
 * instead, which is the only way a block field keeps what it was given.
 */
(function () {
	var params = window.polskiNipBlockParams || null;

	if (!params || !window.wp || !window.wp.data) {
		return;
	}

	var WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];
	var lastLookedUp = '';
	var timer = null;

	function isValidNip(value) {
		var nip = String(value).replace(/[\s\-]/g, '');

		if (nip.length !== 10 || !/^\d{10}$/.test(nip)) {
			return false;
		}

		var sum = 0;

		for (var i = 0; i < 9; i++) {
			sum += parseInt(nip[i], 10) * WEIGHTS[i];
		}

		return sum % 11 === parseInt(nip[9], 10);
	}

	function fillBillingAddress(data) {
		var store = window.wp.data.dispatch('wc/store/cart');

		if (!store || typeof store.setBillingAddress !== 'function') {
			return;
		}

		var next = {};

		// Only fields the register actually returned, so a lookup never blanks
		// something the customer already typed.
		if (data.name) {
			next.company = data.name;
		}
		if (data.address) {
			next.address_1 = data.address;
		}
		if (data.postcode) {
			next.postcode = data.postcode;
		}
		if (data.city) {
			next.city = data.city;
		}
		if (Object.keys(next).length === 0) {
			return;
		}

		next.country = next.country || 'PL';
		store.setBillingAddress(next);
	}

	function lookup(nip) {
		if (nip === lastLookedUp) {
			return;
		}

		lastLookedUp = nip;

		var body = new FormData();
		body.append('action', 'polski_nip_lookup');
		body.append('_nonce', params.nonce);
		body.append('nip', nip);

		window
			.fetch(params.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (payload && payload.success && payload.data) {
					fillBillingAddress(payload.data);
				}
			})
			.catch(function () {
				// A failed lookup is not an error the customer has to act on:
				// the number they typed is still valid and still submitted.
				lastLookedUp = '';
			});
	}

	document.addEventListener('input', function (event) {
		var target = event.target;

		if (!target || target.id !== 'contact-polski-nip') {
			return;
		}

		var value = String(target.value || '').replace(/[\s\-]/g, '');

		window.clearTimeout(timer);

		if (!isValidNip(value)) {
			return;
		}

		timer = window.setTimeout(function () {
			lookup(value);
		}, 400);
	});
})();
