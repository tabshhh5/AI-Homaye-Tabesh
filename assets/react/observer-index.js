import React from 'react';
import { createRoot } from 'react-dom/client';
import GlobalObserver from './observer-components/GlobalObserver';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('homa-observer-root');
    if (container) {
        const root = createRoot(container);
        root.render(<GlobalObserver />);
    }
});
