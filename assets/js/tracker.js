/**
 * Homaye Tabesh - Behavioral Tracking Script
 * Compatible with Divi Theme
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

(function () {
    'use strict';

    // Configuration
    const config = window.homayeConfig || {};
    const apiUrl = config.apiUrl || '/wp-json/homaye/v1/telemetry';
    const batchUrl = config.batchUrl || '/wp-json/homaye/v1/telemetry/batch';
    const userId = config.userId || 'anonymous';
    const diviEnabled = config.diviEnabled || false;

    // Event queue for batch processing
    let eventQueue = [];
    const BATCH_SIZE = 10;
    const BATCH_INTERVAL = 5000; // 5 seconds
    const DEBOUNCE_DELAY = 300; // Debounce delay for scroll events

    // Tracked elements cache
    const trackedElements = new WeakSet();
    
    // Dwell time tracking
    const dwellTimeTracking = new Map();
    
    // Scroll depth tracking
    let maxScrollDepth = 0;
    let scrollDebounceTimer = null;

    /**
     * Send event to server
     */
    function sendEvent(eventType, element, additionalData = {}) {
        const elementData = {
            tag: element.tagName,
            id: element.id,
            text: element.textContent?.substring(0, 100),
            ...additionalData
        };

        const event = {
            event_type: eventType,
            element_class: element.className,
            element_data: elementData,
            user_id: userId,
            timestamp: Date.now()
        };

        // Add to queue
        eventQueue.push(event);

        // Send batch if queue is full
        if (eventQueue.length >= BATCH_SIZE) {
            sendBatch();
        }
    }

    /**
     * Send batch of events (with debouncing)
     */
    function sendBatch() {
        if (eventQueue.length === 0) return;

        const events = [...eventQueue];
        eventQueue = [];

        // Check for nonce
        const nonce = config.nonce || '';
        if (!nonce) {
            console.warn('Homaye Tabesh - Missing nonce, events may not be recorded');
        }

        fetch(batchUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({
                events: events,
                user_id: userId
            })
        }).catch(error => {
            console.error('Homaye Tabesh - Failed to send events:', error);
        });
    }
    
    /**
     * Track dwell time on Divi modules
     */
    function trackDwellTime(element, moduleId) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Start tracking dwell time
                    if (!dwellTimeTracking.has(moduleId)) {
                        dwellTimeTracking.set(moduleId, {
                            startTime: Date.now(),
                            element: element,
                            focused: true
                        });
                    }
                } else {
                    // Stop tracking and send event if dwell time is significant
                    const tracking = dwellTimeTracking.get(moduleId);
                    if (tracking && tracking.focused) {
                        const dwellTime = Date.now() - tracking.startTime;
                        if (dwellTime >= 1000) { // 1 second minimum
                            sendEvent('module_dwell', element, {
                                module_id: moduleId,
                                dwell_time: dwellTime,
                                viewport_ratio: entry.intersectionRatio
                            });
                        }
                        tracking.focused = false;
                    }
                }
            });
        }, { threshold: [0.5, 0.75, 1.0] });
        
        observer.observe(element);
    }
    
    /**
     * Track scroll depth with debouncing
     */
    function trackScrollDepth() {
        if (scrollDebounceTimer) {
            clearTimeout(scrollDebounceTimer);
        }
        
        scrollDebounceTimer = setTimeout(() => {
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            const scrollDepth = Math.min(100, Math.round(((scrollTop + windowHeight) / documentHeight) * 100));
            
            // Track new scroll depth milestones
            if (scrollDepth > maxScrollDepth) {
                const oldDepth = maxScrollDepth;
                maxScrollDepth = scrollDepth;
                
                // Send events at 25%, 50%, 75%, 100% milestones
                const milestones = [25, 50, 75, 100];
                milestones.forEach(milestone => {
                    if (oldDepth < milestone && scrollDepth >= milestone) {
                        sendEvent('scroll_depth', document.body, {
                            depth_percentage: milestone,
                            current_depth: scrollDepth
                        });
                    }
                });
            }
        }, DEBOUNCE_DELAY);
    }
    
    /**
     * Detect heat-point areas (high interaction zones)
     */
    function detectHeatPoints() {
        // Track click coordinates for heat-point analysis
        document.addEventListener('click', function(e) {
            const element = e.target;
            const rect = element.getBoundingClientRect();
            
            // Send heat-point data for special sections
            if (element.closest('.et_pb_pricing') || 
                element.closest('.et_pb_wc_price') || 
                element.closest('[class*="calculator"]')) {
                sendEvent('heat_point', element, {
                    x: Math.round(e.clientX),
                    y: Math.round(e.clientY),
                    element_x: Math.round(rect.left),
                    element_y: Math.round(rect.top),
                    section_type: getModuleType(element)
                });
            }
        });
    }
    
    /**
     * Get module type from element
     */
    function getModuleType(element) {
        const classes = element.className;
        if (classes.includes('et_pb_pricing')) return 'pricing_table';
        if (classes.includes('calculator')) return 'calculator';
        if (classes.includes('et_pb_wc_price')) return 'product_price';
        if (classes.includes('et_pb_wc_add_to_cart')) return 'add_to_cart';
        if (classes.includes('license') || classes.includes('permission')) return 'licensing';
        return 'unknown';
    }

    /**
     * Track hover events
     */
    function trackHover(element) {
        if (trackedElements.has(element)) return;
        trackedElements.add(element);

        let hoverTimer = null;
        let hoverStartTime = null;

        element.addEventListener('mouseenter', function () {
            hoverStartTime = Date.now();
            hoverTimer = setTimeout(() => {
                const hoverDuration = Date.now() - hoverStartTime;
                if (hoverDuration >= 2000) { // Long hover: 2+ seconds
                    sendEvent('long_view', element, { duration: hoverDuration });
                }
            }, 2000);
        });

        element.addEventListener('mouseleave', function () {
            clearTimeout(hoverTimer);
            if (hoverStartTime) {
                const hoverDuration = Date.now() - hoverStartTime;
                if (hoverDuration >= 500) { // Track meaningful hovers
                    sendEvent('hover', element, { duration: hoverDuration });
                }
            }
            hoverStartTime = null;
        });
    }

    /**
     * Track click events
     */
    function trackClick(element) {
        if (trackedElements.has(element)) return;
        trackedElements.add(element);

        element.addEventListener('click', function () {
            sendEvent('click', element);
        });
    }

    /**
     * Track scroll events
     */
    function trackScroll(element) {
        if (trackedElements.has(element)) return;
        trackedElements.add(element);

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    sendEvent('scroll_to', element, {
                        viewport_percentage: Math.round(entry.intersectionRatio * 100)
                    });
                }
            });
        }, { threshold: [0.5] });

        observer.observe(element);
    }

    /**
     * Initialize tracking for Divi elements
     */
    function initDiviTracking() {
        // Divi module selectors
        const diviSelectors = [
            '.et_pb_module',           // All Divi modules
            '.et_pb_pricing',          // Pricing tables
            '.et_pb_button',           // Buttons
            '.et_pb_cta',              // Call to action
            '.et_pb_blurb',            // Blurbs
            '.et_pb_testimonial',      // Testimonials
            '.et_pb_shop',             // WooCommerce shop
            '.et_pb_wc_price',         // Product prices
            '.et_pb_wc_add_to_cart'    // Add to cart buttons
        ];

        diviSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach((element, index) => {
                // Generate unique module ID
                const moduleId = element.id || `${selector.replace('.', '')}_${index}`;
                
                trackHover(element);
                trackClick(element);
                trackScroll(element);
                
                // Track dwell time for all Divi modules
                trackDwellTime(element, moduleId);
            });
        });
    }

    /**
     * Initialize tracking for WooCommerce elements
     */
    function initWooCommerceTracking() {
        const wooSelectors = [
            '.product',
            '.add_to_cart_button',
            '.price',
            '.woocommerce-Price-amount',
            '.product_title',
            '.product-category'
        ];

        wooSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                trackHover(element);
                trackClick(element);
                trackScroll(element);
            });
        });
    }

    /**
     * Initialize tracking for custom elements
     */
    function initCustomTracking() {
        // Track elements with specific data attributes
        const customElements = document.querySelectorAll('[data-homaye-track]');
        customElements.forEach(element => {
            const trackTypes = element.dataset.homayeTrack.split(',');
            
            trackTypes.forEach(type => {
                switch (type.trim()) {
                    case 'hover':
                        trackHover(element);
                        break;
                    case 'click':
                        trackClick(element);
                        break;
                    case 'scroll':
                        trackScroll(element);
                        break;
                }
            });
        });
    }

    /**
     * Initialize mutation observer for dynamic content
     */
    function initMutationObserver() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Re-run tracking initialization for new elements
                        if (diviEnabled) {
                            const diviElements = node.querySelectorAll?.('.et_pb_module');
                            diviElements?.forEach(el => {
                                trackHover(el);
                                trackClick(el);
                                trackScroll(el);
                            });
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Initialize tracking system
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }

        // Initialize tracking
        if (diviEnabled) {
            initDiviTracking();
        }
        initWooCommerceTracking();
        initCustomTracking();
        initMutationObserver();
        
        // Initialize scroll depth tracking
        window.addEventListener('scroll', trackScrollDepth, { passive: true });
        
        // Initialize heat-point detection
        detectHeatPoints();

        // Setup batch sending interval
        setInterval(sendBatch, BATCH_INTERVAL);

        // Send remaining events before page unload
        window.addEventListener('beforeunload', sendBatch);

        console.log('Homaye Tabesh - Advanced tracking initialized with dwell time & scroll depth');
    }

    // Start initialization
    init();
})();
