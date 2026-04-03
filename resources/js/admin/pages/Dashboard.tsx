import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader, Spinner } from '@wordpress/components';
import { useApi } from '../hooks/useApi';
import type { CheckboxStats } from '../types';
import ComplianceGauge from '../components/ComplianceGauge';
import StatCard from '../components/StatCard';
import SuggestionList from '../components/SuggestionList';
import ConsentTrendChart from '../components/ConsentTrendChart';
import CheckboxTable from '../components/CheckboxTable';
import ConsentRateBar from '../components/ConsentRateBar';

export default function Dashboard() {
    const { data, loading, error } = useApi<CheckboxStats>('checkboxes/stats?days=30');

    if (loading) {
        return (
            <div className="polski-dashboard polski-dashboard--loading">
                <Spinner />
            </div>
        );
    }

    if (error || !data) {
        return (
            <div className="polski-dashboard">
                <Card>
                    <CardBody>
                        <p className="polski-error">
                            {__('Failed to load dashboard data.', 'polski')}{' '}
                            {error}
                        </p>
                    </CardBody>
                </Card>
            </div>
        );
    }

    return (
        <div className="polski-dashboard">
            {/* Top row: Compliance gauge + stat cards */}
            <div className="polski-dashboard__top">
                <Card className="polski-dashboard__gauge-card">
                    <CardBody>
                        <ComplianceGauge
                            score={data.compliance_score}
                            max={data.compliance_max}
                            grade={data.compliance_grade}
                        />
                    </CardBody>
                </Card>

                <div className="polski-dashboard__stats-grid">
                    <StatCard
                        label={__('Checkboxes Enabled', 'polski')}
                        value={data.enabled}
                        subtitle={`${data.total} ${__('total', 'polski')}`}
                        color="#16a34a"
                    />
                    <StatCard
                        label={__('Required', 'polski')}
                        value={data.required_enabled}
                        subtitle={__('active required checkboxes', 'polski')}
                        color="#2563eb"
                    />
                    <StatCard
                        label={__('Optional', 'polski')}
                        value={data.optional_enabled}
                        subtitle={__('active optional consents', 'polski')}
                        color="#7c3aed"
                    />
                    <StatCard
                        label={__('Consent Rate', 'polski')}
                        value={
                            data.consent_log.total_records > 0
                                ? `${data.consent_log.consent_rate}%`
                                : '-'
                        }
                        subtitle={`${data.consent_log.total_records} ${__('records (30d)', 'polski')}`}
                        color="#0891b2"
                    />
                </div>
            </div>

            {/* Suggestions */}
            {data.suggestions.length > 0 && (
                <Card className="polski-dashboard__card">
                    <CardHeader>
                        <h2>{__('Compliance Suggestions', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <SuggestionList suggestions={data.suggestions} />
                    </CardBody>
                </Card>
            )}

            {/* Charts row */}
            <div className="polski-dashboard__charts">
                <Card className="polski-dashboard__card polski-dashboard__card--wide">
                    <CardHeader>
                        <h2>{__('Consent Trend (30 days)', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <ConsentTrendChart data={data.consent_log.daily_trend} />
                    </CardBody>
                </Card>

                <Card className="polski-dashboard__card">
                    <CardHeader>
                        <h2>{__('Acceptance Rate by Checkbox', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <ConsentRateBar stats={data.consent_log.per_checkbox} />
                    </CardBody>
                </Card>
            </div>

            {/* Checkbox list */}
            <Card className="polski-dashboard__card">
                <CardHeader>
                    <h2>{__('Registered Checkboxes', 'polski')}</h2>
                </CardHeader>
                <CardBody>
                    <CheckboxTable checkboxes={data.checkboxes} />
                </CardBody>
            </Card>
        </div>
    );
}
