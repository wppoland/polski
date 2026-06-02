/**
 * Custom Triggers controller (vanilla TS, no framework, no external deps).
 *
 * Evaluates merchant-defined triggers and pushes the matching event into the
 * GA4 dataLayer (the same window.dataLayer the GA4 DataLayer module uses):
 *   - `page_url`: pushes once on load when the current URL contains `value`,
 *   - `click`:    pushes when an element matching `selector` is clicked
 *                 (delegated, so it also covers dynamically added elements).
 *
 * A trigger assigned a consent category only fires once that category is
 * granted by the `polski_consent` cookie (necessary always fires); pending
 * categorised triggers are re-evaluated on the `polskiConsentChange` event.
 *
 * Config is injected via the `polskiTriggers` global.
 */

export {};

interface TriggerDef {
	event: string;
	condition: 'page_url' | 'click';
	value: string;
	selector: string;
	category: string;
	params: Record<string, string | number | boolean>;
}

interface TriggersConfig {
	triggers: TriggerDef[];
	cookie: string;
	event: string;
	necessary: string;
}

declare global {
	interface Window {
		polskiTriggers?: TriggersConfig;
		dataLayer?: unknown[];
	}
}

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

function isAllowed(
	cfg: TriggersConfig,
	category: string,
	granted: string[],
): boolean {
	return category === cfg.necessary || granted.indexOf(category) !== -1;
}

function push(def: TriggerDef): void {
	window.dataLayer = window.dataLayer || [];
	window.dataLayer.push({ event: def.event, ...def.params });
}

function boot(): void {
	const cfg = window.polskiTriggers;

	if (!cfg || !Array.isArray(cfg.triggers)) {
		return;
	}

	const path = location.pathname + location.search;
	let granted = grantedCategories(cfg.cookie);

	// Page-URL triggers that are still waiting on consent.
	const pendingPageUrl: TriggerDef[] = [];

	cfg.triggers.forEach((def) => {
		if (def.condition === 'page_url') {
			if (def.value === '' || path.indexOf(def.value) === -1) {
				return;
			}

			if (isAllowed(cfg, def.category, granted)) {
				push(def);
			} else {
				pendingPageUrl.push(def);
			}
		} else if (def.condition === 'click' && def.selector !== '') {
			document.addEventListener('click', (e) => {
				const target = e.target as Element | null;

				if (!target || typeof target.closest !== 'function') {
					return;
				}

				if (!target.closest(def.selector)) {
					return;
				}

				if (isAllowed(cfg, def.category, grantedCategories(cfg.cookie))) {
					push(def);
				}
			});
		}
	});

	if (pendingPageUrl.length > 0) {
		window.addEventListener(cfg.event, (e: Event) => {
			const detail = (e as CustomEvent<{ categories?: string[] }>).detail;
			granted = Array.isArray(detail?.categories)
				? detail!.categories
				: grantedCategories(cfg.cookie);

			for (let i = pendingPageUrl.length - 1; i >= 0; i--) {
				const def = pendingPageUrl[i];

				if (isAllowed(cfg, def.category, granted)) {
					push(def);
					pendingPageUrl.splice(i, 1);
				}
			}
		});
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', boot);
} else {
	boot();
}
