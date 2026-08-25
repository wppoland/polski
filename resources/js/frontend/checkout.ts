/**
 * Frontend checkout enhancements.
 *
 * Handles:
 * - Legal checkbox client-side validation feedback
 * - Smooth transitions when AJAX fragments replace checkbox container
 * - Accessibility: focus management after fragment refresh
 * - NIP validation and GUS company data auto-fill
 */

declare const jQuery: any;
declare const polskiCheckoutParams: { ajaxUrl?: string; nipNonce?: string } | undefined;

(function () {
    const CONTAINER_SELECTOR = '.polski-legal-checkboxes';
    const CHECKBOX_SELECTOR = '.polski-checkbox input[type="checkbox"]';
    const ERROR_CLASS = 'polski-checkbox--error';
    const NIP_INPUT_SELECTOR = 'input[data-polski-nip-field="1"], #billing_nip, input[name="billing_nip"]';

    /**
     * Add inline validation feedback on required checkboxes.
     */
    function initValidation(): void {
        document.querySelectorAll<HTMLInputElement>(CHECKBOX_SELECTOR).forEach((input) => {
            if (!input.required) return;

            input.addEventListener('change', () => {
                const row = input.closest('.polski-checkbox');
                if (!row) return;

                if (input.checked) {
                    row.classList.remove(ERROR_CLASS);
                } else {
                    row.classList.add(ERROR_CLASS);
                }
            });
        });
    }

    /**
     * NIP validation checksum algorithm per Polish tax law.
     */
    function isValidNip(nip: string): boolean {
        const clean = nip.replace(/[\s\-]/g, '');
        if (clean.length !== 10 || !/^\d{10}$/.test(clean)) return false;
        const weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            sum += parseInt(clean[i], 10) * weights[i];
        }
        return (sum % 11) === parseInt(clean[9], 10);
    }

    /**
     * NIP auto-fill via GUS REGON AJAX endpoint.
     */
    function initNipAutofill(): void {
        let debounceTimer: ReturnType<typeof setTimeout> | null = null;
        let lastLookedUpNip = '';

        function performLookup(input: HTMLInputElement): void {
            const raw = input.value || '';
            const nip = raw.replace(/[\s\-]/g, '');
            if (nip.length !== 10 || !isValidNip(nip) || nip === lastLookedUpNip) {
                return;
            }

            const ajaxUrl = (typeof polskiCheckoutParams !== 'undefined' && polskiCheckoutParams?.ajaxUrl)
                ? polskiCheckoutParams.ajaxUrl
                : ((window as any).ajaxurl || '/wp-admin/admin-ajax.php');
            const nonce = (typeof polskiCheckoutParams !== 'undefined' && polskiCheckoutParams?.nipNonce)
                ? polskiCheckoutParams.nipNonce
                : ((window as any).polski_nip_nonce || '');

            const wrapper = input.closest('.form-row') || input.parentElement;
            if (wrapper) wrapper.classList.add('polski-nip-loading');

            const fd = new FormData();
            fd.append('action', 'polski_nip_lookup');
            fd.append('_nonce', nonce);
            fd.append('nip', nip);

            fetch(ajaxUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            })
            .then((r) => r.json())
            .then((res) => {
                if (wrapper) wrapper.classList.remove('polski-nip-loading');
                if (!res || !res.success || !res.data) return;

                lastLookedUpNip = nip;
                const d = res.data;

                const company = document.getElementById('billing_company') as HTMLInputElement | null;
                const addr1 = document.getElementById('billing_address_1') as HTMLInputElement | null;
                const postcode = document.getElementById('billing_postcode') as HTMLInputElement | null;
                const city = document.getElementById('billing_city') as HTMLInputElement | null;

                if (company && d.name) {
                    company.value = d.name;
                    company.dispatchEvent(new Event('input', { bubbles: true }));
                    company.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (addr1 && d.address) {
                    addr1.value = d.address;
                    addr1.dispatchEvent(new Event('input', { bubbles: true }));
                    addr1.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (postcode && d.postcode) {
                    postcode.value = d.postcode;
                    postcode.dispatchEvent(new Event('input', { bubbles: true }));
                    postcode.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (city && d.city) {
                    city.value = d.city;
                    city.dispatchEvent(new Event('input', { bubbles: true }));
                    city.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (typeof jQuery !== 'undefined') {
                    jQuery(document.body).trigger('update_checkout');
                }
            })
            .catch(() => {
                if (wrapper) wrapper.classList.remove('polski-nip-loading');
            });
        }

        function handleNipInput(e: Event): void {
            const target = e.target as HTMLElement | null;
            if (!target) return;

            let input: HTMLInputElement | null = null;
            if (target.matches(NIP_INPUT_SELECTOR)) {
                input = target as HTMLInputElement;
            } else if (target.querySelector) {
                input = target.querySelector(NIP_INPUT_SELECTOR);
            }
            if (!input) return;

            if (debounceTimer) clearTimeout(debounceTimer);

            if (e.type === 'blur' || e.type === 'change') {
                performLookup(input);
            } else {
                debounceTimer = setTimeout(() => {
                    if (input) performLookup(input);
                }, 400);
            }
        }

        document.addEventListener('input', handleNipInput, true);
        document.addEventListener('change', handleNipInput, true);
        document.addEventListener('blur', handleNipInput, true);
    }

    /**
     * Preserve checked state across AJAX fragment refreshes.
     *
     * WooCommerce replaces the .polski-legal-checkboxes container
     * via update_order_review fragments. We save checked states before
     * the update and restore them after.
     */
    function initFragmentPersistence(): void {
        let savedStates: Record<string, boolean> = {};

        // Before fragments update: save states.
        jQuery(document.body).on('update_checkout', () => {
            savedStates = {};
            document.querySelectorAll<HTMLInputElement>(CHECKBOX_SELECTOR).forEach((input) => {
                savedStates[input.name] = input.checked;
            });
        });

        // After fragments update: restore states and re-init validation.
        jQuery(document.body).on('updated_checkout', () => {
            document.querySelectorAll<HTMLInputElement>(CHECKBOX_SELECTOR).forEach((input) => {
                if (input.name in savedStates) {
                    input.checked = savedStates[input.name];
                }
            });

            initValidation();
        });
    }

    /**
     * Initialize when DOM is ready and WooCommerce checkout is present.
     */
    function init(): void {
        initValidation();
        initNipAutofill();

        // Only init fragment persistence if jQuery and WC checkout are available.
        if (typeof jQuery !== 'undefined') {
            initFragmentPersistence();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
