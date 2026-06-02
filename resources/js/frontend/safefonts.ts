/**
 * Safe Fonts controller (vanilla TS, no framework, no external deps).
 *
 * When the Safe Fonts module gates Google Fonts behind consent, each font
 * stylesheet is rendered as a disabled placeholder:
 *
 *   <link rel="stylesheet" href="about:blank"
 *         data-polski-safefont="REAL_URL"
 *         data-polski-consent="CATEGORY" media="print" disabled>
 *
 * This controller re-enables every placeholder whose category is granted by the
 * `polski_consent` cookie (necessary always activates), and re-checks on the
 * Consent Manager's `polskiConsentChange` event. Until consent is granted the
 * external fonts.googleapis.com request is not made.
 *
 * Config (cookie + event names) is injected via the `polskiSafeFonts` global.
 */

export {};

interface SafeFontsConfig {
	event: string;
	cookie: string;
}

declare global {
	interface Window {
		polskiSafeFonts?: SafeFontsConfig;
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

function grantedCategories(cookie: string): string[] {
	const raw = readCookie(cookie);

	if (!raw) {
		return [];
	}

	try {
		const parsed = JSON.parse(raw) as { categories?: unknown };

		if (Array.isArray(parsed.categories)) {
			return parsed.categories.filter(
				(c): c is string => typeof c === 'string',
			);
		}
	} catch {
		/* malformed cookie, treat as no consent */
	}

	return [];
}

function activate(granted: string[]): void {
	const nodes = document.querySelectorAll<HTMLLinkElement>(
		'link[data-polski-safefont][data-polski-consent]',
	);

	nodes.forEach((node) => {
		const category = node.getAttribute('data-polski-consent');
		const href = node.getAttribute('data-polski-safefont');

		if (
			!href ||
			category === null ||
			(category !== NECESSARY && granted.indexOf(category) === -1)
		) {
			return;
		}

		// Flip the placeholder into a live stylesheet.
		node.media = 'all';
		node.href = href;
		node.removeAttribute('disabled');
		node.removeAttribute('data-polski-safefont');
	});
}

function boot(): void {
	const cfg = window.polskiSafeFonts;

	if (!cfg) {
		return;
	}

	activate(grantedCategories(cfg.cookie));

	window.addEventListener(cfg.event, (e: Event) => {
		const detail = (e as CustomEvent<{ categories?: string[] }>).detail;
		const granted = Array.isArray(detail?.categories)
			? detail!.categories
			: grantedCategories(cfg.cookie);
		activate(granted);
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', boot);
} else {
	boot();
}
