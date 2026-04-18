import { __ } from '@wordpress/i18n';
import type { Suggestion } from '../types';

interface Props {
    suggestions: Suggestion[];
}

function severityIcon(severity: Suggestion['severity']): string {
    switch (severity) {
        case 'critical':
            return '\u26D4'; // no entry
        case 'high':
            return '\u26A0'; // warning
        case 'medium':
            return '\u2139'; // info
        default:
            return '\u2714'; // check
    }
}

function severityClass(severity: Suggestion['severity']): string {
    return `polski-suggestion--${severity}`;
}

export default function SuggestionList({ suggestions }: Props) {
    if (suggestions.length === 0) {
        return (
            <div className="polski-suggestions__empty">
                {__('All good! No store checklist issues found.', 'polski')}
            </div>
        );
    }

    return (
        <ul className="polski-suggestions">
            {suggestions.map((s) => (
                <li key={s.id} className={`polski-suggestion ${severityClass(s.severity)}`}>
                    <span className="polski-suggestion__icon">
                        {severityIcon(s.severity)}
                    </span>
                    <span className="polski-suggestion__text">{s.message}</span>
                    <span className="polski-suggestion__badge">{s.severity}</span>
                </li>
            ))}
        </ul>
    );
}
