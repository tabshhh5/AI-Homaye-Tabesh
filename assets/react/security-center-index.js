import React from 'react';
import { createRoot } from 'react-dom/client';
import SecurityCenter from './super-console-components/SecurityCenter';

/**
 * Security Center Entry Point
 * Entry point for Homa Guardian Security Center React application
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('homa-security-center-root');
    
    if (container) {
        const root = createRoot(container);
        root.render(<SecurityCenter />);
    }
});
