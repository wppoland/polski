import { __ } from '@wordpress/i18n';
import type { CheckboxInfo } from '../types';

interface Props {
    checkboxes: CheckboxInfo[];
}

const contextLabels: Record<string, string> = {
    checkout: 'Checkout',
    registration: 'Registration',
    review: 'Review',
    pay_for_order: 'Pay for Order',
    quote: 'Quote',
};

export default function CheckboxTable({ checkboxes }: Props) {
    return (
        <table className="polski-checkbox-table">
            <thead>
                <tr>
                    <th>{__('ID', 'polski')}</th>
                    <th>{__('Type', 'polski')}</th>
                    <th>{__('Status', 'polski')}</th>
                    <th>{__('Contexts', 'polski')}</th>
                    <th>{__('Source', 'polski')}</th>
                </tr>
            </thead>
            <tbody>
                {checkboxes.map((cb) => (
                    <tr
                        key={cb.id}
                        className={
                            cb.enabled ? '' : 'polski-checkbox-table__row--disabled'
                        }
                    >
                        <td>
                            <code>{cb.id}</code>
                        </td>
                        <td>
                            <span
                                className={`polski-badge polski-badge--${cb.type}`}
                            >
                                {cb.type === 'required'
                                    ? __('Required', 'polski')
                                    : __('Optional', 'polski')}
                            </span>
                        </td>
                        <td>
                            <span
                                className={`polski-badge polski-badge--${cb.enabled ? 'enabled' : 'disabled'}`}
                            >
                                {cb.enabled
                                    ? __('Enabled', 'polski')
                                    : __('Disabled', 'polski')}
                            </span>
                        </td>
                        <td>
                            {cb.contexts
                                .map((c) => contextLabels[c] || c)
                                .join(', ')}
                        </td>
                        <td>
                            {cb.is_core
                                ? __('Built-in', 'polski')
                                : __('Custom', 'polski')}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
