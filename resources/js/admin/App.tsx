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
        };
    }
}

export default function App() {
    return (
        <SlotFillProvider>
            <HashRouter>
                <div className="polski-admin">
                    <header className="polski-admin__header">
                        <h1>{__('Polski', 'polski')}</h1>
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
