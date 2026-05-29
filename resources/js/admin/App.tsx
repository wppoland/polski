import { __ } from '@wordpress/i18n';
import { SlotFillProvider } from '@wordpress/components';
import { HashRouter, Routes, Route } from 'react-router-dom';
import Dashboard from './pages/Dashboard';
import SetupWizard from './pages/SetupWizard';

declare global {
    interface Window {
        polskiAdmin: {
            restUrl: string;
            nonce: string;
            version: string;
            isWizardComplete: boolean;
            adminUrl: string;
        };
    }
}

/**
 * Polski brand lockup: monogram tile + "Polski" wordmark with the lifted
 * verified-square tittle. Styling lives in assets/css/polski-brand.css
 * (.polski-logo-mark / .polski-logo-word). The "i" uses the font's dotless
 * glyph (U+0131) so its weight matches the rest of the word; the crimson
 * square + check is overlaid as the tittle.
 */
function PolskiLogo() {
    return (
        <span className="polski-logo" aria-label="Polski">
            <span className="polski-logo-mark" aria-hidden="true">
                <svg viewBox="0 0 100 100">
                    <path
                        fill="#fff"
                        fillRule="evenodd"
                        d="M32 24H58C71 24 79 32.5 79 44C79 55.5 71 64 58 64H46V82H32ZM46 37H56.5C61 37 63 39.6 63 44C63 48.4 61 51 56.5 51H46Z"
                    />
                </svg>
            </span>
            <span className="polski-logo-word" aria-hidden="true">
                Polsk
                <span className="pl-i">
                    {'\u0131'}
                    <span className="pl-dot">
                        <svg viewBox="0 0 100 100" fill="none">
                            <path
                                d="M26 52 L43 68 L76 32"
                                stroke="#fff"
                                strokeWidth="16"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    </span>
                </span>
            </span>
        </span>
    );
}

export default function App() {
    return (
        <SlotFillProvider>
            <HashRouter>
                <div className="polski-admin">
                    <header className="polski-admin__header">
                        <h1 className="screen-reader-text">{__('Polski', 'polski')}</h1>
                        <PolskiLogo />
                        <span className="polski-admin__version">
                            v{window.polskiAdmin.version}
                        </span>
                    </header>
                    <main className="polski-admin__content">
                        <Routes>
                            <Route path="/" element={<Dashboard />} />
                            <Route path="/setup-wizard" element={<SetupWizard />} />
                        </Routes>
                    </main>
                </div>
            </HashRouter>
        </SlotFillProvider>
    );
}
