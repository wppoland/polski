import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Card,
    CardBody,
    CardHeader,
    Button,
    TextControl,
    ToggleControl,
    Notice,
    Spinner,
} from '@wordpress/components';

const { restUrl, nonce, adminUrl } = window.polskiAdmin;

interface WizardData {
    company_name: string;
    company_address: string;
    company_nip: string;
    company_email: string;
    company_phone: string;

    terms_enabled: boolean;
    privacy_enabled: boolean;
    withdrawal_enabled: boolean;
    marketing_enabled: boolean;
    generate_legal_pages: boolean;

    omnibus_enabled: boolean;
    small_business: boolean;
    oss_observer_enabled: boolean;

    order_button_text: string;
    digital_waiver_enabled: boolean;

    // Withdrawal flow (Polski 1.16.0+)
    withdrawal_period_days: number;
    withdrawal_create_lookup_page: boolean;
    withdrawal_digital_consent_mode: 'required' | 'optional' | 'hidden';
}

const INITIAL_DATA: WizardData = {
    company_name: '',
    company_address: '',
    company_nip: '',
    company_email: '',
    company_phone: '',

    terms_enabled: true,
    privacy_enabled: true,
    withdrawal_enabled: true,
    marketing_enabled: false,
    generate_legal_pages: true,

    omnibus_enabled: true,
    small_business: false,
    oss_observer_enabled: false,

    order_button_text: __('Zamawiam z obowiązkiem zapłaty', 'polski'),
    digital_waiver_enabled: false,

    withdrawal_period_days: 14,
    withdrawal_create_lookup_page: true,
    withdrawal_digital_consent_mode: 'optional',
};

const STEPS = [
    { id: 'company', label: __('Company', 'polski'), skippable: false },
    { id: 'legal', label: __('Legal', 'polski'), skippable: true },
    { id: 'tax-oss', label: __('Tax & OSS', 'polski'), skippable: true },
    { id: 'checkout', label: __('Checkout', 'polski'), skippable: true },
    { id: 'finish', label: __('Finish', 'polski'), skippable: false },
];

interface RowProps {
    label: string;
    description: string;
    children: React.ReactNode;
}

/**
 * Germanized-style toggle row: bold label on the left, control + tinted
 * info panel on the right.
 */
function WizardRow({ label, description, children }: RowProps) {
    return (
        <div className="polski-wizard__row">
            <div className="polski-wizard__row-label">{label}</div>
            <div className="polski-wizard__row-body">
                <div className="polski-wizard__row-control">{children}</div>
                <div className="polski-wizard__row-info">{description}</div>
            </div>
        </div>
    );
}

