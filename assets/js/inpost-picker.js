/**
 * InPost parcel locker picker, shared by the classic and the block checkout.
 *
 * existing-checked: assets/js/nip-block-lookup.js - NIP lookup only; its store
 * writes cover billing fields, not an order-level additional field.
 *
 * The browser never calls InPost (it refuses cross-origin requests); the list
 * comes from the shop's own REST route. Both checkouts re-render the area the
 * picker lives in (classic: the order review fragment; block: React), so the
 * chosen locker is kept here and the picker is mounted again whenever its
 * place reappears.
 */
(function () {
	var params = window.polskiInpostParams || null;

	if (!params || !window.fetch) {
		return;
	}

	var t = params.i18n || {};
	var chosen = { code: '', point: null };
	var lastPoints = [];
	var uid = 0;

	function node(tag, className, text) {
		var el = document.createElement(tag);
		if (className) {
			el.className = className;
		}
		if (text) {
			el.textContent = text;
		}
		return el;
	}

	function normalizePostcode(value) {
		var m = String(value || '').trim().match(/^(\d{2})-?(\d{3})$/);
		return m ? m[1] + '-' + m[2] : '';
	}

	// ----- where the shopper's postcode and the chosen value live -----

	function blockStores() {
		if (!window.wp || !window.wp.data) {
			return null;
		}
		var select = window.wp.data.select;
		return select('wc/store/cart') && select('wc/store/checkout') ? window.wp.data : null;
	}

	function shopperPostcode(kind) {
		// The classic page can load the block stores too (mini cart), so the
		// picker's own checkout decides where the postcode comes from.
		var data = kind === 'block' ? blockStores() : null;
		if (data) {
			var customer = data.select('wc/store/cart').getCustomerData();
			return (customer && customer.shippingAddress && customer.shippingAddress.postcode) || '';
		}
		var different = document.getElementById('ship-to-different-address-checkbox');
		var field = document.getElementById(different && different.checked ? 'shipping_postcode' : 'billing_postcode');
		return field ? field.value : '';
	}

	function blockLockerSelected(data) {
		var cart = data.select('wc/store/cart');
		var packages = typeof cart.getShippingRates === 'function' ? cart.getShippingRates() : [];
		return (packages || []).some(function (pkg) {
			return (pkg.shipping_rates || []).some(function (rate) {
				return rate.selected && rate.method_id === params.method;
			});
		});
	}

	function storeChoice(root) {
		var hidden = root.querySelector('input[type="hidden"]');
		if (hidden) {
			hidden.value = chosen.code;
			return;
		}
		var data = blockStores();
		if (!data) {
			return;
		}
		var current = data.select('wc/store/checkout').getAdditionalFields() || {};
		var next = Object.assign({}, current);
		next[params.fieldId] = chosen.code;
		data.dispatch('wc/store/checkout').setAdditionalFields(next);
	}

	// ----- the picker -----

	function renderList(ui, points) {
		ui.list.textContent = '';
		points.forEach(function (point) {
			var item = node('li');
			var button = node('button', 'polski-inpost-picker__point');
			button.type = 'button';
			button.setAttribute('aria-pressed', point.code === chosen.code ? 'true' : 'false');
			button.appendChild(node('strong', '', point.code));
			if (point.address) {
				button.appendChild(node('span', '', point.address));
			}
			if (point.description) {
				button.appendChild(node('span', 'polski-inpost-picker__meta', point.description));
			}
			if (point.hours) {
				button.appendChild(node('span', 'polski-inpost-picker__meta', (t.hours || '') + ' ' + point.hours));
			}
			button.addEventListener('click', function () {
				chosen = { code: point.code, point: point };
				storeChoice(ui.root);
				render(ui, false);
				ui.toggle.focus();
			});
			item.appendChild(button);
			ui.list.appendChild(item);
		});
	}

	function search(ui) {
		var postcode = normalizePostcode(ui.input.value);

		if (!postcode) {
			ui.status.textContent = t.invalidPostcode || '';
			return;
		}

		ui.status.textContent = t.searching || '';
		ui.list.textContent = '';

		window
			// Plain permalinks give index.php?rest_route=..., which already has a query.
			.fetch(params.restUrl + (params.restUrl.indexOf('?') === -1 ? '?' : '&') + 'postcode=' + encodeURIComponent(postcode), {
				credentials: 'same-origin',
			})
			.then(function (response) {
				return response.json().then(function (body) {
					return { ok: response.ok, body: body };
				});
			})
			.then(function (result) {
				if (!result.ok) {
					ui.status.textContent = (result.body && result.body.message) || t.error || '';
					return;
				}
				lastPoints = (result.body && result.body.points) || [];
				ui.status.textContent = lastPoints.length
					? String(t.found || '%d').replace('%d', String(lastPoints.length))
					: t.none || '';
				renderList(ui, lastPoints);
			})
			.catch(function () {
				ui.status.textContent = t.error || '';
			});
	}

	function render(ui, open) {
		ui.summary.hidden = !chosen.code;
		ui.summaryText.textContent = chosen.code + (chosen.point && chosen.point.address ? ', ' + chosen.point.address : '');
		ui.toggle.textContent = chosen.code ? t.change || '' : t.choose || '';
		ui.panel.hidden = !open;
		ui.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	function mount(root) {
		root.setAttribute('data-polski-inpost-ready', '1');
		uid += 1;

		var ui = { root: root };
		var box = node('div', 'polski-inpost-picker__ui');
		var inputId = 'polski-inpost-postcode-' + uid;
		var panelId = 'polski-inpost-panel-' + uid;

		ui.summary = node('p', 'polski-inpost-picker__chosen');
		ui.summary.appendChild(node('span', '', (t.chosen || '') + ' '));
		ui.summaryText = node('strong');
		ui.summary.appendChild(ui.summaryText);

		ui.toggle = node('button', 'button polski-inpost-picker__toggle');
		ui.toggle.type = 'button';
		ui.toggle.setAttribute('aria-controls', panelId);

		ui.panel = node('div', 'polski-inpost-picker__panel');
		ui.panel.id = panelId;

		var row = node('div', 'polski-inpost-picker__search');
		var label = node('label', '', t.postcode || '');
		label.htmlFor = inputId;
		ui.input = node('input', 'input-text');
		ui.input.id = inputId;
		ui.input.type = 'text';
		ui.input.inputMode = 'numeric';
		ui.input.autocomplete = 'postal-code';
		// A postcode is an identifier: no autocorrect, and Enter runs the search.
		ui.input.enterKeyHint = 'search';
		ui.input.spellcheck = false;
		ui.input.setAttribute('autocorrect', 'off');
		ui.input.setAttribute('autocapitalize', 'none');
		ui.input.maxLength = 6;
		ui.input.value = normalizePostcode(shopperPostcode(root.getAttribute('data-polski-inpost')));
		var go = node('button', 'button', t.search || '');
		go.type = 'button';
		row.appendChild(label);
		row.appendChild(ui.input);
		row.appendChild(go);

		ui.status = node('p', 'polski-inpost-picker__status');
		ui.status.setAttribute('role', 'status');
		ui.status.setAttribute('aria-live', 'polite');
		ui.list = node('ul', 'polski-inpost-picker__list');

		ui.panel.appendChild(row);
		ui.panel.appendChild(ui.status);
		ui.panel.appendChild(ui.list);
		box.appendChild(ui.summary);
		box.appendChild(ui.toggle);
		box.appendChild(ui.panel);
		root.appendChild(box);

		go.addEventListener('click', function () {
			search(ui);
		});
		// Inside the checkout form Enter would place the order.
		ui.input.addEventListener('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				search(ui);
			}
		});
		ui.toggle.addEventListener('click', function () {
			var open = ui.panel.hidden;
			render(ui, open);
			if (open) {
				ui.input.focus();
				if (!ui.list.children.length && normalizePostcode(ui.input.value)) {
					search(ui);
				}
			}
		});

		storeChoice(root);
		root.polskiInpostUi = ui;

		// Nothing chosen yet: open straight away with the nearest lockers.
		render(ui, !chosen.code);
		if (!chosen.code && ui.input.value) {
			if (lastPoints.length) {
				renderList(ui, lastPoints);
			} else {
				search(ui);
			}
		}
	}

	// ----- mounting on both checkouts -----

	function syncClassic() {
		document.querySelectorAll('[data-polski-inpost="classic"]:not([data-polski-inpost-ready])').forEach(mount);
	}

	function syncBlock() {
		var data = blockStores();
		var input = document.getElementById('order-' + params.fieldId.replace(/[^a-z0-9]+/gi, '-'));

		if (!data) {
			return;
		}

		var wrapper = input ? input.closest('.wc-block-components-text-input') || input.parentNode : null;
		var root = wrapper && wrapper.nextElementSibling && wrapper.nextElementSibling.getAttribute('data-polski-inpost') === 'block'
			? wrapper.nextElementSibling
			: null;
		var selected = !!input && blockLockerSelected(data);

		// A picker left behind by a re-render, or by the field's own rule
		// removing it, must not linger next to another rate.
		document.querySelectorAll('[data-polski-inpost="block"]').forEach(function (el) {
			if (el !== root || !selected) {
				el.remove();
			}
		});

		if (!input) {
			return;
		}

		// WooCommerce without conditional field rules shows the field for every
		// rate; hide it here unless the locker is the selected rate.
		wrapper.style.display = selected ? '' : 'none';

		if (selected && !root) {
			if (!chosen.code && input.value) {
				chosen = { code: String(input.value).toUpperCase(), point: null };
			}
			root = node('div', 'polski-inpost-picker');
			root.setAttribute('data-polski-inpost', 'block');
			wrapper.parentNode.insertBefore(root, wrapper.nextSibling);
			mount(root);
		}
	}

	var queued = false;
	var subscribed = false;

	function schedule() {
		if (!queued) {
			queued = true;
			window.requestAnimationFrame(sync);
		}
	}

	// The picker can mount before the shopper types a postcode: fill it in
	// once the address has one, unless the shopper is editing the field.
	function prefill() {
		document.querySelectorAll('[data-polski-inpost-ready]').forEach(function (root) {
			var ui = root.polskiInpostUi;
			var postcode = ui ? normalizePostcode(shopperPostcode(root.getAttribute('data-polski-inpost'))) : '';
			if (!ui || ui.input.value || !postcode || document.activeElement === ui.input) {
				return;
			}
			ui.input.value = postcode;
			if (!ui.panel.hidden && !ui.list.children.length) {
				search(ui);
			}
		});
	}

	function sync() {
		queued = false;
		// Picking another rate on the block checkout changes the store, not
		// always the DOM. The stores may register after this script runs.
		if (!subscribed && blockStores()) {
			subscribed = true;
			window.wp.data.subscribe(schedule);
		}
		syncClassic();
		syncBlock();
		prefill();
	}

	new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });

	sync();
})();
