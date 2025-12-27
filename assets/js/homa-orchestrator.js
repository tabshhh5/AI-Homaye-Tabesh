/**
 * Homa Orchestrator
 * Manages the viewport squeeze logic and parallel interaction
 */
(function() {
    'use strict';

    // Configuration constants
    const SIDEBAR_WIDTH = 400; // pixels
    const SIDEBAR_Z_INDEX = 999999;
    const SIDEBAR_TRANSITION_DURATION = '0.3s';

    window.HomaOrchestrator = {
        initialized: false,
        isOpen: false,

        /**
         * Initialize the orchestrator
         */
        init: function() {
            try {
                if (this.initialized) {
                    console.log('[Homa Orchestrator] Already initialized');
                    return;
                }

                console.log('[Homa Orchestrator] Starting initialization...');

                this.setupGlobalWrapper();
                this.setupEventListeners();
                this.initialized = true;
                
                console.log('[Homa Orchestrator] Initialized successfully');
            } catch (error) {
                console.error('[Homa Orchestrator] Initialization failed:', error);
                
                // Try minimal initialization
                try {
                    this.createFallbackSidebar();
                    this.setupEventListeners();
                    this.initialized = true;
                    console.log('[Homa Orchestrator] Fallback initialization completed');
                } catch (fallbackError) {
                    console.error('[Homa Orchestrator] Even fallback init failed:', fallbackError);
                }
                
                // Report error if handler available
                if (window.Homa && window.Homa.reportError) {
                    window.Homa.reportError(error, { component: 'orchestrator', method: 'init' });
                }
            }
        },

        /**
         * Setup the global wrapper structure
         */
        setupGlobalWrapper: function() {
            try {
                // Check if wrapper already exists
                if (document.getElementById('homa-global-wrapper')) {
                    console.log('[Homa Orchestrator] Global wrapper already exists');
                    return;
                }

                // Verify body element exists
                if (!document.body) {
                    console.error('[Homa Orchestrator] Document body not available');
                    return;
                }

                // Create wrapper
                const wrapper = document.createElement('div');
                wrapper.id = 'homa-global-wrapper';

                // Create site view (will contain the Divi content)
                const siteView = document.createElement('div');
                siteView.id = 'homa-site-view';

                // Create sidebar view (will contain React app)
                const sidebarView = document.createElement('div');
                sidebarView.id = 'homa-sidebar-view';

                // Move body content into site view safely
                try {
                    while (document.body.firstChild) {
                        siteView.appendChild(document.body.firstChild);
                    }
                } catch (moveError) {
                    console.error('[Homa Orchestrator] Error moving body content:', moveError);
                    // If moving fails, continue anyway - sidebar will still work
                }

                // Assemble structure
                wrapper.appendChild(siteView);
                wrapper.appendChild(sidebarView);
                document.body.appendChild(wrapper);

                // Set body styles safely
                try {
                    document.body.style.margin = '0';
                    document.body.style.padding = '0';
                    document.body.style.overflow = 'hidden';
                } catch (styleError) {
                    console.warn('[Homa Orchestrator] Could not apply body styles:', styleError);
                }

                console.log('[Homa Orchestrator] Global wrapper structure created successfully');
            } catch (error) {
                console.error('[Homa Orchestrator] Failed to setup global wrapper:', error);
                
                // Fail-safe: Create minimal sidebar container
                this.createFallbackSidebar();
                
                // Report error if handler is available
                if (window.Homa && window.Homa.reportError) {
                    window.Homa.reportError(error, { component: 'orchestrator', method: 'setupGlobalWrapper' });
                }
            }
        },

        /**
         * Create fallback sidebar container if main setup fails
         */
        createFallbackSidebar: function() {
            try {
                // Check if sidebar already exists
                if (document.getElementById('homa-sidebar-view')) {
                    return;
                }

                console.log('[Homa Orchestrator] Creating fallback sidebar container');
                
                // Create minimal sidebar
                const sidebar = document.createElement('div');
                sidebar.id = 'homa-sidebar-view';
                sidebar.style.cssText = `
                    position: fixed;
                    top: 0;
                    right: -${SIDEBAR_WIDTH}px;
                    width: ${SIDEBAR_WIDTH}px;
                    height: 100vh;
                    background: white;
                    z-index: ${SIDEBAR_Z_INDEX};
                    transition: right ${SIDEBAR_TRANSITION_DURATION} ease;
                    box-shadow: -2px 0 10px rgba(0,0,0,0.1);
                `;
                
                document.body.appendChild(sidebar);
                console.log('[Homa Orchestrator] Fallback sidebar created');
            } catch (fallbackError) {
                console.error('[Homa Orchestrator] Even fallback sidebar creation failed:', fallbackError);
            }
        },

        /**
         * Setup event listeners
         */
        setupEventListeners: function() {
            try {
                // Listen for toggle events
                document.addEventListener('homa:open-sidebar', () => {
                    try {
                        this.openSidebar();
                    } catch (error) {
                        console.error('[Homa Orchestrator] Error opening sidebar:', error);
                    }
                });

                document.addEventListener('homa:close-sidebar', () => {
                    try {
                        this.closeSidebar();
                    } catch (error) {
                        console.error('[Homa Orchestrator] Error closing sidebar:', error);
                    }
                });

                document.addEventListener('homa:toggle-sidebar', () => {
                    try {
                        this.toggleSidebar();
                    } catch (error) {
                        console.error('[Homa Orchestrator] Error toggling sidebar:', error);
                    }
                });

                // Listen for form changes on the site
                this.setupFormObserver();
                
                console.log('[Homa Orchestrator] Event listeners registered');
            } catch (error) {
                console.error('[Homa Orchestrator] Error setting up event listeners:', error);
                if (window.Homa && window.Homa.reportError) {
                    window.Homa.reportError(error, { component: 'orchestrator', method: 'setupEventListeners' });
                }
            }
        },

        /**
         * Open the sidebar
         */
        openSidebar: function() {
            if (this.isOpen) {
                return;
            }

            document.body.classList.add('homa-open');
            this.isOpen = true;

            // Update global state
            if (window.Homa && window.Homa.updateState) {
                window.Homa.updateState({ isSidebarOpen: true });
            }

            // Emit via event bus (but don't dispatch DOM event to avoid recursion)
            if (window.Homa && window.Homa.emit) {
                window.Homa.emit('sidebar:opened', { timestamp: Date.now() });
            }

            // Trigger window resize after animation completes
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
                this.recalculateDiviModules();
            }, 650); // Slightly after the 600ms transition
        },

        /**
         * Close the sidebar
         */
        closeSidebar: function() {
            if (!this.isOpen) {
                return;
            }

            document.body.classList.remove('homa-open');
            this.isOpen = false;

            // Update global state
            if (window.Homa && window.Homa.updateState) {
                window.Homa.updateState({ isSidebarOpen: false });
            }

            // Emit via event bus (but don't dispatch DOM event to avoid recursion)
            if (window.Homa && window.Homa.emit) {
                window.Homa.emit('sidebar:closed', { timestamp: Date.now() });
            }

            // Trigger window resize after animation completes
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
                this.recalculateDiviModules();
            }, 650);
        },

        /**
         * Toggle the sidebar
         */
        toggleSidebar: function() {
            if (this.isOpen) {
                this.closeSidebar();
            } else {
                this.openSidebar();
            }
        },

        /**
         * Recalculate Divi modules after viewport change
         */
        recalculateDiviModules: function() {
            // Trigger Divi's responsive handlers
            if (window.et_pb_init_modules) {
                window.et_pb_init_modules();
            }

            // Recalculate sliders and galleries
            if (window.jQuery) {
                window.jQuery('.et_pb_slider').each(function() {
                    if (window.jQuery(this).data('et_pb_simple_slider')) {
                        window.jQuery(this).data('et_pb_simple_slider').et_slider_move_to(0);
                    }
                });

                // Trigger Isotope recalculation for galleries - with safety check
                if (typeof window.jQuery.fn.isotope === 'function') {
                    window.jQuery('.et_pb_gallery').isotope('layout');
                } else {
                    console.warn('[Homa Orchestrator] Isotope library not loaded, skipping gallery recalculation');
                }
            }
        },

        /**
         * Setup form observer to detect changes
         */
        setupFormObserver: function() {
            const siteView = document.getElementById('homa-site-view');
            if (!siteView) {
                return;
            }

            // Debounce function
            const debounce = (func, wait) => {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            };

            // Handle input changes
            const handleInputChange = debounce((event) => {
                const target = event.target;
                if (!target.name && !target.id) {
                    return;
                }

                const fieldId = target.id || target.name;
                const value = target.value;

                // Dispatch custom event for React to listen
                window.dispatchEvent(new CustomEvent('homa_site_updated', {
                    detail: {
                        fieldId: fieldId,
                        value: value,
                        fieldType: target.type,
                        element: target
                    }
                }));
            }, 300);

            // Attach listeners
            siteView.addEventListener('input', handleInputChange);
            siteView.addEventListener('change', handleInputChange);
        },

        /**
         * Execute action on site element from sidebar
         */
        executeOnSite: function(selector, action = 'highlight') {
            const element = document.querySelector(`#homa-site-view ${selector}`);
            if (!element) {
                console.warn('[Homa Orchestrator] Element not found:', selector);
                return false;
            }

            switch (action) {
                case 'highlight':
                    element.classList.add('homa-pulse');
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => {
                        element.classList.remove('homa-pulse');
                    }, 3000);
                    break;

                case 'scroll':
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    break;

                case 'click':
                    element.click();
                    break;

                case 'focus':
                    element.focus();
                    break;

                default:
                    console.warn('[Homa Orchestrator] Unknown action:', action);
                    return false;
            }

            return true;
        },

        /**
         * Get current viewport state
         */
        getViewportState: function() {
            return {
                isOpen: this.isOpen,
                siteViewWidth: document.getElementById('homa-site-view')?.offsetWidth || 0,
                sidebarViewWidth: document.getElementById('homa-sidebar-view')?.offsetWidth || 0,
                totalWidth: window.innerWidth
            };
        }
    };

    // Auto-initialize when DOM is ready - MUST run before React sidebar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize immediately - React sidebar depends on this
            if (!window.HomaOrchestrator.initialized) {
                window.HomaOrchestrator.init();
            }
        });
    } else {
        // Document already loaded, initialize now
        if (!window.HomaOrchestrator.initialized) {
            window.HomaOrchestrator.init();
        }
    }
    
    // CRITICAL FIX: Ensure sidebar container exists BEFORE any React code runs
    // This is a fail-safe to prevent blank screen errors
    setTimeout(() => {
        if (!document.getElementById('homa-sidebar-view')) {
            console.warn('[Homa Orchestrator] Sidebar container missing after init - creating emergency fallback');
            window.HomaOrchestrator.createFallbackSidebar();
        }
    }, 50); // Small delay to let init complete

})();
