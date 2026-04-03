import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@woocommerce/blocks-components';
import {
    useCheckoutExtensionData,
    useCheckoutValidation,
} from '@woocommerce/blocks-checkout';

declare const wp: {
    apiFetch: (options: { path: string }) => Promise<any>;
};

interface CheckboxData {
    id: string;
    label: string;
    type: 'required' | 'optional';
    enabled: boolean;
    hide_input: boolean;
    error_message: string;
}

/**
 * Fetches checkbox definitions from REST API and renders them
 * in the WooCommerce Checkout Block.
 */
export default function LegalCheckboxesBlock() {
    const [checkboxes, setCheckboxes] = useState<CheckboxData[]>([]);
    const [states, setStates] = useState<Record<string, boolean>>({});
    const [loading, setLoading] = useState(true);

    const { setExtensionData } = useCheckoutExtensionData('polski');

    // Fetch checkbox definitions.
    useEffect(() => {
        const fetchCheckboxes = async () => {
            try {
                const response = await wp.apiFetch({
                    path: '/polski/v1/checkboxes',
                });

                const visible = (response as CheckboxData[]).filter(
                    (cb) => cb.enabled && !cb.hide_input,
                );

                setCheckboxes(visible);

                // Initialize states.
                const initial: Record<string, boolean> = {};
                visible.forEach((cb) => {
                    initial[cb.id] = false;
                });
                setStates(initial);
            } catch {
                // Fallback: empty, classic checkout will handle.
            } finally {
                setLoading(false);
            }
        };

        fetchCheckboxes();
    }, []);

    // Sync states to Store API extension data.
    useEffect(() => {
        setExtensionData('checkboxes', states);
    }, [states, setExtensionData]);

    // Register validation.
    useCheckoutValidation(() => {
        const errors: Record<string, string> = {};

        for (const cb of checkboxes) {
            if (cb.type === 'required' && !states[cb.id]) {
                errors[cb.id] =
                    cb.error_message ||
                    __('This field is required.', 'polski');
            }
        }

        if (Object.keys(errors).length > 0) {
            return {
                errorMessage: Object.values(errors)[0],
            };
        }

        return true;
    });

    const handleChange = useCallback((id: string, checked: boolean) => {
        setStates((prev) => ({ ...prev, [id]: checked }));
    }, []);

    if (loading || checkboxes.length === 0) {
        return null;
    }

    return (
        <div className="polski-legal-checkboxes polski-legal-checkboxes--block">
            {checkboxes.map((cb) => (
                <div
                    key={cb.id}
                    className={`polski-checkbox polski-checkbox--${cb.id}`}
                >
                    <CheckboxControl
                        id={`polski-checkbox-${cb.id}`}
                        checked={states[cb.id] || false}
                        onChange={(checked: boolean) => handleChange(cb.id, checked)}
                        hasError={cb.type === 'required' && states[cb.id] === false}
                    >
                        <span
                            dangerouslySetInnerHTML={{ __html: cb.label }}
                        />
                        {cb.type === 'required' && (
                            <abbr className="required" title={__('required', 'polski')}>
                                *
                            </abbr>
                        )}
                    </CheckboxControl>
                </div>
            ))}
        </div>
    );
}
