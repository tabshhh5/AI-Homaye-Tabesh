/**
 * Homa Semantic Indexer
 * Tree-Walker Semantic Indexing for Divi Elements and WooCommerce Forms
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

(function () {
    'use strict';

    /**
     * Homa Semantic Indexer Class
     * Scans and indexes all important DOM elements for Homa's spatial awareness
     */
    class HomaIndexer {
        constructor() {
            this.map = new Map();
            this.fieldMap = new Map(); // Maps semantic names to elements
            this.observedElements = new WeakSet();
            // Use consistent config name
            this.config = window.homayePerceptionConfig || window.homayeConfig || {};
            // Debounce timer for mutation observer
            this.rescanTimer = null;
            this.rescanDelay = 500; // 500ms debounce
            this.init();
        }

        /**
         * Initialize the indexer
         */
        init() {
            console.log('Homa Indexer: Initializing semantic mapping...');
            
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.scanPage());
            } else {
                this.scanPage();
            }

            // Setup mutation observer for dynamic content
            this.initMutationObserver();
        }

        /**
         * Scan the entire page and build semantic map
         */
        scanPage(rootElement = document.body) {
            console.log('Homa Indexer: Scanning page for elements...');

            // Core element selectors to index
            const selectors = [
                // Form elements
                'input[type="text"]',
                'input[type="email"]',
                'input[type="tel"]',
                'input[type="number"]',
                'textarea',
                'select',
                'button[type="submit"]',
                
                // Divi modules
                '.et_pb_module',
                '.et_pb_section',
                '.et_pb_row',
                '.et_pb_button',
                '.et_pb_pricing',
                '.et_pb_cta',
                
                // WooCommerce elements
                '.price',
                '.product',
                '.add_to_cart_button',
                '.woocommerce-Price-amount',
                
                // Custom tracked elements
                '[data-homaye-track]',
                '[data-homa-semantic]'
            ];

            const allElements = rootElement.querySelectorAll(selectors.join(', '));
            let indexedCount = 0;

            allElements.forEach((element) => {
                if (!this.observedElements.has(element)) {
                    this.indexElement(element);
                    this.observedElements.add(element);
                    indexedCount++;
                }
            });

            console.log(`Homa Indexer: Indexed ${indexedCount} new elements. Total: ${this.map.size}`);
            
            // Expose to window for debugging (in namespaced debug object)
            // Using getter to prevent external modification
            // Only define once to prevent "Cannot redefine property" errors
            if (!window.HomaDebug) {
                window.HomaDebug = {};
            }
            
            // Check if property already exists before defining
            if (!Object.prototype.hasOwnProperty.call(window.HomaDebug, 'IndexerMap')) {
                Object.defineProperty(window.HomaDebug, 'IndexerMap', {
                    get: () => {
                        // Return a read-only representation
                        const readOnlyMap = new Map();
                        this.map.forEach((value, key) => {
                            readOnlyMap.set(key, {...value});
                        });
                        return readOnlyMap;
                    },
                    enumerable: true,
                    configurable: true // Allow reconfiguration if needed
                });
            }

            // Emit indexer ready event
            if (window.Homa && window.Homa.emit) {
                window.Homa.emit('indexer:ready', {
                    map: this.getAll(),
                    count: this.map.size
                });
                window.Homa.updateState({ 
                    indexerReady: true,
                    pageMap: this.getAll()
                });
            }

            // Setup change listeners on indexed form elements
            this.setupChangeListeners();
        }

        /**
         * Index a single element
         */
        indexElement(element) {
            const semanticKey = this.getSemanticKey(element);
            const elementData = {
                element: element,
                rect: element.getBoundingClientRect(),
                type: element.tagName.toLowerCase(),
                semanticName: semanticKey,
                fieldMeaning: this.getFieldMeaning(element),
                isVisible: this.isVisible(element),
                diviModule: this.getDiviModuleType(element),
                timestamp: Date.now()
            };

            // Store in main map with unique ID
            const uniqueId = element.id || this.generateUniqueId(element);
            this.map.set(uniqueId, elementData);

            // Store in semantic field map for easy lookup
            if (semanticKey) {
                this.fieldMap.set(semanticKey, elementData);
            }
        }

        /**
         * Get semantic key for an element
         */
        getSemanticKey(element) {
            // Priority order for semantic identification
            const semanticAttr = element.getAttribute('data-homa-semantic');
            if (semanticAttr) return this.slugify(semanticAttr);

            const placeholder = element.getAttribute('placeholder');
            if (placeholder) return this.slugify(placeholder);

            const ariaLabel = element.getAttribute('aria-label');
            if (ariaLabel) return this.slugify(ariaLabel);

            const id = element.id;
            if (id) return this.slugify(id);

            const name = element.getAttribute('name');
            if (name) return this.slugify(name);

            // For labels, use their text content
            const label = this.findAssociatedLabel(element);
            if (label) return this.slugify(label.textContent);

            // For buttons, use text content
            if (element.tagName === 'BUTTON' || element.classList.contains('button')) {
                return this.slugify(element.textContent);
            }

            return null;
        }

        /**
         * Get field meaning from label or placeholder
         */
        getFieldMeaning(element) {
            // Check for explicit semantic attribute
            const semantic = element.getAttribute('data-homa-semantic');
            if (semantic) return semantic;

            // Find associated label
            const label = this.findAssociatedLabel(element);
            if (label) return label.textContent.trim();

            // Use placeholder
            const placeholder = element.getAttribute('placeholder');
            if (placeholder) return placeholder;

            // Use aria-label
            const ariaLabel = element.getAttribute('aria-label');
            if (ariaLabel) return ariaLabel;

            // For buttons, use text
            if (element.tagName === 'BUTTON' || element.classList.contains('button')) {
                return element.textContent.trim();
            }

            return 'Unknown field';
        }

        /**
         * Find associated label for input element
         */
        findAssociatedLabel(element) {
            // Check for explicit label with "for" attribute
            if (element.id) {
                const label = document.querySelector(`label[for="${element.id}"]`);
                if (label) return label;
            }

            // Check for parent label
            const parentLabel = element.closest('label');
            if (parentLabel) return parentLabel;

            // Check for preceding sibling label
            let sibling = element.previousElementSibling;
            while (sibling) {
                if (sibling.tagName === 'LABEL') {
                    return sibling;
                }
                sibling = sibling.previousElementSibling;
            }

            return null;
        }

        /**
         * Get Divi module type
         */
        getDiviModuleType(element) {
            const classes = element.className;
            
            if (classes.includes('et_pb_pricing')) return 'pricing_table';
            if (classes.includes('et_pb_button')) return 'button';
            if (classes.includes('et_pb_cta')) return 'call_to_action';
            if (classes.includes('et_pb_blurb')) return 'blurb';
            if (classes.includes('et_pb_testimonial')) return 'testimonial';
            if (classes.includes('et_pb_shop')) return 'shop';
            if (classes.includes('et_pb_contact')) return 'contact_form';
            
            return null;
        }

        /**
         * Check if element is visible
         */
        isVisible(element) {
            const rect = element.getBoundingClientRect();
            const style = window.getComputedStyle(element);
            
            return (
                rect.width > 0 &&
                rect.height > 0 &&
                style.display !== 'none' &&
                style.visibility !== 'hidden' &&
                style.opacity !== '0'
            );
        }

        /**
         * Generate unique ID for element
         */
        generateUniqueId(element) {
            const tag = element.tagName.toLowerCase();
            const classes = element.className.replace(/\s+/g, '_');
            const timestamp = Date.now();
            const random = Math.random().toString(36).substring(2, 9);
            
            return `${tag}_${classes}_${timestamp}_${random}`;
        }

        /**
         * Slugify text for use as key
         */
        slugify(text) {
            if (!text) return '';
            return text
                .toLowerCase()
                .trim()
                .replace(/[\s\u200C\u200B]+/g, '_') // Replace spaces and zero-width chars
                .replace(/[^\w\u0600-\u06FF_-]/g, '') // Keep alphanumeric, Persian, underscore, dash
                .replace(/_{2,}/g, '_') // Replace multiple underscores
                .replace(/^_|_$/g, ''); // Trim underscores
        }

        /**
         * Setup mutation observer for dynamic content
         */
        initMutationObserver() {
            const observer = new MutationObserver((mutations) => {
                let shouldRescan = false;

                mutations.forEach((mutation) => {
                    if (mutation.addedNodes.length > 0) {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                // Check if added node contains form elements or Divi modules
                                const hasRelevantElements = 
                                    node.matches('input, textarea, select, button, .et_pb_module') ||
                                    node.querySelector('input, textarea, select, button, .et_pb_module');
                                
                                if (hasRelevantElements) {
                                    shouldRescan = true;
                                }
                            }
                        });
                    }
                });

                if (shouldRescan) {
                    // Debounce the rescan to avoid multiple rapid rescans
                    if (this.rescanTimer) {
                        clearTimeout(this.rescanTimer);
                    }
                    
                    this.rescanTimer = setTimeout(() => {
                        console.log('Homa Indexer: Detected dynamic content, rescanning...');
                        this.scanPage();
                        this.rescanTimer = null;
                    }, this.rescanDelay);
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            console.log('Homa Indexer: Mutation observer active for dynamic content (with debouncing)');
        }

        /**
         * Find element by semantic name
         */
        findBySemanticName(semanticName) {
            const key = this.slugify(semanticName);
            return this.fieldMap.get(key) || null;
        }

        /**
         * Find elements by type
         */
        findByType(type) {
            const results = [];
            this.map.forEach((data, key) => {
                if (data.type === type) {
                    results.push(data);
                }
            });
            return results;
        }

        /**
         * Find elements by Divi module type
         */
        findByDiviModule(moduleType) {
            const results = [];
            this.map.forEach((data, key) => {
                if (data.diviModule === moduleType) {
                    results.push(data);
                }
            });
            return results;
        }

        /**
         * Get all indexed elements
         */
        getAll() {
            return Array.from(this.map.values());
        }

        /**
         * Get spatial navigation data
         */
        getSpatialData(elementId) {
            const data = this.map.get(elementId);
            if (!data) return null;

            // Refresh bounding box
            data.rect = data.element.getBoundingClientRect();
            
            return {
                element: data.element,
                rect: data.rect,
                semanticName: data.semanticName,
                fieldMeaning: data.fieldMeaning,
                centerX: data.rect.left + data.rect.width / 2,
                centerY: data.rect.top + data.rect.height / 2,
                inViewport: this.isInViewport(data.rect)
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
         * Clear the index
         */
        clear() {
            this.map.clear();
            this.fieldMap.clear();
            console.log('Homa Indexer: Index cleared');
        }

        /**
         * Setup change listeners on indexed form elements
         */
        setupChangeListeners() {
            if (!window.Homa || !window.Homa.emit) {
                console.warn('Homa Indexer: Event bus not ready, skipping change listeners');
                return;
            }

            // Debounce helper
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

            // Setup listener for each indexed form field
            this.map.forEach((data, key) => {
                const element = data.element;
                
                // Only for form elements
                if (!['input', 'textarea', 'select'].includes(data.type)) {
                    return;
                }

                // Avoid duplicate listeners
                if (element.dataset.homaListenerAttached) {
                    return;
                }

                // Mark as having listener
                element.dataset.homaListenerAttached = 'true';

                const handler = debounce((e) => {
                    const value = e.target.value;
                    
                    // Emit site input change event
                    window.Homa.emit('site:input_change', {
                        field: key,
                        value: value,
                        meaning: data.fieldMeaning,
                        semanticName: data.semanticName,
                        element: element,
                        type: data.type
                    });

                    console.log(`[Homa Sync] Field "${data.fieldMeaning}" changed to: ${value}`);
                }, 300);

                element.addEventListener('input', handler);
                element.addEventListener('change', handler);
            });

            console.log('Homa Indexer: Change listeners attached to form elements');
        }

        /**
         * Refresh index for specific element
         */
        refreshElement(element) {
            const uniqueId = element.id || this.generateUniqueId(element);
            const existingData = this.map.get(uniqueId);
            
            if (existingData) {
                this.indexElement(element);
            }
        }
    }

    // Initialize indexer when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.HomaIndexer = new HomaIndexer();
        });
    } else {
        window.HomaIndexer = new HomaIndexer();
    }

    console.log('Homa Indexer module loaded');
})();
