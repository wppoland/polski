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

const { restUrl, nonce } = window.polskiAdmin;

interface WizardData {
    company_name: string;
    company_address: string;
    company_nip: string;
    company_email: string;
    company_phone: string;
    terms_enabled: boolean;
    privacy_enabled: boolean;
    withdrawal_enabled: boolean;
    digital_waiver_enabled: boolean;
    marketing_enabled: boolean;
    order_button_text: string;
    generate_legal_pages: boolean;
    omnibus_enabled: boolean;
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
    digital_waiver_enabled: false,
    marketing_enabled: false,
    order_button_text: __('Zamawiam z obowiązkiem zapłaty', 'polski'),
    generate_legal_pages: true,
    omnibus_enabled: true,
};

const STEPS = [
    { id: 'company', label: __('Company Data', 'polski') },
    { id: 'checkboxes', label: __('Legal Checkboxes', 'polski') },
    { id: 'features', label: __('Features', 'polski') },
    { id: 'done', label: __('Done', 'polski') },
];

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
            setStep(STEPS.length - 1);
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

            {/* Step 1: Company Data */}
            {step === 0 && (
                <Card>
                    <CardHeader>
                        <h2>{__('Company Information', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <TextControl
                            label={__('Company Name', 'polski')}
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
                            help={__('Tax ID number (10 digits)', 'polski')}
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

            {/* Step 2: Legal Checkboxes */}
            {step === 1 && (
                <Card>
                    <CardHeader>
                        <h2>{__('Legal Checkboxes', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <p className="polski-wizard__hint">
                            {__('Select which legal checkboxes to show at checkout.', 'polski')}
                        </p>
                        <ToggleControl
                            label={__('Terms and Conditions', 'polski')}
                            checked={data.terms_enabled}
                            onChange={(v) => update('terms_enabled', v)}
                            help={__('Required by Polish consumer law.', 'polski')}
                        />
                        <ToggleControl
                            label={__('Privacy Policy', 'polski')}
                            checked={data.privacy_enabled}
                            onChange={(v) => update('privacy_enabled', v)}
                            help={__('Required by GDPR Art. 6.1.a.', 'polski')}
                        />
                        <ToggleControl
                            label={__('Withdrawal Rights', 'polski')}
                            checked={data.withdrawal_enabled}
                            onChange={(v) => update('withdrawal_enabled', v)}
                            help={__('14-day withdrawal acknowledgment.', 'polski')}
                        />
                        <ToggleControl
                            label={__('Digital Content Waiver', 'polski')}
                            checked={data.digital_waiver_enabled}
                            onChange={(v) => update('digital_waiver_enabled', v)}
                            help={__('Enable if you sell digital products.', 'polski')}
                        />
                        <ToggleControl
                            label={__('Marketing Consent', 'polski')}
                            checked={data.marketing_enabled}
                            onChange={(v) => update('marketing_enabled', v)}
                            help={__('Optional newsletter/marketing opt-in.', 'polski')}
                        />
                    </CardBody>
                </Card>
            )}

            {/* Step 3: Features */}
            {step === 2 && (
                <Card>
                    <CardHeader>
                        <h2>{__('Features', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        <TextControl
                            label={__('Order Button Text', 'polski')}
                            value={data.order_button_text}
                            onChange={(v) => update('order_button_text', v)}
                            help={__('Use a clear payment obligation text for the final order button.', 'polski')}
                        />
                        <ToggleControl
                            label={__('Omnibus Directive', 'polski')}
                            checked={data.omnibus_enabled}
                            onChange={(v) => update('omnibus_enabled', v)}
                            help={__('Show lowest price from last 30 days on sale products.', 'polski')}
                        />
                        <ToggleControl
                            label={__('Generate Legal Pages', 'polski')}
                            checked={data.generate_legal_pages}
                            onChange={(v) => update('generate_legal_pages', v)}
                            help={__('Create draft pages for Terms, Privacy Policy, and Return Policy.', 'polski')}
                        />
                    </CardBody>
                </Card>
            )}

            {/* Step 4: Done */}
            {step === 3 && (
                <Card>
                    <CardHeader>
                        <h2>{completed ? __('Setup Complete!', 'polski') : __('Finishing...', 'polski')}</h2>
                    </CardHeader>
                    <CardBody>
                        {completed ? (
                            <div className="polski-wizard__done">
                                <p>{__('Your store setup is ready for review.', 'polski')}</p>
                                <Button variant="primary" href="#/">
                                    {__('Go to Dashboard', 'polski')}
                                </Button>
                            </div>
                        ) : (
                            <Spinner />
                        )}
                    </CardBody>
                </Card>
            )}

            {/* Navigation */}
            {step < STEPS.length - 1 && (
                <div className="polski-wizard__nav">
                    {step > 0 && (
                        <Button variant="secondary" onClick={() => setStep(step - 1)}>
                            {__('Back', 'polski')}
                        </Button>
                    )}
                    <div style={{ flex: 1 }} />
                    {step < 2 && (
                        <Button
                            variant="primary"
                            onClick={() => setStep(step + 1)}
                            disabled={!canProceed()}
                        >
                            {__('Next', 'polski')}
                        </Button>
                    )}
                    {step === 2 && (
                        <Button
                            variant="primary"
                            onClick={handleFinish}
                            isBusy={saving}
                            disabled={saving}
                        >
                            {saving ? __('Saving...', 'polski') : __('Finish Setup', 'polski')}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
