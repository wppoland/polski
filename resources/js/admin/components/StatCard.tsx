interface Props {
    label: string;
    value: number | string;
    subtitle?: string;
    color?: string;
}

export default function StatCard({ label, value, subtitle, color }: Props) {
    return (
        <div className="polski-stat-card">
            <div
                className="polski-stat-card__value"
                style={color ? { color } : undefined}
            >
                {value}
            </div>
            <div className="polski-stat-card__label">{label}</div>
            {subtitle && (
                <div className="polski-stat-card__subtitle">{subtitle}</div>
            )}
        </div>
    );
}
