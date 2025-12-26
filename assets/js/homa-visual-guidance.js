/**
 * Homa Visual Guidance Engine
 * Client-side controller for visual DOM actions
 * 
 * @package HomayeTabesh
 * @since PR10
 */

(function($) {
    'use strict';

    /**
     * Visual Guidance Engine Class
     * Manages all visual interactions on the client side
     */
    class HomaVisualGuidance {
        constructor() {
            this.config = window.homaVisualConfig || {};
            this.activeElements = new Map();
            this.tooltipCounter = 0;
            this.init();
        }

        /**
         * Initialize the visual guidance engine
         */
        init() {
            console.log('[Homa Visual Guidance] Initializing...');

            // Listen for visual command events
            if (window.Homa) {
                window.Homa.on('visual:action', (action) => {
                    this.executeAction(action);
                });

                window.Homa.on('ai:command', (command) => {
                    // Check if this is a visual command
                    if (this.isVisualCommand(command)) {
                        this.executeAction(command);
                    }
                });
            }

            // Register with HomaCommandInterpreter
            if (window.HomaCommandInterpreter) {
                console.log('[Homa Visual Guidance] Connected to Command Interpreter');
            }

            console.log('[Homa Visual Guidance] Ready');
        }

        /**
         * Check if command is a visual command
         * 
         * @param {Object} command Command object
         * @returns {boolean}
         */
        isVisualCommand(command) {
            if (!command) return false;

            const cmd = (command.command || '').toUpperCase();
            const actionType = (command.action_type || '').toLowerCase();
            
            const visualCommands = ['HIGHLIGHT', 'SCROLL_TO', 'SHOW_TOOLTIP', 'GLOW', 'PULSE'];
            const visualActionTypes = ['ui_interaction', 'ui_command', 'visual'];

            return visualCommands.includes(cmd) || 
                   visualActionTypes.includes(actionType) ||
                   command.target_selector !== undefined;
        }

        /**
         * Execute a visual action
         * 
         * @param {Object} action Action object
         */
        executeAction(action) {
            if (!action) {
                console.warn('[Homa Visual Guidance] Empty action received');
                return;
            }

            console.log('[Homa Visual Guidance] Executing action:', action);

            const command = (action.command || action.action_type || '').toUpperCase();
            const selector = action.target_selector || action.selector || action.target;

            if (!selector) {
                console.warn('[Homa Visual Guidance] No selector provided for action:', action);
                return;
            }

            const element = document.querySelector(selector);
            if (!element) {
                console.warn('[Homa Visual Guidance] Element not found:', selector);
                return;
            }

            // Execute based on command type
            switch (command) {
                case 'HIGHLIGHT':
                    this.highlightElement(element, action);
                    break;

                case 'SCROLL_TO':
                    this.scrollToElement(element, action);
                    break;

                case 'SHOW_TOOLTIP':
                case 'TOOLTIP':
                    this.showTooltip(element, action);
                    break;

                case 'GLOW':
                    this.glowElement(element, action);
                    break;

                case 'PULSE':
                    this.pulseElement(element, action);
                    break;

                default:
                    // Try to infer action
                    if (action.message) {
                        this.showTooltip(element, action);
                    } else {
                        this.highlightElement(element, action);
                    }
            }

            // Emit completion event
            if (window.Homa) {
                window.Homa.emit('visual:action_completed', {
                    action: action,
                    selector: selector,
                    timestamp: Date.now()
                });
            }
        }

        /**
         * Highlight an element with glow effect
         * 
         * @param {HTMLElement} element Target element
         * @param {Object} action Action configuration
         */
        highlightElement(element, action) {
            const duration = action.duration || this.config.defaultDuration || 5000;
            const elementId = this.getElementId(element);

            // Remove existing highlight if any
            if (this.activeElements.has(elementId)) {
                this.clearElementEffects(elementId);
            }

            // Add highlight class
            element.classList.add('homa-highlight', 'homa-glow-effect');
            
            // Scroll into view
            this.scrollToElement(element, { smooth: true, offset: 100 });

            // Store active element
            const timeoutId = setTimeout(() => {
                element.classList.remove('homa-highlight', 'homa-glow-effect');
                this.activeElements.delete(elementId);
            }, duration);

            this.activeElements.set(elementId, {
                element: element,
                timeoutId: timeoutId,
                type: 'highlight'
            });

            console.log(`[Homa Visual Guidance] Highlighted element for ${duration}ms:`, element);
        }

        /**
         * Scroll to element smoothly
         * 
         * @param {HTMLElement} element Target element
         * @param {Object} options Scroll options
         */
        scrollToElement(element, options = {}) {
            const offset = options.offset || this.config.scrollOffset || 100;
            const smooth = options.smooth !== false;

            const elementTop = element.getBoundingClientRect().top + window.pageYOffset;
            const offsetPosition = elementTop - offset;

            window.scrollTo({
                top: offsetPosition,
                behavior: smooth ? 'smooth' : 'auto'
            });

            console.log('[Homa Visual Guidance] Scrolled to element:', element);
        }

        /**
         * Show tooltip near element
         * 
         * @param {HTMLElement} element Target element
         * @param {Object} action Action configuration
         */
        showTooltip(element, action) {
            const message = action.message || action.text || 'توجه کنید به این بخش';
            const duration = action.duration || this.config.defaultDuration || 10000;
            const tooltipId = `homa-tooltip-${++this.tooltipCounter}`;

            // Create tooltip element
            const tooltip = document.createElement('div');
            tooltip.id = tooltipId;
            tooltip.className = 'homa-visual-tooltip';
            tooltip.innerHTML = `
                <div class="homa-tooltip-content">${message}</div>
                <button class="homa-tooltip-close" aria-label="بستن">&times;</button>
            `;

            // Position tooltip
            const rect = element.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
            
            tooltip.style.top = `${rect.top + scrollTop - 80}px`;
            tooltip.style.left = `${rect.left + scrollLeft}px`;

            // Add to DOM
            document.body.appendChild(tooltip);

            // Animate in
            setTimeout(() => {
                tooltip.classList.add('homa-tooltip-visible');
            }, 10);

            // Scroll element into view
            this.scrollToElement(element, { smooth: true });

            // Add highlight to element
            element.classList.add('homa-tooltip-target');

            // Close button handler
            const closeBtn = tooltip.querySelector('.homa-tooltip-close');
            closeBtn.addEventListener('click', () => {
                this.removeTooltip(tooltipId, element);
            });

            // Auto-remove after duration
            const timeoutId = setTimeout(() => {
                this.removeTooltip(tooltipId, element);
            }, duration);

            // Store reference
            this.activeElements.set(tooltipId, {
                element: tooltip,
                target: element,
                timeoutId: timeoutId,
                type: 'tooltip'
            });

            console.log('[Homa Visual Guidance] Tooltip shown:', message);
        }

        /**
         * Remove tooltip
         * 
         * @param {string} tooltipId Tooltip element ID
         * @param {HTMLElement} targetElement Target element
         */
        removeTooltip(tooltipId, targetElement) {
            const tooltipData = this.activeElements.get(tooltipId);
            if (!tooltipData) return;

            const tooltip = tooltipData.element;
            
            // Animate out
            tooltip.classList.remove('homa-tooltip-visible');
            
            // Remove from DOM after animation
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }, 300);

            // Remove highlight from target
            if (targetElement) {
                targetElement.classList.remove('homa-tooltip-target');
            }

            // Clear timeout
            if (tooltipData.timeoutId) {
                clearTimeout(tooltipData.timeoutId);
            }

            // Remove from active elements
            this.activeElements.delete(tooltipId);
        }

        /**
         * Add glow effect to element
         * 
         * @param {HTMLElement} element Target element
         * @param {Object} action Action configuration
         */
        glowElement(element, action) {
            const duration = action.duration || this.config.defaultDuration || 5000;
            const elementId = this.getElementId(element);

            element.classList.add('homa-glow-effect');

            const timeoutId = setTimeout(() => {
                element.classList.remove('homa-glow-effect');
                this.activeElements.delete(elementId);
            }, duration);

            this.activeElements.set(elementId, {
                element: element,
                timeoutId: timeoutId,
                type: 'glow'
            });
        }

        /**
         * Add pulse effect to element
         * 
         * @param {HTMLElement} element Target element
         * @param {Object} action Action configuration
         */
        pulseElement(element, action) {
            const duration = action.duration || this.config.defaultDuration || 5000;
            const elementId = this.getElementId(element);

            element.classList.add('homa-pulse-effect');

            const timeoutId = setTimeout(() => {
                element.classList.remove('homa-pulse-effect');
                this.activeElements.delete(elementId);
            }, duration);

            this.activeElements.set(elementId, {
                element: element,
                timeoutId: timeoutId,
                type: 'pulse'
            });
        }

        /**
         * Get unique ID for element
         * 
         * @param {HTMLElement} element
         * @returns {string}
         */
        getElementId(element) {
            if (element.id) {
                return `id-${element.id}`;
            }
            
            // Generate unique ID based on element position
            const rect = element.getBoundingClientRect();
            return `pos-${rect.top}-${rect.left}`;
        }

        /**
         * Clear all effects from element
         * 
         * @param {string} elementId Element ID
         */
        clearElementEffects(elementId) {
            const elementData = this.activeElements.get(elementId);
            if (!elementData) return;

            const element = elementData.element;
            
            // Clear timeout
            if (elementData.timeoutId) {
                clearTimeout(elementData.timeoutId);
            }

            // Remove classes
            element.classList.remove(
                'homa-highlight',
                'homa-glow-effect',
                'homa-pulse-effect',
                'homa-tooltip-target'
            );

            // Remove from active elements
            this.activeElements.delete(elementId);
        }

        /**
         * Clear all active visual effects
         */
        clearAll() {
            console.log('[Homa Visual Guidance] Clearing all visual effects');

            this.activeElements.forEach((data, id) => {
                if (data.type === 'tooltip') {
                    this.removeTooltip(id, data.target);
                } else {
                    this.clearElementEffects(id);
                }
            });

            this.activeElements.clear();
        }

        /**
         * Get active elements count
         * 
         * @returns {number}
         */
        getActiveCount() {
            return this.activeElements.size;
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof window.HomaVisualGuidance === 'undefined') {
            window.HomaVisualGuidance = new HomaVisualGuidance();
            console.log('[Homa Visual Guidance] Engine initialized');
        }
    });

    // Export for debugging
    if (!window.HomaDebug) {
        window.HomaDebug = {};
    }

    window.HomaDebug.VisualGuidance = {
        clearAll: () => window.HomaVisualGuidance?.clearAll(),
        getActiveCount: () => window.HomaVisualGuidance?.getActiveCount() || 0
    };

})(jQuery);
