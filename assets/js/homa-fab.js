/**
 * Homa Floating Action Button (FAB)
 * Button to toggle the sidebar
 */
(function() {
    'use strict';

    // Create and inject the FAB
    function createFAB() {
        // Check if FAB already exists
        if (document.getElementById('homa-fab')) {
            return;
        }

        // Create button
        const fab = document.createElement('button');
        fab.id = 'homa-fab';
        fab.className = 'homa-fab';
        fab.setAttribute('aria-label', 'باز کردن دستیار هما');
        fab.innerHTML = `
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M16 4C9.4 4 4 9.4 4 16C4 22.6 9.4 28 16 28C22.6 28 28 22.6 28 16C28 9.4 22.6 4 16 4ZM16 8C18.2 8 20 9.8 20 12C20 14.2 18.2 16 16 16C13.8 16 12 14.2 12 12C12 9.8 13.8 8 16 8ZM16 24.8C13.2 24.8 10.7 23.5 9.2 21.4C9.5 19.3 13.6 18.1 16 18.1C18.4 18.1 22.5 19.3 22.8 21.4C21.3 23.5 18.8 24.8 16 24.8Z" fill="currentColor"/>
            </svg>
            <span class="homa-fab-pulse"></span>
        `;

        // Add click handler
        fab.addEventListener('click', () => {
            // Check if orchestrator exists and toggle
            if (window.HomaOrchestrator) {
                window.HomaOrchestrator.toggleSidebar();
            } else {
                // Fallback: dispatch event
                document.dispatchEvent(new CustomEvent('homa:toggle-sidebar'));
            }
        });

        // Add to body
        document.body.appendChild(fab);

        // Add styles
        addFABStyles();
    }

    // Add CSS for FAB
    function addFABStyles() {
        if (document.getElementById('homa-fab-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'homa-fab-styles';
        style.textContent = `
            .homa-fab {
                position: fixed;
                bottom: 24px;
                left: 24px;
                width: 64px;
                height: 64px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999999;
                transition: all 0.3s ease;
                color: white;
            }

            .homa-fab:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 30px rgba(102, 126, 234, 0.6);
            }

            .homa-fab:active {
                transform: scale(0.95);
            }

            .homa-fab-pulse {
                position: absolute;
                width: 100%;
                height: 100%;
                border-radius: 50%;
                background: rgba(102, 126, 234, 0.4);
                animation: fab-pulse 2s infinite;
            }

            @keyframes fab-pulse {
                0% {
                    transform: scale(1);
                    opacity: 1;
                }
                100% {
                    transform: scale(1.5);
                    opacity: 0;
                }
            }

            body.homa-open .homa-fab {
                transform: translateX(-350px);
            }

            @media (max-width: 768px) {
                .homa-fab {
                    bottom: 16px;
                    left: 16px;
                    width: 56px;
                    height: 56px;
                }

                body.homa-open .homa-fab {
                    transform: translateY(350px);
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createFAB);
    } else {
        createFAB();
    }

})();
