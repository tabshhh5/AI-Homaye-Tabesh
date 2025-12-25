/**
 * Homa Orchestrator
 * Manages the viewport squeeze logic and parallel interaction
 */
(function() {
    'use strict';

    window.HomaOrchestrator = {
        initialized: false,
        isOpen: false,

        /**
         * Initialize the orchestrator
         */
        init: function() {
            if (this.initialized) {
                return;
            }

            this.setupGlobalWrapper();
            this.setupEventListeners();
            this.initialized = true;
            
            console.log('[Homa Orchestrator] Initialized');
        },

        /**
         * Setup the global wrapper structure
         */
        setupGlobalWrapper: function() {
            // Check if wrapper already exists
            if (document.getElementById('homa-global-wrapper')) {
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

            // Move body content into site view
            while (document.body.firstChild) {
                siteView.appendChild(document.body.firstChild);
            }

            // Assemble structure
            wrapper.appendChild(siteView);
            wrapper.appendChild(sidebarView);
            document.body.appendChild(wrapper);

            // Set body styles
            document.body.style.margin = '0';
            document.body.style.padding = '0';
            document.body.style.overflow = 'hidden';
        },

        /**
         * Setup event listeners
         */
        setupEventListeners: function() {
            // Listen for toggle events
            document.addEventListener('homa:open-sidebar', () => {
                this.openSidebar();
            });

            document.addEventListener('homa:close-sidebar', () => {
                this.closeSidebar();
            });

            document.addEventListener('homa:toggle-sidebar', () => {
                this.toggleSidebar();
            });

            // Listen for form changes on the site
            this.setupFormObserver();
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

            // Dispatch event for React
            document.dispatchEvent(new CustomEvent('homa:toggle-sidebar', {
                detail: { isOpen: true }
            }));

            // Emit via event bus
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

            // Dispatch event for React
            document.dispatchEvent(new CustomEvent('homa:toggle-sidebar', {
                detail: { isOpen: false }
            }));

            // Emit via event bus
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

                // Trigger Isotope recalculation for galleries
                window.jQuery('.et_pb_gallery').isotope('layout');
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

    // Auto-initialize when DOM is ready (after React setup)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            // Wait a bit for other scripts to load
            setTimeout(() => {
                if (!window.HomaOrchestrator.initialized) {
                    window.HomaOrchestrator.init();
                }
            }, 100);
        });
    }

})();
