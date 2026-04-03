import { __ } from '@wordpress/i18n';

interface Props {
    score: number;
    max: number;
    grade: string;
}

function getGradeColor(grade: string): string {
    switch (grade) {
        case 'A':
            return '#16a34a';
        case 'B':
            return '#65a30d';
        case 'C':
            return '#ca8a04';
        case 'D':
            return '#ea580c';
        default:
            return '#dc2626';
    }
}

export default function ComplianceGauge({ score, max, grade }: Props) {
    const percentage = Math.round((score / max) * 100);
    const color = getGradeColor(grade);
    const circumference = 2 * Math.PI * 54;
    const offset = circumference - (percentage / 100) * circumference;

    return (
        <div className="polski-gauge">
            <svg viewBox="0 0 120 120" width="140" height="140">
                <circle
                    cx="60"
                    cy="60"
                    r="54"
                    fill="none"
                    stroke="#e5e7eb"
                    strokeWidth="8"
                />
                <circle
                    cx="60"
                    cy="60"
                    r="54"
                    fill="none"
                    stroke={color}
                    strokeWidth="8"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    transform="rotate(-90 60 60)"
                    style={{ transition: 'stroke-dashoffset 0.6s ease' }}
                />
                <text
                    x="60"
                    y="52"
                    textAnchor="middle"
                    fontSize="28"
                    fontWeight="bold"
                    fill={color}
                >
                    {grade}
                </text>
                <text
                    x="60"
                    y="72"
                    textAnchor="middle"
                    fontSize="13"
                    fill="#6b7280"
                >
                    {score}/{max}
                </text>
            </svg>
            <div className="polski-gauge__label">
                {__('GDPR Compliance', 'polski')}
            </div>
        </div>
    );
}
