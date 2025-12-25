/**
 * Homa Interactive Tour Overlay
 * Homa-Highlight Overlay Engine for Educational Tours
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

(function () {
    'use strict';

    /**
     * Homa Tour Manager Class
     * Creates interactive tutorial tours with highlights and tooltips
     */
    class HomaTourManager {
        constructor() {
            this.config = window.homayeConfig || {};
            this.activeTour = null;
            this.currentStep = 0;
            this.overlayElement = null;
            this.highlightElement = null;
            this.tooltipElement = null;
            // Configurable z-index base - can be overridden via config
            // Note: Very high z-index (999990) may conflict with some themes
            this.zIndexBase = this.config.tourZIndexBase || 999990;
            
            this.init();
        }

        /**
         * Initialize tour manager
         */
        init() {
            console.log('Homa Tour Manager: Initializing...');
            
            this.createOverlayElements();
            this.setupStyles();
            this.exposeAPI();
        }

        /**
         * Create overlay DOM elements
         */
        createOverlayElements() {
            // Create overlay container
            this.overlayElement = document.createElement('div');
            this.overlayElement.id = 'homa-tour-overlay';
            this.overlayElement.className = 'homa-tour-overlay';
            this.overlayElement.style.display = 'none';
            document.body.appendChild(this.overlayElement);

            // Create highlight box
            this.highlightElement = document.createElement('div');
            this.highlightElement.id = 'homa-tour-highlight';
            this.highlightElement.className = 'homa-tour-highlight';
            document.body.appendChild(this.highlightElement);

            // Create tooltip
            this.tooltipElement = document.createElement('div');
            this.tooltipElement.id = 'homa-tour-tooltip';
            this.tooltipElement.className = 'homa-tour-tooltip';
            document.body.appendChild(this.tooltipElement);

            console.log('Homa Tour Manager: Overlay elements created');
        }

        /**
         * Setup CSS styles for tour elements
         */
        setupStyles() {
            if (document.getElementById('homa-tour-styles')) {
                return;
            }

            const styles = document.createElement('style');
            styles.id = 'homa-tour-styles';
            styles.textContent = `
                /* Tour Overlay - darkens the page except highlighted element */
                .homa-tour-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    z-index: ${this.zIndexBase};
                    pointer-events: none;
                    transition: opacity 0.3s ease;
                }

                .homa-tour-overlay.active {
                    display: block !important;
                    opacity: 1;
                }

                /* Highlight Box - illuminates the target element */
                .homa-tour-highlight {
                    position: absolute;
                    border: 3px solid #4a90e2;
                    border-radius: 8px;
                    box-shadow: 
                        0 0 0 9999px rgba(0, 0, 0, 0.7),
                        0 0 30px rgba(74, 144, 226, 0.8),
                        inset 0 0 20px rgba(74, 144, 226, 0.3);
                    z-index: ${this.zIndexBase + 1};
                    pointer-events: none;
                    transition: all 0.4s cubic-bezier(0.4, 0.0, 0.2, 1);
                    display: none;
                    animation: homa-tour-pulse 2s infinite;
                }

                .homa-tour-highlight.active {
                    display: block !important;
                }

                @keyframes homa-tour-pulse {
                    0%, 100% {
                        border-color: #4a90e2;
                        box-shadow: 
                            0 0 0 9999px rgba(0, 0, 0, 0.7),
                            0 0 30px rgba(74, 144, 226, 0.8),
                            inset 0 0 20px rgba(74, 144, 226, 0.3);
                    }
                    50% {
                        border-color: #5ba3ff;
                        box-shadow: 
                            0 0 0 9999px rgba(0, 0, 0, 0.7),
                            0 0 40px rgba(91, 163, 255, 1),
                            inset 0 0 30px rgba(91, 163, 255, 0.5);
                    }
                }

                /* Spatial highlight (without overlay) */
                .homa-spatial-highlight {
                    outline: 3px solid #4a90e2 !important;
                    outline-offset: 3px;
                    background: rgba(74, 144, 226, 0.1) !important;
                    transition: all 0.3s ease;
                    animation: homa-spatial-pulse 1.5s ease-in-out 3;
                }

                @keyframes homa-spatial-pulse {
                    0%, 100% {
                        outline-color: #4a90e2;
                        outline-offset: 3px;
                    }
                    50% {
                        outline-color: #5ba3ff;
                        outline-offset: 6px;
                    }
                }

                /* Tour Tooltip */
                .homa-tour-tooltip {
                    position: absolute;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 20px 24px;
                    border-radius: 12px;
                    font-size: 15px;
                    line-height: 1.6;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
                    z-index: ${this.zIndexBase + 2};
                    max-width: 400px;
                    min-width: 250px;
                    display: none;
                    animation: homa-tooltip-appear 0.4s cubic-bezier(0.4, 0.0, 0.2, 1);
                }

                .homa-tour-tooltip.active {
                    display: block !important;
                }

                @keyframes homa-tooltip-appear {
                    from {
                        opacity: 0;
                        transform: translateY(-20px) scale(0.9);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                }

                .homa-tour-tooltip::before {
                    content: '';
                    position: absolute;
                    width: 0;
                    height: 0;
                }

                .homa-tour-tooltip.position-top::before {
                    bottom: -10px;
                    left: 50%;
                    transform: translateX(-50%);
                    border-left: 10px solid transparent;
                    border-right: 10px solid transparent;
                    border-top: 10px solid #764ba2;
                }

                .homa-tour-tooltip.position-bottom::before {
                    top: -10px;
                    left: 50%;
                    transform: translateX(-50%);
                    border-left: 10px solid transparent;
                    border-right: 10px solid transparent;
                    border-bottom: 10px solid #667eea;
                }

                .homa-tour-tooltip-header {
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 12px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .homa-tour-tooltip-content {
                    margin-bottom: 16px;
                }

                .homa-tour-tooltip-footer {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-top: 16px;
                    padding-top: 16px;
                    border-top: 1px solid rgba(255, 255, 255, 0.2);
                }

                .homa-tour-step-indicator {
                    font-size: 13px;
                    opacity: 0.8;
                }

                .homa-tour-buttons {
                    display: flex;
                    gap: 8px;
                }

                .homa-tour-btn {
                    background: rgba(255, 255, 255, 0.2);
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    color: white;
                    padding: 8px 16px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    transition: all 0.2s;
                }

                .homa-tour-btn:hover {
                    background: rgba(255, 255, 255, 0.3);
                    transform: translateY(-1px);
                }

                .homa-tour-btn-primary {
                    background: rgba(255, 255, 255, 0.9);
                    color: #667eea;
                    font-weight: 600;
                }

                .homa-tour-btn-primary:hover {
                    background: white;
                }

                .homa-tour-close {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    background: transparent;
                    border: none;
                    color: white;
                    font-size: 24px;
                    cursor: pointer;
                    opacity: 0.7;
                    transition: opacity 0.2s;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .homa-tour-close:hover {
                    opacity: 1;
                }
            `;
            
            document.head.appendChild(styles);
            console.log('Homa Tour Manager: Styles injected');
        }

        /**
         * Expose public API
         */
        exposeAPI() {
            window.HomaTour = {
                start: (tour) => this.startTour(tour),
                next: () => this.nextStep(),
                previous: () => this.previousStep(),
                goToStep: (stepIndex) => this.goToStep(stepIndex),
                end: () => this.endTour(),
                isActive: () => this.activeTour !== null
            };

            // Also expose as method on window
            window.startHomaTour = (step) => this.showSingleStep(step);
        }

        /**
         * Start a multi-step tour
         * @param {Object} tour - Tour configuration
         */
        startTour(tour) {
            if (!tour || !tour.steps || tour.steps.length === 0) {
                console.error('Homa Tour: Invalid tour configuration');
                return;
            }

            this.activeTour = tour;
            this.currentStep = 0;

            console.log('Homa Tour: Starting tour -', tour.title || 'Untitled Tour');

            // Show first step
            this.showStep(this.currentStep);
        }

        /**
         * Show a specific step
         * @param {number} stepIndex - Step index
         */
        showStep(stepIndex) {
            if (!this.activeTour || !this.activeTour.steps[stepIndex]) {
                console.error('Homa Tour: Invalid step index');
                return;
            }

            const step = this.activeTour.steps[stepIndex];
            this.showSingleStep(step);
        }

        /**
         * Show a single tour step
         * @param {Object} step - Step configuration
         */
        showSingleStep(step) {
            const {
                selector,
                title = 'توجه کنید',
                message,
                position = 'auto',
                allowNext = true,
                allowPrevious = true,
                allowSkip = true
            } = step;

            // Find target element
            const targetElement = document.querySelector(selector);
            
            if (!targetElement) {
                console.warn('Homa Tour: Target element not found:', selector);
                
                // Try next step if in a tour
                if (this.activeTour) {
                    this.nextStep();
                }
                return;
            }

            // Scroll to element first
            if (window.HomaSpatialNavigator) {
                window.HomaSpatialNavigator.scrollTo(targetElement, {
                    offset: 150,
                    callback: () => {
                        this.showHighlight(targetElement);
                        this.showTooltip(targetElement, step);
                    }
                });
            } else {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => {
                    this.showHighlight(targetElement);
                    this.showTooltip(targetElement, step);
                }, 500);
            }
        }

        /**
         * Show highlight around element
         */
        showHighlight(element) {
            const rect = element.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

            // Show overlay
            this.overlayElement.classList.add('active');
            this.overlayElement.style.display = 'block';

            // Position highlight
            this.highlightElement.style.top = (rect.top + scrollTop - 5) + 'px';
            this.highlightElement.style.left = (rect.left + scrollLeft - 5) + 'px';
            this.highlightElement.style.width = (rect.width + 10) + 'px';
            this.highlightElement.style.height = (rect.height + 10) + 'px';
            this.highlightElement.classList.add('active');
            this.highlightElement.style.display = 'block';

            console.log('Homa Tour: Element highlighted');
        }

        /**
         * Show tooltip with step information
         */
        showTooltip(element, step) {
            const rect = element.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            // Build tooltip content
            const tooltipContent = this.buildTooltipContent(step);
            this.tooltipElement.innerHTML = tooltipContent;

            // Position tooltip
            const tooltipRect = this.tooltipElement.getBoundingClientRect();
            const position = this.calculateTooltipPosition(rect, tooltipRect);

            this.tooltipElement.style.top = (position.top + scrollTop) + 'px';
            this.tooltipElement.style.left = position.left + 'px';
            this.tooltipElement.className = `homa-tour-tooltip active position-${position.placement}`;
            this.tooltipElement.style.display = 'block';

            // Setup event listeners
            this.setupTooltipEvents();

            console.log('Homa Tour: Tooltip displayed');
        }

        /**
         * Build tooltip HTML content
         */
        buildTooltipContent(step) {
            const isInTour = this.activeTour !== null;
            const stepNumber = isInTour ? this.currentStep + 1 : 1;
            const totalSteps = isInTour ? this.activeTour.steps.length : 1;

            let html = `
                <button class="homa-tour-close" data-action="close">&times;</button>
                <div class="homa-tour-tooltip-header">
                    <span>${step.title || 'توجه کنید'}</span>
                </div>
                <div class="homa-tour-tooltip-content">
                    ${step.message}
                </div>
            `;

            if (isInTour) {
                html += `
                    <div class="homa-tour-tooltip-footer">
                        <div class="homa-tour-step-indicator">
                            مرحله ${stepNumber} از ${totalSteps}
                        </div>
                        <div class="homa-tour-buttons">
                `;

                if (step.allowPrevious !== false && this.currentStep > 0) {
                    html += `<button class="homa-tour-btn" data-action="previous">قبلی</button>`;
                }

                if (step.allowSkip !== false) {
                    html += `<button class="homa-tour-btn" data-action="skip">پایان تور</button>`;
                }

                if (step.allowNext !== false) {
                    const isLastStep = this.currentStep >= this.activeTour.steps.length - 1;
                    html += `<button class="homa-tour-btn homa-tour-btn-primary" data-action="next">
                        ${isLastStep ? 'پایان' : 'بعدی'}
                    </button>`;
                }

                html += `
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="homa-tour-tooltip-footer">
                        <button class="homa-tour-btn homa-tour-btn-primary" data-action="close">
                            متوجه شدم
                        </button>
                    </div>
                `;
            }

            return html;
        }

        /**
         * Calculate tooltip position
         */
        calculateTooltipPosition(elementRect, tooltipRect) {
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            
            let top, left, placement;

            // Try to position below element
            if (elementRect.bottom + tooltipRect.height + 20 < viewportHeight) {
                top = elementRect.bottom + 20;
                placement = 'bottom';
            } else {
                // Position above
                top = elementRect.top - tooltipRect.height - 20;
                placement = 'top';
            }

            // Center horizontally
            left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2) + scrollLeft;

            // Keep within viewport
            if (left < 10) left = 10;
            if (left + tooltipRect.width > viewportWidth - 10) {
                left = viewportWidth - tooltipRect.width - 10;
            }

            return { top, left, placement };
        }

        /**
         * Setup tooltip event listeners
         */
        setupTooltipEvents() {
            const buttons = this.tooltipElement.querySelectorAll('[data-action]');
            
            buttons.forEach(button => {
                button.addEventListener('click', (e) => {
                    const action = e.currentTarget.getAttribute('data-action');
                    
                    switch (action) {
                        case 'next':
                            this.nextStep();
                            break;
                        case 'previous':
                            this.previousStep();
                            break;
                        case 'skip':
                        case 'close':
                            this.endTour();
                            break;
                    }
                });
            });
        }

        /**
         * Move to next step
         */
        nextStep() {
            if (!this.activeTour) {
                this.endTour();
                return;
            }

            if (this.currentStep < this.activeTour.steps.length - 1) {
                this.currentStep++;
                this.showStep(this.currentStep);
            } else {
                this.endTour();
            }
        }

        /**
         * Move to previous step
         */
        previousStep() {
            if (!this.activeTour || this.currentStep === 0) {
                return;
            }

            this.currentStep--;
            this.showStep(this.currentStep);
        }

        /**
         * Go to specific step
         */
        goToStep(stepIndex) {
            if (!this.activeTour || stepIndex < 0 || stepIndex >= this.activeTour.steps.length) {
                console.error('Homa Tour: Invalid step index');
                return;
            }

            this.currentStep = stepIndex;
            this.showStep(this.currentStep);
        }

        /**
         * End tour and cleanup
         */
        endTour() {
            this.overlayElement.classList.remove('active');
            this.highlightElement.classList.remove('active');
            this.tooltipElement.classList.remove('active');

            setTimeout(() => {
                this.overlayElement.style.display = 'none';
                this.highlightElement.style.display = 'none';
                this.tooltipElement.style.display = 'none';
            }, 300);

            this.activeTour = null;
            this.currentStep = 0;

            console.log('Homa Tour: Tour ended');
        }
    }

    // Initialize tour manager when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.HomaTourManager = new HomaTourManager();
        });
    } else {
        window.HomaTourManager = new HomaTourManager();
    }

    console.log('Homa Tour Manager module loaded');
})();
