import React from 'react';
import { createRoot } from 'react-dom/client';
import SuperConsole from './super-console-components/SuperConsole';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('homa-super-console-root');
    if (container) {
        const root = createRoot(container);
        root.render(<SuperConsole />);
    }
});
