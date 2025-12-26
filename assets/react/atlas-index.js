import React from 'react';
import ReactDOM from 'react-dom/client';
import AtlasDashboard from './atlas-components/AtlasDashboard';

/**
 * Initialize Atlas Control Center Dashboard
 * Entry point for the Atlas React application
 */
if (document.getElementById('atlas-dashboard-root')) {
    const root = ReactDOM.createRoot(document.getElementById('atlas-dashboard-root'));
    root.render(<AtlasDashboard />);
}