export default function SetupWizard() {
    const [step, setStep] = useState(0);
    const [data, setData] = useState<WizardData>(INITIAL_DATA);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [completed, setCompleted] = useState(false);

    const update = useCallback(
        <K extends keyof WizardData>(key: K, value: WizardData[K]) => {
            setData((prev) => ({ ...prev, [key]: value }));
        },
        [],
    );

    const handleFinish = useCallback(async () => {
        setSaving(true);
        setError(null);

        try {
            const response = await fetch(`${restUrl}wizard/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                const err = await response.json();
                throw new Error(err.message || `HTTP ${response.status}`);
            }

            setCompleted(true);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
        } finally {
            setSaving(false);
        }
    }, [data]);

    const canProceed = (): boolean => {
        if (step === 0) {
            return data.company_name.trim() !== '' && data.company_email.trim() !== '';
        }
        return true;
    };

    const goBack = useCallback(() => setStep((s) => Math.max(0, s - 1)), []);
    const goNext = useCallback(() => setStep((s) => Math.min(STEPS.length - 1, s + 1)), []);

    const isLastStep = step === STEPS.length - 1;
    const isFinishStep = step === STEPS.length - 1;
    const isSettingsFinal = step === STEPS.length - 2; // Checkout step triggers Finish.

    return (
        <div className="polski-wizard">
            {/* Step indicator */}
            <div className="polski-wizard__steps">
                {STEPS.map((s, i) => (
                    <div
                        key={s.id}
                        className={`polski-wizard__step ${
                            i === step
                                ? 'polski-wizard__step--active'
                                : i < step
                                  ? 'polski-wizard__step--done'
                                  : ''
                        }`}
                    >
                        <span className="polski-wizard__step-num">{i + 1}</span>
                        <span className="polski-wizard__step-label">{s.label}</span>
                    </div>
                ))}
            </div>

            {error && (
                <Notice status="error" isDismissible onDismiss={() => setError(null)}>
                    {error}
                </Notice>
            )}

            {/* Step 1: Company */}
            {step === 0 && (
                <Card>
                    <CardHeader>
                        <h2>{__('Company information', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <p className="polski-wizard__hint">
                            {__(
                                'Used on legal pages, invoices, and emails. You can change all of this later in Polski > Dashboard.',
                                'polski',
                            )}
                        </p>
                        <TextControl
                            label={__('Company name', 'polski')}
                            value={data.company_name}
                            onChange={(v) => update('company_name', v)}
                            required
                        />
                        <TextControl
                            label={__('Address', 'polski')}
                            value={data.company_address}
                            onChange={(v) => update('company_address', v)}
                        />
                        <TextControl
                            label={__('NIP', 'polski')}
                            value={data.company_nip}
                            onChange={(v) => update('company_nip', v)}
                            help={__('Tax ID (10 digits). Used on invoices and the KSeF integration.', 'polski')}
                        />
                        <TextControl
                            label={__('Email', 'polski')}
                            value={data.company_email}
                            onChange={(v) => update('company_email', v)}
                            type="email"
                            required
                        />
                        <TextControl
                            label={__('Phone', 'polski')}
                            value={data.company_phone}
                            onChange={(v) => update('company_phone', v)}
                        />
                    </CardBody>
                </Card>
            )}

            {/* Step 2: Legal */}
            {step === 1 && (
                <Card>
                    <CardHeader>
                        <h2>{__('Legal compliance', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <p className="polski-wizard__hint">
                            {__(
                                'Pick which consumer-law checkboxes appear at checkout and whether to auto-generate the legal pages referenced by them.',
                                'polski',
                            )}
                        </p>

                        <WizardRow
                            label={__('Terms and Conditions', 'polski')}
                            description={__(
                                'Required by Polish consumer law. Adds a mandatory checkbox at checkout referencing your Terms page.',
                                'polski',
                            )}
                        >
                            <ToggleControl
                                label={__('Show Terms checkbox', 'polski')}
                                checked={data.terms_enabled}
                                onChange={(v) => update('terms_enabled', v)}
                            />
                        </WizardRow>

                        <WizardRow
                            label={__('Privacy Policy', 'polski')}
                            description={__(
                                'Required by GDPR Art. 6.1.a. Adds a mandatory checkbox at checkout referencing your Privacy Policy.',
                                'polski',
                            )}
                        >
                            <ToggleControl
                                label={__('Show Privacy checkbox', 'polski')}
                                checked={data.privacy_enabled}
                                onChange={(v) => update('privacy_enabled', v)}
                            />
                        </WizardRow>

                        <WizardRow
                            label={__('14-day withdrawal', 'polski')}
                            description={__(
                                'Customer confirms they have been informed about the 14-day right of withdrawal. Required under EU Directive 2011/83.',
                                'polski',
                            )}
                        >
                            <ToggleControl
                                label={__('Show withdrawal checkbox', 'polski')}
                                checked={data.withdrawal_enabled}
                                onChange={(v) => update('withdrawal_enabled', v)}
                            />
                        </WizardRow>

                        {data.withdrawal_enabled && (
                            <>
                                <WizardRow
                                    label={__('Withdrawal period (days)', 'polski')}
                                    description={__(
                                        'Standard EU period is 14 days. Increase only if your store voluntarily offers a longer return window. The clock starts when an order enters a completed/shipped status.',
                                        'polski',
                                    )}
                                >
                                    <TextControl
                                        type="number"
                                        min={1}
                                        value={String(data.withdrawal_period_days)}
                                        onChange={(v) => update('withdrawal_period_days', Math.max(1, parseInt(v, 10) || 14))}
                                    />
                                </WizardRow>

                                <WizardRow
                                    label={__('Create a public withdrawal form page', 'polski')}
                                    description={__(
                                        'Required by Art. 11a of Directive 2023/2673 (in force 19 June 2026) for guest customers without a shop account. We will publish a page at /odstapienie/ containing the [polski_withdrawal_lookup] shortcode. You can rename or move it later.',
                                        'polski',
                                    )}
                                >
                                    <ToggleControl
                                        label={__('Publish /odstapienie/ on Finish', 'polski')}
                                        checked={data.withdrawal_create_lookup_page}
                                        onChange={(v) => update('withdrawal_create_lookup_page', v)}
                                    />
                                </WizardRow>

                                <WizardRow
                                    label={__('Digital products consent (Art. 16(m))', 'polski')}
                                    description={__(
                                        'For downloadable / virtual products, the consumer must actively consent to losing the right of withdrawal before delivery starts. Pick the prompt mode best fitting your catalog. "Hidden" preserves the right of withdrawal regardless of digital nature.',
                                        'polski',
                                    )}
                                >
                                    <select
                                        value={data.withdrawal_digital_consent_mode}
                                        onChange={(e) =>
                                            update(
                                                'withdrawal_digital_consent_mode',
                                                e.target.value as WizardData['withdrawal_digital_consent_mode'],
                                            )
                                        }
                                    >
                                        <option value="required">
                                            {__('Required — checkout blocked until ticked', 'polski')}
                                        </option>
                                        <option value="optional">
                                            {__('Optional — show unchecked, only ticked orders are exempt', 'polski')}
                                        </option>
                                        <option value="hidden">
                                            {__("Hidden — don't collect consent", 'polski')}
                                        </option>
                                    </select>
                                </WizardRow>
                            </>
                        )}

                        <WizardRow
                            label={__('Marketing consent', 'polski')}
                            description={__(
                                'Optional opt-in for newsletters and marketing messages. Keep this off unless you operate a newsletter.',
                                'polski',
                            )}
                        >
                            <ToggleControl
                                label={__('Show marketing checkbox', 'polski')}
                                checked={data.marketing_enabled}
                                onChange={(v) => update('marketing_enabled', v)}
                            />
                        </WizardRow>

                        <WizardRow
                            label={__('Generate legal pages', 'polski')}
                            description={__(
                                'Create draft pages for Terms, Privacy Policy, Right of Withdrawal, and Complaints so your checkboxes have something to link to. You can edit them afterwards.',
                                'polski',
                            )}
                        >
                            <ToggleControl
                                label={__('Auto-generate pages on finish', 'polski')}
                                checked={data.generate_legal_pages}
                                onChange={(v) => update('generate_legal_pages', v)}
                            />
                        </WizardRow>
                    </CardBody>
                </Card>
            )}

            {/* Step 3: Tax & OSS */}
            {step === 2 && (
                <Card>
                    <CardHeader>
                        <h2>{__('Tax & OSS', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <p className="polski-wizard__hint">
                            {__(
                                'Price display, Omnibus directive, small-business exemption, and the EU OSS delivery-threshold observer.',
                                'polski',
                            )}
                        </p>

                        <WizardRow
                            label={__('Omnibus directive', 'polski')}
                            description={__(
                                'Track product price history and display the lowest price from the last 30 days on sale products. Required by EU Directive 2019/2161.',
                                'polski',
                            )}
                        >
                            <ToggleControl
                                label={__('Enable Omnibus tracking', 'polski')}
                                checked={data.omnibus_enabled}
                                onChange={(v) => update('omnibus_enabled', v)}
                            />
                        </WizardRow>

                        <WizardRow
                            label={__('Small-business exemption (Art. 113)', 'polski')}
                            description={__(
                                'Enable this if you use the Polish VAT exemption under Art. 113 of the VAT Act (annual revenue below the threshold). Adjusts the tax display notice accordingly.',
                                'polski',
                            )}
                        >
                            <ToggleControl
                                label={__('VAT-exempt under Art. 113', 'polski')}
                                checked={data.small_business}
                                onChange={(v) => update('small_business', v)}
                            />
                        </WizardRow>

                        <WizardRow
                            label={__('OSS observer', 'polski')}
                            description={__(
                                'Observe the EU One Stop Shop delivery threshold (€10,000 annual intra-EU B2C sales). Finishing this wizard with the toggle on will install the standalone One Stop Shop plugin automatically.',
                                'polski',
                            )}
                        >
                            <ToggleControl
                                label={__('Enable OSS observer', 'polski')}
                                checked={data.oss_observer_enabled}
                                onChange={(v) => update('oss_observer_enabled', v)}
                            />
                        </WizardRow>
                    </CardBody>
                </Card>
            )}

            {/* Step 4: Checkout */}
            {step === 3 && (
                <Card>
                    <CardHeader>
                        <h2>{__('Checkout', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <p className="polski-wizard__hint">
                            {__(
                                'Order button wording and optional digital-content waiver. All of this is configurable per-module afterwards.',
                                'polski',
                            )}
                        </p>

                        <WizardRow
                            label={__('Order button text', 'polski')}
                            description={__(
                                'Polish law requires the final order button to make the payment obligation explicit. The default text complies.',
                                'polski',
                            )}
                        >
                            <TextControl
                                label={__('Button label', 'polski')}
                                value={data.order_button_text}
                                onChange={(v) => update('order_button_text', v)}
                            />
                        </WizardRow>

                        <WizardRow
                            label={__('Digital content waiver', 'polski')}
                            description={__(
                                'Enable if you sell downloads or digital subscriptions. The customer explicitly agrees to immediate delivery and loses the 14-day withdrawal right.',
                                'polski',
                            )}
                        >
                            <ToggleControl
                                label={__('Show digital waiver checkbox', 'polski')}
                                checked={data.digital_waiver_enabled}
                                onChange={(v) => update('digital_waiver_enabled', v)}
                            />
                        </WizardRow>
                    </CardBody>
                </Card>
            )}

            {/* Step 5: Finish */}
            {isFinishStep && (
                <Card>
                    <CardHeader>
                        <h2>{completed ? __('Setup complete', 'polski') : __('Ready to finish', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        {completed ? (
                            <div className="polski-wizard__done">
                                <p>
                                    {__(
                                        'Your store is configured. Review the generated legal pages, adjust module settings as needed, and you are good to go.',
                                        'polski',
                                    )}
                                </p>
                                {data.oss_observer_enabled && (
                                    <Notice status="info" isDismissible={false}>
                                        {__(
                                            'You enabled the OSS observer. If the One Stop Shop plugin is missing, an admin notice will prompt you to install it.',
                                            'polski',
                                        )}
                                    </Notice>
                                )}
                                <div style={{ marginTop: 16 }}>
                                    <Button variant="primary" href={adminUrl}>
                                        {__('Go to dashboard', 'polski')}
                                    </Button>
                                </div>
                            </div>
                        ) : saving ? (
                            <div>
                                <Spinner />
                                <p>{__('Saving your settings...', 'polski')}</p>
                            </div>
                        ) : (
                            <div>
                                <p>
                                    {__(
                                        'Clicking Finish saves your company data, legal checkboxes, tax setup, and checkout options. You can change everything afterwards in Polski > Modules.',
                                        'polski',
                                    )}
                                </p>
                                <Button variant="primary" onClick={handleFinish}>
                                    {__('Finish setup', 'polski')}
                                </Button>
                            </div>
                        )}
                    </CardBody>
                </Card>
            )}

            {/* Navigation */}
            {!isFinishStep && (
                <div className="polski-wizard__nav">
                    {step > 0 && (
                        <Button variant="secondary" onClick={goBack}>
                            {__('Back', 'polski')}
                        </Button>
                    )}
                    <div style={{ flex: 1 }} />

                    {STEPS[step].skippable && !isSettingsFinal && (
                        <Button variant="tertiary" onClick={goNext}>
                            {__('Skip step', 'polski')}
                        </Button>
                    )}

                    <Button
                        variant="primary"
                        onClick={goNext}
                        disabled={!canProceed()}
                    >
                        {__('Continue', 'polski')}
                    </Button>
                </div>
            )}

            <p className="polski-wizard__escape">
                <a href={adminUrl}>{__('Return to WP Admin', 'polski')}</a>
            </p>
        </div>
    );
}
