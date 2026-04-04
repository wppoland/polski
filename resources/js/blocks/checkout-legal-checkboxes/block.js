import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const { CheckboxControl } = window.wc.blocksComponents;
const { useCheckoutExtensionData, useCheckoutValidation } = window.wc.blocksCheckout;

export default function LegalCheckboxesBlock() {
    const [checkboxes, setCheckboxes] = useState([]);
    const [states, setStates] = useState({});
    const [loading, setLoading] = useState(true);

    const { setExtensionData } = useCheckoutExtensionData('polski');

    useEffect(() => {
        const fetchCheckboxes = async () => {
            try {
                const response = await wp.apiFetch({
                    path: '/polski/v1/checkboxes',
                });

                const visible = response.filter(
                    (cb) => cb.enabled && !cb.hide_input,
                );

                setCheckboxes(visible);

                const initial = {};
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

    useEffect(() => {
        setExtensionData('checkboxes', states);
    }, [states, setExtensionData]);

    useCheckoutValidation(() => {
        const errors = {};

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

    const handleChange = useCallback((id, checked) => {
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
                        onChange={(checked) => handleChange(cb.id, checked)}
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
