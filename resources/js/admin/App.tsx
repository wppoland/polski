import { __ } from '@wordpress/i18n';
import { SlotFillProvider } from '@wordpress/components';
import { HashRouter, Routes, Route } from 'react-router-dom';
import Dashboard from './pages/Dashboard';

declare global {
    interface Window {
        spolszczonyAdmin: {
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
                <div className="spolszczony-admin">
                    <header className="spolszczony-admin__header">
                        <h1>{__('Spolszczony', 'spolszczony')}</h1>
                        <span className="spolszczony-admin__version">
                            v{window.spolszczonyAdmin.version}
                        </span>
                    </header>
                    <main className="spolszczony-admin__content">
                        <Routes>
                            <Route path="/" element={<Dashboard />} />
                        </Routes>
                    </main>
                </div>
            </HashRouter>
        </SlotFillProvider>
    );
}
