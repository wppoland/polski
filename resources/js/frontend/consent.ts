/**
 * Consent Manager banner controller (vanilla TS, no framework, no external deps).
 *
 * On first visit it shows a cookie-consent banner with the categories the store
 * owner enabled. On a choice it:
 *   (a) writes the `polski_consent` cookie,
 *   (b) POSTs the decision to the REST recorder with the wp_rest nonce,
 *   (c) calls gtag('consent','update', ...) for Google Consent Mode v2,
 *   (d) activates any <script type="text/plain" data-polski-consent="CATEGORY">
 *       node whose category is now granted by swapping it to an executable
 *       script ('necessary' always activates),
 *   (e) dispatches the `polskiConsentChange` window event.
 *
 * Accessibility: dialog role, focus trap while open, Escape closes (declines
 * the optional categories without recording), keyboard reachable controls.
 *
 * The runtime config (categories, cookie/event names, version hash, REST URL,
 * nonce, Consent Mode map) is injected via the `polskiConsent` global by the
 * PHP enqueue.
 */

export {};

interface ConsentConfig {
	cookie: string;
	event: string;
	version: string;
	categories: string[];
	consentMode: boolean;
	consentModeMap: Record<string, string[]>;
	restUrl: string;
	nonce: string;
}

type ConsentState = 'granted' | 'denied';

declare global {
	interface Window {
		polskiConsent?: ConsentConfig;
		gtag?: (...args: unknown[]) => void;
	}
}

const NECESSARY = 'necessary';

function readCookie(name: string): string | null {
	const escaped = name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1');
	const match = document.cookie.match(
		new RegExp('(?:^|; )' + escaped + '=([^;]*)'),
	);

	return match ? decodeURIComponent(match[1]) : null;
}

function writeCookie(name: string, value: string): void {
	const expires = new Date();
	expires.setTime(expires.getTime() + 180 * 24 * 60 * 60 * 1000);
	const secure = location.protocol === 'https:' ? '; Secure' : '';
	document.cookie =
		name +
		'=' +
		encodeURIComponent(value) +
		'; expires=' +
		expires.toUTCString() +
		'; path=/; SameSite=Lax' +
		secure;
}

/**
 * Granted optional categories stored in the cookie, or null if undecided.
 */
function storedCategories(cfg: ConsentConfig): string[] | null {
	const raw = readCookie(cfg.cookie);

	if (!raw) {
		return null;
	}

	try {
		const parsed = JSON.parse(raw) as { categories?: unknown };

		if (Array.isArray(parsed.categories)) {
			return parsed.categories.filter(
				(c): c is string => typeof c === 'string',
			);
		}
	} catch {
		/* malformed cookie, treat as undecided */
	}

	return null;
}

function applyConsentMode(cfg: ConsentConfig, granted: string[]): void {
	if (!cfg.consentMode || typeof window.gtag !== 'function') {
		return;
	}

	const payload: Record<string, ConsentState> = {};

	Object.keys(cfg.consentModeMap).forEach((category) => {
		const state: ConsentState =
			granted.indexOf(category) !== -1 ? 'granted' : 'denied';
		cfg.consentModeMap[category].forEach((signal) => {
			payload[signal] = state;
		});
	});

	window.gtag('consent', 'update', payload);
}

/**
 * Swap every consent-gated placeholder whose category is now granted to a real
 * executable script. 'necessary' always activates. Already-activated nodes are
 * untouched because they are no longer type="text/plain".
 */
function activateGated(granted: string[]): void {
	const nodes = document.querySelectorAll<HTMLScriptElement>(
		'script[type="text/plain"][data-polski-consent]',
	);

	nodes.forEach((node) => {
		const category = node.getAttribute('data-polski-consent');

		if (
			category === null ||
			(category !== NECESSARY && granted.indexOf(category) === -1)
		) {
			return;
		}

		const replacement = document.createElement('script');

		// Copy any extra attributes (e.g. async, type overrides handled below).
		Array.prototype.forEach.call(node.attributes, (attr: Attr) => {
			if (
				attr.name === 'type' ||
				attr.name === 'data-polski-consent' ||
				attr.name === 'data-src'
			) {
				return;
			}
			replacement.setAttribute(attr.name, attr.value);
		});

		const src = node.getAttribute('data-src');

		if (src) {
			replacement.src = src;
		} else {
			replacement.text = node.textContent ?? '';
		}

		node.parentNode?.replaceChild(replacement, node);
	});
}

function fireEvent(cfg: ConsentConfig, granted: string[]): void {
	try {
		window.dispatchEvent(
			new CustomEvent(cfg.event, { detail: { categories: granted } }),
		);
	} catch {
		/* CustomEvent unsupported (very old browsers); silently skip */
	}
}

function persist(cfg: ConsentConfig, granted: string[]): void {
	if (!cfg.restUrl) {
		return;
	}

	try {
		void fetch(cfg.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			credentials: 'same-origin',
			keepalive: true,
			body: JSON.stringify({ categories: granted, version: cfg.version }),
		}).catch(() => {
			/* recording is best-effort */
		});
	} catch {
		/* fetch unavailable */
	}
}

