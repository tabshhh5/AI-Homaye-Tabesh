import React from 'react';
import ReactDOM from 'react-dom/client';
import HomaSidebar from './components/HomaSidebar';
import './styles/parallel-ui.css';

// Configuration constants for initialization timing
const ORCHESTRATOR_INIT_DELAY = 100; // milliseconds
const MAX_INIT_RETRIES = 3;
const RETRY_DELAY = 200; // milliseconds between retries

/**
 * Initialize Homa Parallel UI with retry logic
 * This function is called by WordPress when the page loads
 */
window.initHomaParallelUI = function(retryCount = 0) {
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

    // CRITICAL: Ensure orchestrator is fully initialized first
    if (window.HomaOrchestrator && !window.HomaOrchestrator.initialized) {
        console.log('[Homa] Waiting for orchestrator initialization...');
        window.HomaOrchestrator.init();
        
        // Wait a moment for DOM operations to complete
        setTimeout(() => {
            if (!window.HomaOrchestrator.initialized) {
                console.warn('[Homa] Orchestrator still not initialized, trying fallback');
                window.HomaOrchestrator.createFallbackSidebar();
            }
        }, 50);
    }

    // Check if container exists (orchestrator should have created it)
    let container = document.getElementById('homa-sidebar-view');
    if (!container) {
        console.warn(`[Homa] Sidebar container not found (attempt ${retryCount + 1}/${MAX_INIT_RETRIES})`);
        
        // Try to create a fallback container
        if (window.HomaOrchestrator && window.HomaOrchestrator.createFallbackSidebar) {
            window.HomaOrchestrator.createFallbackSidebar();
            container = document.getElementById('homa-sidebar-view');
        }
        
        // If still no container and we have retries left, try again
        if (!container && retryCount < MAX_INIT_RETRIES) {
            console.log(`[Homa] Retrying initialization in ${RETRY_DELAY}ms...`);
            setTimeout(() => {
                window.initHomaParallelUI(retryCount + 1);
            }, RETRY_DELAY);
            return;
        }
        
        // Final failure
        if (!container) {
            console.error('[Homa] Failed to create sidebar container after all retries. Check console for errors.');
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
                    <p style="font-size: 12px; color: #6c757d; margin-top: 10px;">خطا: ${error.message}</p>
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
        }, ORCHESTRATOR_INIT_DELAY);
    });
} else {
    // Document already loaded, wait a bit for orchestrator
    setTimeout(() => {
        if (window.initHomaParallelUI) {
            window.initHomaParallelUI();
        }
    }, ORCHESTRATOR_INIT_DELAY);
}
