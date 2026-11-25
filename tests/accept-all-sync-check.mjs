// existing-checked: tests/admin-menu-icon-check.mjs - that check reads a CSS
// file; this one runs the block checkout "Accept all" script against a stand-in
// for WooCommerce's data stores, which nothing else in the repo exercises.
//
// 1.50.0: with "Accept all" on, the block checkout showed "polski/privacy is
// not equal to 1" and a 400 from the Store API about 2.5 s after load, with
// no click. WooCommerce sends every changed checkout field to the server,
// skipping only fields that carry a validation error, and a required box has
// no error until it is clicked. The script watched the store, so store updates
// made while the form loaded could make it untick required boxes, which
// WooCommerce then sent. Run against 1.50.0's script, case 1 fails.
//
// The stand-in below sends what WooCommerce would send and fails on any
// unticked required box in it.
//
// Run: node tests/accept-all-sync-check.mjs [path/to/script.js]
import { readFileSync } from 'node:fs';
import assert from 'node:assert';
import vm from 'node:vm';

const file = process.argv[2] ?? new URL('../assets/js/block-checkout-accept-all.js', import.meta.url);
const source = readFileSync(file, 'utf8');

const REQUIRED = { 'polski/terms': 'Accept the terms.', 'polski/privacy': 'Accept the privacy policy.' };
const BOXES = ['polski/terms', 'polski/privacy', 'polski/marketing'];
const FIELD = 'polski/accept_all';

function checkout(initialFields) {
	let fields = { ...initialFields };
	let errors = {};
	const listeners = [];
	const changeListeners = [];
	const sent = [];
	const notify = () => listeners.slice().forEach((l) => l());

	// The fields WooCommerce would send now, as its draft sync filters them.
	const synced = { ...initialFields };
	const push = () => {
		const out = {};
		for (const [k, v] of Object.entries(fields)) {
			const key = (k === 'polski/marketing' ? 'contact_' : 'order_') + k;
			if (!errors[key] && synced[k] !== v) {
				out[k] = v;
				synced[k] = v;
			}
		}
		if (Object.keys(out).length) sent.push(out);
	};

	const inputs = {};
	for (const id of [FIELD, ...BOXES]) {
		const domId = (id === 'polski/marketing' ? 'contact-' : 'order-') + id.replace('/', '-');
		inputs[domId] = { id: domId, type: 'checkbox', closest: () => ({ textContent: id }) };
	}

	const wp = {
		data: {
			subscribe: (l) => listeners.push(l),
			select: (store) => (store === 'wc/store/checkout'
				? { getAdditionalFields: () => fields }
				: { getValidationErrors: () => errors }),
			dispatch: (store) => (store === 'wc/store/checkout'
				? { setAdditionalFields: (patch) => { fields = { ...fields, ...patch }; notify(); } }
				: {
					clearValidationError: (k) => { const { [k]: _, ...rest } = errors; errors = rest; notify(); },
					setValidationErrors: (e) => { errors = { ...errors, ...e }; notify(); },
				}),
		},
	};

	const timers = [];
	const context = {
		window: { setTimeout: (fn) => timers.push(fn) },
		document: {
			getElementById: (id) => inputs[id] ?? null,
			addEventListener: (type, fn) => type === 'change' && changeListeners.push(fn),
		},
	};
	context.window.wp = wp;
	context.window.polskiAcceptAll = { field: FIELD, boxes: BOXES, required: REQUIRED };
	vm.runInNewContext(source, context);

	const flush = () => { while (timers.length) timers.shift()(); };

	return {
		sent,
		push,
		fields: () => fields,
		errors: () => errors,
		// What WooCommerce does when its own code updates the store.
		storeUpdate: (patch) => { fields = { ...fields, ...patch }; notify(); flush(); },
		// A re-render (a cart update) drops the boxes' validation errors.
		remount: () => { errors = {}; notify(); flush(); },
		// The shopper clicks a box: WooCommerce stores the value and, for a
		// required box being unticked, sets its error.
		click: (id) => {
			const key = (id === 'polski/marketing' ? 'contact_' : 'order_') + id;
			const next = !fields[id];
			if (REQUIRED[id]) {
				if (next) { const { [key]: _, ...rest } = errors; errors = rest; } else { errors = { ...errors, [key]: { message: 'x', hidden: true } }; }
			}
			fields = { ...fields, [id]: next };
			notify();
			const domId = (id === 'polski/marketing' ? 'contact-' : 'order-') + id.replace('/', '-');
			changeListeners.forEach((fn) => fn({ target: inputs[domId] }));
			flush();
		},
	};
}

const rejected = (sent) => sent.flatMap((p) => Object.entries(p).filter(([k, v]) => REQUIRED[k] && v === false).map(([k]) => k));

// 1. The case that shipped in 1.50.0. After an order, WooCommerce keeps the
// ticked boxes in the session and Polski drops "Accept all" from it. On the
// next checkout the old script saw every box ticked and ticked "Accept all";
// WooCommerce then put back the session value (empty) when the cart updated
// (Bileto adds a ticket on load), the script read that as the shopper
// unticking "Accept all" and unticked every box, the re-render dropped their
// errors, and WooCommerce sent terms and privacy as unticked.
{
	const c = checkout({ 'polski/terms': true, 'polski/privacy': true, 'polski/marketing': true });
	c.storeUpdate({ [FIELD]: false });
	c.remount();
	c.storeUpdate({ [FIELD]: undefined });
	c.remount();
	c.push();
	assert.deepStrictEqual(rejected(c.sent), [], `an unticked required box was sent on load: ${JSON.stringify(c.sent)}`);
	assert.strictEqual(c.fields()['polski/terms'], true, 'a consent changed without a click');
	assert.ok(!c.fields()[FIELD], 'Accept all ticked itself without a click');
	assert.deepStrictEqual(c.sent, [], 'the script wrote to the store without a click');
}

// 2. Accept all ticks and unticks every box; nothing unticked is ever sent.
{
	const c = checkout({});
	c.click(FIELD);
	assert.ok(BOXES.every((id) => c.fields()[id] === true), 'Accept all did not tick every box');
	assert.deepStrictEqual(Object.keys(c.errors()), [], 'ticked boxes kept their errors');
	c.push();
	c.click(FIELD);
	assert.ok(BOXES.every((id) => c.fields()[id] === false), 'Accept all did not untick every box');
	assert.ok(c.errors()['order_polski/terms']?.hidden, 'an unticked required box has no hidden error');
	c.push();
	assert.deepStrictEqual(rejected(c.sent), [], `Accept all sent an unticked required box: ${JSON.stringify(c.sent)}`);
}

// 3. Unticking one box unticks Accept all; ticking it again ticks Accept all.
{
	const c = checkout({});
	c.click(FIELD);
	c.click('polski/privacy');
	assert.strictEqual(c.fields()[FIELD], false, 'Accept all stayed ticked with a box unticked');
	c.click('polski/privacy');
	assert.strictEqual(c.fields()[FIELD], true, 'Accept all did not follow the boxes');
}

console.log('OK: Accept all acts on clicks only and never sends an unticked required box.');
