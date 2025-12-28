/**
 * Homa Conversion Triggers
 * Behavioral Intervention Engine - Exit Intent, Scroll Depth, Field Hesitation
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

(function () {
    'use strict';

    /**
     * Conversion Triggers Manager
     * Detects user hesitation and triggers intervention opportunities
     */
    class HomaConversionTriggers {
        constructor() {
            this.config = window.homayePerceptionConfig || window.homayeConfig || {};
            this.exitIntentShown = false;
            this.scrollDepthMarkers = new Set([25, 50, 75, 90]);
            this.scrollDepthReached = new Set();
            this.fieldHesitationTimers = new Map();
            this.mouseVelocityBuffer = [];
            this.lastMouseY = 0;
            this.lastMouseTime = 0;
            this.idleTimeTracking = new Map();
            this.priceChangeCount = 0;
            this.formCompletionPercentage = 0;

            this.init();
        }

        /**
         * Initialize the conversion triggers system
         */
        init() {
            console.log('Homa Conversion Triggers: Initializing behavioral monitoring...');

            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setupTriggers());
            } else {
                this.setupTriggers();
            }
        }

        /**
         * Setup all trigger mechanisms
         */
        setupTriggers() {
            this.setupExitIntent();
            this.setupScrollDepth();
            this.setupFieldHesitation();
            this.setupPriceChangeDetection();
            this.setupFormCompletionTracking();

            console.log('Homa Conversion Triggers: All triggers active');
        }

        /**
         * Exit Intent Detection
         * Tracks mouse velocity and detects when user moves cursor towards browser toolbar
         */
        setupExitIntent() {
            // Mouse movement tracking with velocity calculation
            document.addEventListener('mousemove', (e) => {
                const currentTime = Date.now();
                const currentY = e.clientY;

                // Calculate velocity (pixels per millisecond)
                if (this.lastMouseTime > 0) {
                    const deltaY = currentY - this.lastMouseY;
                    const deltaTime = currentTime - this.lastMouseTime;
                    const velocity = deltaY / deltaTime; // negative = moving up

                    this.mouseVelocityBuffer.push({ velocity, y: currentY, time: currentTime });

                    // Keep buffer size manageable
                    if (this.mouseVelocityBuffer.length > 10) {
                        this.mouseVelocityBuffer.shift();
                    }
                }

                this.lastMouseY = currentY;
                this.lastMouseTime = currentTime;
            }, { passive: true });

            // Exit intent detection
            document.addEventListener('mouseleave', (e) => {
                // Check if mouse is leaving from the top (towards toolbar)
                if (e.clientY < 0 && !this.exitIntentShown) {
                    // Calculate average upward velocity
                    const recentVelocities = this.mouseVelocityBuffer.slice(-5);
                    const avgVelocity = recentVelocities.reduce((sum, item) => sum + item.velocity, 0) / recentVelocities.length;

                    // If moving upward with significant velocity (threshold: -0.5 px/ms)
                    if (avgVelocity < -0.5) {
                        this.triggerExitIntent();
                    }
                }
            }, { passive: true });

            // Additional exit intent on beforeunload (backup)
            window.addEventListener('beforeunload', (e) => {
                if (!this.exitIntentShown && this.formCompletionPercentage > 20 && this.formCompletionPercentage < 100) {
                    this.triggerExitIntent();
                }
            });
        }

        /**
         * Trigger exit intent intervention
         */
        triggerExitIntent() {
            this.exitIntentShown = true;

            console.log('Homa: Exit intent detected!');

            // Dispatch to Homa system
            this.dispatchTrigger('EXIT_INTENT', {
                type: 'CART_RECOVERY',
                formCompletion: this.formCompletionPercentage,
                context: this.getUserContext()
            });

            // Update persona with hesitation signal
            this.updatePersonaBehavior('exit_intent_detected', {
                completion: this.formCompletionPercentage
            });
        }

        /**
         * Scroll Depth Tracking
         * Triggers offers at specific scroll depths
         */
        setupScrollDepth() {
            let scrollDebounce = null;

            window.addEventListener('scroll', () => {
                clearTimeout(scrollDebounce);
                scrollDebounce = setTimeout(() => {
                    this.checkScrollDepth();
                }, 150);
            }, { passive: true });
        }

        /**
         * Check current scroll depth and trigger interventions
         */
        checkScrollDepth() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrollPercent = Math.round((scrollTop / docHeight) * 100);

            // Check each marker
            this.scrollDepthMarkers.forEach(marker => {
                if (scrollPercent >= marker && !this.scrollDepthReached.has(marker)) {
                    this.scrollDepthReached.add(marker);
                    this.triggerScrollDepth(marker);
                }
            });
        }

        /**
         * Trigger scroll depth intervention
         */
        triggerScrollDepth(depth) {
            console.log(`Homa: Scroll depth ${depth}% reached`);

            this.dispatchTrigger('SCROLL_DEPTH', {
                depth: depth,
                action: this.getScrollDepthAction(depth),
                context: this.getUserContext()
            });
        }

        /**
         * Get appropriate action for scroll depth
         */
        getScrollDepthAction(depth) {
            if (depth >= 90) {
                return 'OFFER_STRONG_DISCOUNT';
            } else if (depth >= 75) {
                return 'SHOW_TESTIMONIALS';
            } else if (depth >= 50) {
                return 'HIGHLIGHT_BENEFITS';
            } else {
                return 'ENGAGE_CONVERSATION';
            }
        }

        /**
         * Field Hesitation Detection
         * Tracks time spent idle on form fields
         */
        setupFieldHesitation() {
            // Monitor all input fields
            const fields = document.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea, select');

            fields.forEach(field => {
                this.attachFieldMonitors(field);
            });

            // Watch for dynamically added fields
            const observer = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) {
                            const newFields = node.querySelectorAll?.('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea, select');
                            newFields?.forEach(field => this.attachFieldMonitors(field));
                        }
                    });
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });
        }

        /**
         * Attach monitoring to a field
         */
        attachFieldMonitors(field) {
            // Track focus time
            field.addEventListener('focus', () => {
                const fieldId = this.getFieldIdentifier(field);
                this.idleTimeTracking.set(fieldId, {
                    focusTime: Date.now(),
                    lastActivity: Date.now(),
                    field: field
                });

                // Start hesitation timer
                this.startHesitationTimer(fieldId);
            });

            // Track activity
            field.addEventListener('input', () => {
                const fieldId = this.getFieldIdentifier(field);
                const tracking = this.idleTimeTracking.get(fieldId);
                if (tracking) {
                    tracking.lastActivity = Date.now();
                }
            });

            // Track blur
            field.addEventListener('blur', () => {
                const fieldId = this.getFieldIdentifier(field);
                this.stopHesitationTimer(fieldId);
                this.idleTimeTracking.delete(fieldId);
            });
        }

        /**
         * Start hesitation timer for a field
         */
        startHesitationTimer(fieldId) {
            const timer = setTimeout(() => {
                this.triggerFieldHesitation(fieldId);
            }, 60000); // 60 seconds of idle time

            this.fieldHesitationTimers.set(fieldId, timer);
        }

        /**
         * Stop hesitation timer
         */
        stopHesitationTimer(fieldId) {
            const timer = this.fieldHesitationTimers.get(fieldId);
            if (timer) {
                clearTimeout(timer);
                this.fieldHesitationTimers.delete(fieldId);
            }
        }

        /**
         * Trigger field hesitation intervention
         */
        triggerFieldHesitation(fieldId) {
            const tracking = this.idleTimeTracking.get(fieldId);
            if (!tracking) return;

            const idleTime = Date.now() - tracking.lastActivity;

            console.log(`Homa: Field hesitation detected on ${fieldId} (idle: ${idleTime}ms)`);

            this.dispatchTrigger('FIELD_HESITATION', {
                fieldId: fieldId,
                idleTime: idleTime,
                action: 'ASK_FOR_HELP',
                context: this.getUserContext()
            });
        }

        /**
         * Price Change Detection
         * Tracks when user changes price-affecting fields multiple times
         */
        setupPriceChangeDetection() {
            // Monitor fields that affect pricing
            const priceFields = document.querySelectorAll('[data-price-field], .price-affecting-field, input[name*="quantity"], input[name*="pages"], select[name*="binding"], select[name*="cover"]');

            priceFields.forEach(field => {
                let lastValue = field.value;

                field.addEventListener('change', () => {
                    if (field.value !== lastValue) {
                        this.priceChangeCount++;
                        lastValue = field.value;

                        console.log(`Homa: Price change detected (count: ${this.priceChangeCount})`);

                        // If user changed price-affecting fields more than 5 times
                        if (this.priceChangeCount > 5) {
                            this.triggerPriceHesitation();
                        }
                    }
                });
            });
        }

        /**
         * Trigger price hesitation intervention
         */
        triggerPriceHesitation() {
            console.log('Homa: Price hesitation detected - user struggling with pricing');

            this.dispatchTrigger('PRICE_HESITATION', {
                changeCount: this.priceChangeCount,
                action: 'OFFER_DISCOUNT',
                context: this.getUserContext()
            });

            // Reset counter after intervention
            this.priceChangeCount = 0;
        }

        /**
         * Form Completion Tracking
         * Calculates percentage of form completion
         */
        setupFormCompletionTracking() {
            setInterval(() => {
                this.updateFormCompletion();
            }, 5000); // Check every 5 seconds
        }

        /**
         * Update form completion percentage
         */
        updateFormCompletion() {
            const allFields = document.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea, select');
            if (allFields.length === 0) return;

            let filledCount = 0;
            allFields.forEach(field => {
                if (field.value && field.value.trim() !== '') {
                    filledCount++;
                }
            });

            this.formCompletionPercentage = Math.round((filledCount / allFields.length) * 100);
        }

        /**
         * Get field identifier
         */
        getFieldIdentifier(field) {
            return field.id || field.name || field.getAttribute('data-homa-id') || `field_${Math.random().toString(36).substr(2, 9)}`;
        }

        /**
         * Get user context for interventions
         */
        getUserContext() {
            return {
                formCompletion: this.formCompletionPercentage,
                priceChangeCount: this.priceChangeCount,
                scrollDepth: Math.max(...Array.from(this.scrollDepthReached), 0),
                idleTime: this.getMaxIdleTime(),
                timestamp: Date.now(),
                pageUrl: window.location.href
            };
        }

        /**
         * Get maximum idle time across all fields
         */
        getMaxIdleTime() {
            let maxIdle = 0;
            this.idleTimeTracking.forEach(tracking => {
                const idle = Date.now() - tracking.lastActivity;
                if (idle > maxIdle) {
                    maxIdle = idle;
                }
            });
            return maxIdle;
        }

        /**
         * Dispatch trigger to Homa system
         */
        dispatchTrigger(triggerType, data) {
            // Dispatch to Homa event system
            const event = new CustomEvent('homa:trigger', {
                detail: {
                    trigger: triggerType,
                    data: data
                }
            });
            document.dispatchEvent(event);

            // Send to backend if API available
            if (this.config.apiUrl) {
                this.sendTriggerToBackend(triggerType, data);
            }
        }

        /**
         * Send trigger to backend
         */
        sendTriggerToBackend(triggerType, data) {
            const nonce = this.config.nonce || '';
            
            fetch(`${this.config.apiUrl || '/wp-json/homaye/v1'}/conversion/trigger`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    trigger_type: triggerType,
                    trigger_data: data,
                    session_id: this.getSessionId()
                })
            }).catch(error => {
                console.warn('Homa: Failed to send trigger to backend:', error);
            });
        }

        /**
         * Update persona behavior
         */
        updatePersonaBehavior(event, data) {
            const nonce = this.config.nonce || '';

            fetch(`${this.config.apiUrl || '/wp-json/homaye/v1'}/telemetry/behavior`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    event_type: event,
                    event_data: data,
                    session_id: this.getSessionId()
                })
            }).catch(error => {
                console.warn('Homa: Failed to update persona:', error);
            });
        }

        /**
         * Get session ID
         */
        getSessionId() {
            return this.config.sessionId || this.config.userId || 'anonymous';
        }

        /**
         * Check if intervention should be triggered
         */
        checkInterventionNeed(userData) {
            if (userData.idleTime > 60000 && userData.formCompletion < 50) {
                return "ASK_FOR_HELP";
            }
            if (userData.priceChangeCount > 5) {
                return "OFFER_DISCOUNT";
            }
            if (userData.formCompletion > 70 && userData.scrollDepth > 75) {
                return "PUSH_TO_CHECKOUT";
            }
            return null;
        }
    }

    // Initialize when ready
    if (window.Homa) {
        window.Homa.ConversionTriggers = new HomaConversionTriggers();
    } else {
        // Create Homa namespace if it doesn't exist
        window.Homa = window.Homa || {};
        window.Homa.ConversionTriggers = new HomaConversionTriggers();
    }

    console.log('Homa Conversion Triggers: Module loaded successfully');
})();
