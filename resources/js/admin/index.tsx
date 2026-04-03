import { createRoot } from '@wordpress/element';
import '../../css/admin-dashboard.css';
import App from './App';

const container = document.getElementById('polski-admin');

if (container) {
    const root = createRoot(container);
    root.render(<App />);
}
