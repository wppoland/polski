import { __ } from '@wordpress/i18n';
import type { PerCheckboxStat } from '../types';

interface Props {
    stats: PerCheckboxStat[];
}

export default function ConsentRateBar({ stats }: Props) {
    if (stats.length === 0) {
        return null;
    }

    return (
        <div className="polski-consent-rates">
            {stats.map((s) => (
                <div key={s.checkbox_id} className="polski-consent-rate">
                    <div className="polski-consent-rate__header">
                        <code>{s.checkbox_id}</code>
                        <span className="polski-consent-rate__pct">
                            {s.rate}%
                        </span>
                    </div>
                    <div className="polski-consent-rate__track">
                        <div
                            className="polski-consent-rate__fill"
                            style={{ width: `${s.rate}%` }}
                        />
                    </div>
                    <div className="polski-consent-rate__detail">
                        {s.accepted} {__('accepted', 'polski')} / {s.total}{' '}
                        {__('total', 'polski')}
                    </div>
                </div>
            ))}
        </div>
    );
}
