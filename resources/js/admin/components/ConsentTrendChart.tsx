import { __ } from '@wordpress/i18n';
import type { DailyTrend } from '../types';

interface Props {
    data: DailyTrend[];
}

export default function ConsentTrendChart({ data }: Props) {
    if (data.length === 0) {
        return (
            <div className="polski-chart__empty">
                {__('No consent data yet. Statistics will appear after first orders.', 'polski')}
            </div>
        );
    }

    const maxTotal = Math.max(...data.map((d) => d.total), 1);

    return (
        <div className="polski-chart">
            <div className="polski-chart__bars">
                {data.map((day) => {
                    const heightPercent = (day.total / maxTotal) * 100;
                    const acceptedPercent =
                        day.total > 0 ? (day.accepted / day.total) * 100 : 0;
                    const dateLabel = new Date(day.date).toLocaleDateString(
                        'pl-PL',
                        { day: '2-digit', month: '2-digit' },
                    );

                    return (
                        <div key={day.date} className="polski-chart__bar-group">
                            <div
                                className="polski-chart__bar"
                                style={{ height: `${heightPercent}%` }}
                                title={`${dateLabel}: ${day.accepted}/${day.total}`}
                            >
                                <div
                                    className="polski-chart__bar-fill"
                                    style={{ height: `${acceptedPercent}%` }}
                                />
                            </div>
                            <span className="polski-chart__bar-label">
                                {dateLabel}
                            </span>
                        </div>
                    );
                })}
            </div>
            <div className="polski-chart__legend">
                <span className="polski-chart__legend-item polski-chart__legend-item--accepted">
                    {__('Accepted', 'polski')}
                </span>
                <span className="polski-chart__legend-item polski-chart__legend-item--total">
                    {__('Total', 'polski')}
                </span>
            </div>
        </div>
    );
}
