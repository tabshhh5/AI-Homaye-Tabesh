/**
 * Homa Spatial Navigation API
 * Smart Auto-Scroll and Element Focus Management
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

(function () {
    'use strict';

    /**
     * Homa Spatial Navigator Class
     * Provides intelligent navigation and focus management
     */
    class HomaSpatialNavigator {
        constructor() {
            this.config = window.homayeConfig || {};
            this.currentFocus = null;
            this.navigationHistory = [];
            this.maxHistoryLength = 50;
            this.scrollDuration = 800; // Default scroll animation duration (ms)
            this.scrollOffset = 100; // Offset from top when scrolling to element
            
            this.init();
        }

        /**
         * Initialize navigator
         */
        init() {
            console.log('Homa Spatial Navigator: Initializing...');
            
            // Expose API methods to window
            this.exposeAPI();
        }

        /**
         * Expose public API
         */
        exposeAPI() {
            window.HomaNavigation = {
                scrollTo: (target, options) => this.scrollTo(target, options),
                focusElement: (target, options) => this.focusElement(target, options),
                highlightElement: (target, options) => this.highlightElement(target, options),
                navigateToField: (fieldName) => this.navigateToField(fieldName),
                navigateBack: () => this.navigateBack(),
                getNavigationHistory: () => this.getNavigationHistory(),
                centerElement: (target) => this.centerElement(target)
            };
        }

        /**
         * Scroll to element smoothly
         * @param {string|Element} target - CSS selector or element
         * @param {Object} options - Scroll options
         */
        scrollTo(target, options = {}) {
            const element = this.resolveElement(target);
            
            if (!element) {
                console.warn('Homa Navigation: Element not found:', target);
                return Promise.reject(new Error('Element not found'));
            }

            const {
                offset = this.scrollOffset,
                duration = this.scrollDuration,
                behavior = 'smooth',
                block = 'start',
                inline = 'nearest',
                callback = null
            } = options;

            // Record navigation
            this.recordNavigation('scroll', element);

            // Use native smooth scroll if available
            if (behavior === 'smooth' && 'scrollIntoView' in element) {
                return new Promise((resolve) => {
                    element.scrollIntoView({
                        behavior: 'smooth',
                        block: block,
                        inline: inline
                    });

                    // Adjust for offset
                    if (offset !== 0) {
                        setTimeout(() => {
                            window.scrollBy({
                                top: -offset,
                                behavior: 'smooth'
                            });
                        }, 100);
                    }

                    setTimeout(() => {
                        if (callback) callback(element);
                        resolve(element);
                    }, duration);
                });
            } else {
                // Fallback to immediate scroll
                const elementTop = element.getBoundingClientRect().top + window.pageYOffset;
                window.scrollTo({
                    top: elementTop - offset,
                    behavior: behavior
                });

                if (callback) callback(element);
                return Promise.resolve(element);
            }
        }

        /**
         * Focus on element (scroll and highlight)
         * @param {string|Element} target - CSS selector or element
         * @param {Object} options - Focus options
         */
        focusElement(target, options = {}) {
            const element = this.resolveElement(target);
            
            if (!element) {
                console.warn('Homa Navigation: Element not found:', target);
                return Promise.reject(new Error('Element not found'));
            }

            const {
                highlight = true,
                scroll = true,
                duration = this.scrollDuration
            } = options;

            this.currentFocus = element;

            return new Promise((resolve) => {
                const actions = [];

                // Scroll to element
                if (scroll) {
                    actions.push(this.scrollTo(element, options));
                }

                // Highlight element
                if (highlight) {
                    Promise.all(actions).then(() => {
                        this.highlightElement(element, options);
                        resolve(element);
                    });
                } else {
                    Promise.all(actions).then(() => resolve(element));
                }
            });
        }

        /**
         * Highlight element visually
         * @param {string|Element} target - CSS selector or element
         * @param {Object} options - Highlight options
         */
        highlightElement(target, options = {}) {
            const element = this.resolveElement(target);
            
            if (!element) {
                console.warn('Homa Navigation: Element not found:', target);
                return;
            }

            const {
                duration = 3000,
                className = 'homa-spatial-highlight',
                pulseCount = 3
            } = options;

            // Add highlight class
            element.classList.add(className);

            // Remove after duration
            setTimeout(() => {
                element.classList.remove(className);
            }, duration);

            console.log('Homa Navigation: Element highlighted');
        }

        /**
         * Navigate to field by semantic name
         * @param {string} fieldName - Semantic field name
         */
        navigateToField(fieldName) {
            if (!window.HomaIndexer) {
                console.error('Homa Navigation: Indexer not available');
                return Promise.reject(new Error('Indexer not available'));
            }

            const fieldData = window.HomaIndexer.findBySemanticName(fieldName);
            
            if (!fieldData) {
                console.warn('Homa Navigation: Field not found:', fieldName);
                return Promise.reject(new Error('Field not found'));
            }

            return this.focusElement(fieldData.element, {
                highlight: true,
                scroll: true
            });
        }

        /**
         * Center element in viewport
         * @param {string|Element} target - CSS selector or element
         */
        centerElement(target) {
            const element = this.resolveElement(target);
            
            if (!element) {
                console.warn('Homa Navigation: Element not found:', target);
                return Promise.reject(new Error('Element not found'));
            }

            const rect = element.getBoundingClientRect();
            const elementCenter = rect.top + rect.height / 2;
            const viewportCenter = window.innerHeight / 2;
            const scrollAmount = elementCenter - viewportCenter + window.pageYOffset;

            return new Promise((resolve) => {
                window.scrollTo({
                    top: scrollAmount,
                    behavior: 'smooth'
                });

                setTimeout(() => {
                    resolve(element);
                }, this.scrollDuration);
            });
        }

        /**
         * Navigate back to previous element
         */
        navigateBack() {
            if (this.navigationHistory.length < 2) {
                console.warn('Homa Navigation: No previous navigation');
                return Promise.reject(new Error('No previous navigation'));
            }

            // Remove current
            this.navigationHistory.pop();
            
            // Get previous
            const previous = this.navigationHistory[this.navigationHistory.length - 1];
            
            if (previous && previous.element) {
                return this.focusElement(previous.element);
            }

            return Promise.reject(new Error('Previous element not available'));
        }

        /**
         * Get navigation history
         */
        getNavigationHistory() {
            return this.navigationHistory.map(item => ({
                type: item.type,
                timestamp: item.timestamp,
                element: item.element ? {
                    tag: item.element.tagName,
                    id: item.element.id,
                    className: item.element.className
                } : null
            }));
        }

        /**
         * Record navigation action
         */
        recordNavigation(type, element) {
            this.navigationHistory.push({
                type: type,
                element: element,
                timestamp: Date.now()
            });

            // Limit history length
            if (this.navigationHistory.length > this.maxHistoryLength) {
                this.navigationHistory.shift();
            }
        }

        /**
         * Resolve element from string or element
         */
        resolveElement(target) {
            if (typeof target === 'string') {
                return document.querySelector(target);
            } else if (target instanceof Element) {
                return target;
            }
            return null;
        }

        /**
         * Get element coordinates
         */
        getElementCoordinates(target) {
            const element = this.resolveElement(target);
            
            if (!element) {
                return null;
            }

            const rect = element.getBoundingClientRect();
            
            return {
                top: rect.top + window.pageYOffset,
                left: rect.left + window.pageXOffset,
                bottom: rect.bottom + window.pageYOffset,
                right: rect.right + window.pageXOffset,
                width: rect.width,
                height: rect.height,
                centerX: rect.left + rect.width / 2,
                centerY: rect.top + rect.height / 2,
                inViewport: this.isInViewport(rect)
            };
        }

        /**
         * Check if element is in viewport
         */
        isInViewport(rect) {
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }

        /**
         * Find nearest element to coordinates
         */
        findNearestElement(x, y, elementType = null) {
            if (!window.HomaIndexer) {
                console.error('Homa Navigation: Indexer not available');
                return null;
            }

            const allElements = elementType 
                ? window.HomaIndexer.findByType(elementType)
                : window.HomaIndexer.getAll();

            let nearest = null;
            let minDistance = Infinity;

            allElements.forEach(elementData => {
                const rect = elementData.element.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                
                const distance = Math.sqrt(
                    Math.pow(centerX - x, 2) + Math.pow(centerY - y, 2)
                );

                if (distance < minDistance) {
                    minDistance = distance;
                    nearest = elementData;
                }
            });

            return nearest;
        }

        /**
         * Scroll through a sequence of elements
         * @param {Array} targets - Array of selectors or elements
         * @param {Object} options - Navigation options
         */
        scrollSequence(targets, options = {}) {
            const {
                delay = 2000,
                highlight = true,
                callback = null
            } = options;

            let currentIndex = 0;

            const navigateNext = () => {
                if (currentIndex >= targets.length) {
                    if (callback) callback();
                    return;
                }

                const target = targets[currentIndex];
                
                this.focusElement(target, {
                    highlight: highlight,
                    scroll: true
                }).then(() => {
                    currentIndex++;
                    setTimeout(navigateNext, delay);
                }).catch((error) => {
                    console.error('Homa Navigation: Sequence error:', error);
                    currentIndex++;
                    setTimeout(navigateNext, delay);
                });
            };

            navigateNext();
        }

        /**
         * Clear current focus
         */
        clearFocus() {
            this.currentFocus = null;
        }
    }

    // Initialize spatial navigator when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.HomaSpatialNavigator = new HomaSpatialNavigator();
        });
    } else {
        window.HomaSpatialNavigator = new HomaSpatialNavigator();
    }

    console.log('Homa Spatial Navigator module loaded');
})();
