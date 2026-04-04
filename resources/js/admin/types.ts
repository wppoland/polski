export interface CheckboxInfo {
    id: string;
    enabled: boolean;
    is_core: boolean;
    type: 'required' | 'optional';
    contexts: string[];
}

export interface Suggestion {
    id: string;
    severity: 'critical' | 'high' | 'medium' | 'low';
    message: string;
}

export interface ContextBreakdown {
    total: number;
    required: number;
    optional: number;
}

export interface DailyTrend {
    date: string;
    total: number;
    accepted: number;
}

export interface PerCheckboxStat {
    checkbox_id: string;
    accepted: number;
    declined: number;
    total: number;
    rate: number;
}

export interface ByContextStat {
    context: string;
    total: number;
    accepted: number;
}

export interface ConsentLogStats {
    period_days: number;
    total_records: number;
    consented: number;
    declined: number;
    consent_rate: number;
    per_checkbox: PerCheckboxStat[];
    daily_trend: DailyTrend[];
    by_context: ByContextStat[];
}

export interface CheckboxStats {
    total: number;
    core_total: number;
    custom_total: number;
    enabled: number;
    disabled: number;
    core_enabled: number;
    core_disabled: number;
    custom_enabled: number;
    required_enabled: number;
    optional_enabled: number;
    by_context: Record<string, ContextBreakdown>;
    compliance_score: number;
    compliance_max: number;
    compliance_grade: string;
    suggestions: Suggestion[];
    checkboxes: CheckboxInfo[];
    consent_log: ConsentLogStats;
}
