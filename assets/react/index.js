import React from 'react';
import ReactDOM from 'react-dom/client';
import HomaSidebar from './components/HomaSidebar';
import './styles/parallel-ui.css';

/**
 * Initialize Homa Parallel UI
 * This function is called by WordPress when the page loads
 */
window.initHomaParallelUI = function() {
    // Validate React is available
    if (typeof window.React === 'undefined' || typeof window.ReactDOM === 'undefined') {
        console.error('[Homa] React or ReactDOM not loaded. Please check CDN availability.');
        // Show user-friendly error
        const container = document.getElementById('homa-sidebar-view');
        if (container) {
            container.innerHTML = `
                <div style="padding: 20px; text-align: center; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;">
                    <h3>خطا در بارگذاری هما</h3>
                    <p>لطفاً اتصال اینترنت خود را بررسی کنید و صفحه را رفرش کنید.</p>
                </div>
            `;
        }
        return;
    }

    // Ensure orchestrator is initialized first
    if (window.HomaOrchestrator && !window.HomaOrchestrator.initialized) {
        console.log('[Homa] Waiting for orchestrator initialization...');
        window.HomaOrchestrator.init();
    }

    // Check if container exists (orchestrator should have created it)
    const container = document.getElementById('homa-sidebar-view');
    if (!container) {
        console.error('[Homa] Sidebar container not found - orchestrator may have failed');
        // Try to create a fallback container
        if (window.HomaOrchestrator && window.HomaOrchestrator.createFallbackSidebar) {
            window.HomaOrchestrator.createFallbackSidebar();
            // Try again after creating fallback
            const fallbackContainer = document.getElementById('homa-sidebar-view');
            if (!fallbackContainer) {
                console.error('[Homa] Failed to create fallback sidebar container');
                return;
            }
        } else {
            return;
        }
    }

    try {
        // Create React root and render sidebar
        const root = window.ReactDOM.createRoot(document.getElementById('homa-sidebar-view'));
        root.render(window.React.createElement(HomaSidebar));
        console.log('[Homa] React sidebar initialized successfully');
    } catch (error) {
        console.error('[Homa] Initialization error:', error);
        const errorContainer = document.getElementById('homa-sidebar-view');
        if (errorContainer) {
            errorContainer.innerHTML = `
                <div style="padding: 20px; text-align: center; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;">
                    <h3>خطا در بارگذاری هما</h3>
                    <p>لطفاً صفحه را رفرش کنید. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.</p>
                </div>
            `;
        }
    }
};

// Auto-initialize when DOM is ready - with slight delay to ensure orchestrator finishes
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Small delay to ensure orchestrator completes first
        setTimeout(() => {
            if (window.initHomaParallelUI) {
                window.initHomaParallelUI();
            }
        }, 100);
    });
} else {
    // Document already loaded, wait a bit for orchestrator
    setTimeout(() => {
        if (window.initHomaParallelUI) {
            window.initHomaParallelUI();
        }
    }, 100);
}