class ConsentBanner {
	private readonly cfg: ConsentConfig;
	private readonly el: HTMLElement;
	private readonly categoriesBox: HTMLElement | null;
	private readonly saveBtn: HTMLElement | null;
	private lastFocused: Element | null = null;
	private trapHandler: ((e: KeyboardEvent) => void) | null = null;

	constructor(cfg: ConsentConfig, el: HTMLElement) {
		this.cfg = cfg;
		this.el = el;
		this.categoriesBox = el.querySelector(
			'.polski-consent-banner__categories',
		);
		this.saveBtn = el.querySelector('[data-polski-consent-action="save"]');

		el.addEventListener('click', (e) => this.onClick(e));
	}

	init(): void {
		const existing = storedCategories(this.cfg);

		if (existing) {
			// Already decided: silently re-apply (no banner, no re-record).
			this.commit(existing, false, false);
			this.syncCheckboxes(existing);
			return;
		}

		this.syncCheckboxes(null);
		this.open();
	}

	private onClick(e: Event): void {
		const target = e.target as HTMLElement | null;
		const btn = target?.closest<HTMLElement>(
			'[data-polski-consent-action]',
		);

		if (!btn) {
			return;
		}

		switch (btn.getAttribute('data-polski-consent-action')) {
			case 'accept':
				this.commit(this.cfg.categories.slice(), true, true);
				break;
			case 'reject':
				this.commit([], true, true);
				break;
			case 'manage':
				this.showManage();
				break;
			case 'save':
				this.commit(this.selected(), true, true);
				break;
		}
	}

	private selected(): string[] {
		const out: string[] = [];
		this.el
			.querySelectorAll<HTMLInputElement>(
				'[data-polski-consent-category]',
			)
			.forEach((cb) => {
				if (cb.checked) {
					out.push(cb.value);
				}
			});

		return out;
	}

	private syncCheckboxes(granted: string[] | null): void {
		this.el
			.querySelectorAll<HTMLInputElement>(
				'[data-polski-consent-category]',
			)
			.forEach((cb) => {
				cb.checked = granted !== null && granted.indexOf(cb.value) !== -1;
			});
	}

	private showManage(): void {
		this.categoriesBox?.removeAttribute('hidden');
		this.saveBtn?.removeAttribute('hidden');
		const first = this.categoriesBox?.querySelector<HTMLInputElement>(
			'input[type="checkbox"]:not([disabled])',
		);
		first?.focus();
	}

	/**
	 * Apply a decision. When `record` is false the cookie is still (re)written
	 * but the REST recorder is not called and the banner is assumed already
	 * hidden (page-load re-apply path).
	 */
	private commit(granted: string[], visible: boolean, record: boolean): void {
		if (granted.indexOf(NECESSARY) === -1) {
			granted.unshift(NECESSARY);
		}

		writeCookie(
			this.cfg.cookie,
			JSON.stringify({ categories: granted, version: this.cfg.version }),
		);
		applyConsentMode(this.cfg, granted);
		activateGated(granted);
		fireEvent(this.cfg, granted);

		if (record) {
			persist(this.cfg, granted);
		}

		if (visible) {
			this.close();
		}
	}

	private open(): void {
		this.lastFocused = document.activeElement;
		this.el.removeAttribute('hidden');

		this.trapHandler = (e: KeyboardEvent) => this.onKeydown(e);
		document.addEventListener('keydown', this.trapHandler, true);

		const focusable = this.focusable();
		(focusable[0] ?? this.el).focus();
	}

	private close(): void {
		this.el.setAttribute('hidden', '');

		if (this.trapHandler) {
			document.removeEventListener('keydown', this.trapHandler, true);
			this.trapHandler = null;
		}

		if (this.lastFocused instanceof HTMLElement) {
			this.lastFocused.focus();
		}
	}

	private focusable(): HTMLElement[] {
		return Array.prototype.slice.call(
			this.el.querySelectorAll<HTMLElement>(
				'button:not([hidden]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])',
			),
		).filter((node: HTMLElement) => node.offsetParent !== null);
	}

	private onKeydown(e: KeyboardEvent): void {
		if (this.el.hasAttribute('hidden')) {
			return;
		}

		if (e.key === 'Escape') {
			// Escape = decline optional categories (an explicit, recordable choice).
			e.preventDefault();
			this.commit([], true, true);
			return;
		}

		if (e.key !== 'Tab') {
			return;
		}

		const nodes = this.focusable();

		if (nodes.length === 0) {
			return;
		}

		const first = nodes[0];
		const last = nodes[nodes.length - 1];
		const active = document.activeElement;

		if (e.shiftKey && active === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && active === last) {
			e.preventDefault();
			first.focus();
		}
	}
}

function boot(): void {
	const cfg = window.polskiConsent;

	if (!cfg) {
		return;
	}

	const el = document.getElementById('polski-consent-banner');

	if (!el) {
		return;
	}

	new ConsentBanner(cfg, el).init();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', boot);
} else {
	boot();
}
