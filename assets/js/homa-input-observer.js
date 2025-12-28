/**
 * Homa Live Input Observer
 * Asynchronous Buffer Streaming for Real-time Intent Detection
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

(function () {
    'use strict';

    /**
     * Homa Input Observer Class
     * Monitors user input and detects intent in real-time
     */
    class HomaInputObserver {
        constructor() {
            // Use consistent config name
            this.config = window.homayePerceptionConfig || window.homayeConfig || {};
            this.apiUrl = this.config.apiUrl || '/wp-json/homaye/v1/ai/analyze-intent';
            this.debounceDelay = this.config.inputDebounceDelay || 800; // Configurable delay
            this.minChars = this.config.inputMinChars || 3; // Configurable minimum chars
            this.inputBuffer = new Map(); // Buffer for each input field
            this.activeTimers = new Map(); // Debounce timers
            
            // Configurable list of sensitive keywords for privacy protection
            this.ignoredFields = new Set(
                this.config.sensitiveFieldKeywords || [
                    'password', 'passwd', 'pwd',
                    'credit', 'card', 'cvv', 'cvc',
                    'ssn', 'social', 'security',
                    'account', 'routing',
                    'national_id', 'کد_ملی', 'کدملی'
                ]
            );
            this.observedInputs = new WeakSet();
            this.intentCallbacks = [];
            
            // Mutation observer debounce
            this.attachTimer = null;
            this.attachDelay = 500; // 500ms debounce for attach observers
            
            this.init();
        }

        /**
         * Initialize the input observer
         */
        init() {
            console.log('Homa Input Observer: Initializing live input monitoring...');
            
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.attachObservers());
            } else {
                this.attachObservers();
            }

            // Listen for dynamic content
            this.initMutationObserver();
        }

        /**
         * Attach observers to all input elements
         */
        attachObservers(rootElement = document.body) {
            const inputSelectors = [
                'input[type="text"]',
                'input[type="email"]',
                'input[type="tel"]',
                'input[type="search"]',
                'textarea',
                'input:not([type="password"]):not([type="hidden"])'
            ];

            const inputs = rootElement.querySelectorAll(inputSelectors.join(', '));
            let attachedCount = 0;

            inputs.forEach((input) => {
                if (!this.observedInputs.has(input) && !this.shouldIgnoreField(input)) {
                    this.observeInput(input);
                    this.observedInputs.add(input);
                    attachedCount++;
                }
            });

            console.log(`Homa Input Observer: Attached to ${attachedCount} new input fields`);
        }

        /**
         * Check if field should be ignored for privacy
         */
        shouldIgnoreField(input) {
            // Check data attribute for ignore flag
            if (input.hasAttribute('data-homa-ignore')) {
                return true;
            }

            // Check type
            const type = input.getAttribute('type')?.toLowerCase();
            if (type === 'password' || type === 'hidden') {
                return true;
            }

            // Check for sensitive field indicators
            const name = input.getAttribute('name')?.toLowerCase() || '';
            const id = input.id?.toLowerCase() || '';
            const placeholder = input.getAttribute('placeholder')?.toLowerCase() || '';
            
            // Check against configured sensitive keywords
            return Array.from(this.ignoredFields).some(keyword => 
                name.includes(keyword) || 
                id.includes(keyword) || 
                placeholder.includes(keyword)
            );
        }

        /**
         * Observe individual input element
         */
        observeInput(input) {
            const inputId = input.id || input.name || this.generateInputId(input);

            input.addEventListener('input', (e) => {
                this.handleInput(e.target, inputId);
            });

            input.addEventListener('focus', (e) => {
                this.handleFocus(e.target, inputId);
            });

            input.addEventListener('blur', (e) => {
                this.handleBlur(e.target, inputId);
            });
        }

        /**
         * Handle input event
         */
        handleInput(input, inputId) {
            const value = input.value.trim();

            // Update buffer
            this.inputBuffer.set(inputId, {
                value: value,
                input: input,
                fieldName: this.getFieldName(input),
                timestamp: Date.now()
            });

            // Clear existing timer
            if (this.activeTimers.has(inputId)) {
                clearTimeout(this.activeTimers.get(inputId));
            }

            // Set debounce timer
            if (value.length >= this.minChars) {
                const timer = setTimeout(() => {
                    this.analyzeIntent(inputId);
                }, this.debounceDelay);

                this.activeTimers.set(inputId, timer);
            }
        }

        /**
         * Handle focus event
         */
        handleFocus(input, inputId) {
            console.log('Homa Input Observer: Field focused -', this.getFieldName(input));
            
            // Notify callbacks
            this.notifyCallbacks('focus', {
                inputId: inputId,
                fieldName: this.getFieldName(input),
                element: input
            });
        }

        /**
         * Handle blur event
         */
        handleBlur(input, inputId) {
            const bufferData = this.inputBuffer.get(inputId);
            
            if (bufferData && bufferData.value.length >= this.minChars) {
                // Final analysis on blur
                this.analyzeIntent(inputId, true);
            }
        }

        /**
         * Analyze user intent from input
         */
        analyzeIntent(inputId, isFinal = false) {
            const bufferData = this.inputBuffer.get(inputId);
            
            if (!bufferData) return;

            const { value, input, fieldName } = bufferData;

            console.log(`Homa Input Observer: Analyzing intent for "${fieldName}" - "${value.substring(0, 30)}..."`);

            // Extract concepts from input
            const concepts = this.extractConcepts(value);

            // Create analysis payload
            const payload = {
                field_name: fieldName,
                field_value: value,
                concepts: concepts,
                is_final: isFinal,
                timestamp: Date.now()
            };

            // Notify callbacks immediately for responsive UI
            this.notifyCallbacks('intent_detected', {
                inputId: inputId,
                fieldName: fieldName,
                value: value,
                concepts: concepts,
                element: input
            });

            // Send to server for AI analysis (if available)
            if (this.config.enableIntentAnalysis !== false) {
                this.sendToServer(payload, inputId);
            }
        }

        /**
         * Extract concepts from input text
         */
        extractConcepts(text) {
            const concepts = {
                keywords: [],
                length: text.length,
                wordCount: text.split(/\s+/).length,
                hasNumbers: /\d/.test(text),
                hasPersian: /[\u0600-\u06FF]/.test(text),
                hasEnglish: /[a-zA-Z]/.test(text)
            };

            // Extract meaningful words (3+ chars)
            const words = text.split(/\s+/).filter(word => word.length >= 3);
            concepts.keywords = words.slice(0, 10); // First 10 keywords

            // Detect patterns
            concepts.patterns = this.detectPatterns(text);

            return concepts;
        }

        /**
         * Detect patterns in input text
         */
        detectPatterns(text) {
            const patterns = [];

            // Persian patterns
            if (/کتاب|کتب|نشریه/.test(text)) patterns.push('book_related');
            if (/چاپ|چابک|نشر/.test(text)) patterns.push('printing_related');
            if (/رمان|داستان|قصه/.test(text)) patterns.push('story_related');
            if (/کودک|بچه|نوجوان/.test(text)) patterns.push('children_related');
            if (/طراحی|گرافیک|تصویر/.test(text)) patterns.push('design_related');
            if (/تیراژ|نسخه|عدد/.test(text)) patterns.push('quantity_related');
            if (/قیمت|هزینه|تومان/.test(text)) patterns.push('price_related');

            // English patterns
            if (/book|publication/i.test(text)) patterns.push('book_related');
            if (/print|press|publish/i.test(text)) patterns.push('printing_related');
            if (/novel|story|fiction/i.test(text)) patterns.push('story_related');
            if (/child|kid|teen/i.test(text)) patterns.push('children_related');
            if (/design|graphic|illustration/i.test(text)) patterns.push('design_related');

            // Number patterns
            const numbers = text.match(/\d+/g);
            if (numbers) {
                const maxNumber = Math.max(...numbers.map(n => parseInt(n)));
                if (maxNumber > 100 && maxNumber < 100000) {
                    patterns.push('quantity_indicator');
                }
            }

            return patterns;
        }

        /**
         * Send intent data to server
         */
        sendToServer(payload, inputId) {
            const nonce = this.config.nonce || '';
            
            fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Homa Input Observer: Server response:', data);
                
                // Notify callbacks with server response
                this.notifyCallbacks('server_response', {
                    inputId: inputId,
                    response: data
                });
            })
            .catch(error => {
                console.error('Homa Input Observer: Server error:', error);
            });
        }

        /**
         * Get field name from input element
         */
        getFieldName(input) {
            // Try various attributes
            const label = input.closest('label')?.textContent?.trim() ||
                         document.querySelector(`label[for="${input.id}"]`)?.textContent?.trim();
            
            if (label) return label;

            const placeholder = input.getAttribute('placeholder');
            if (placeholder) return placeholder;

            const ariaLabel = input.getAttribute('aria-label');
            if (ariaLabel) return ariaLabel;

            const name = input.getAttribute('name');
            if (name) return name;

            return input.id || 'unnamed_field';
        }

        /**
         * Generate unique input ID
         */
        generateInputId(input) {
            const name = input.name || input.id || 'input';
            const timestamp = Date.now();
            const random = Math.random().toString(36).substring(2, 9);
            return `${name}_${timestamp}_${random}`;
        }

        /**
         * Register callback for intent events
         */
        onIntent(callback) {
            if (typeof callback === 'function') {
                this.intentCallbacks.push(callback);
            }
        }

        /**
         * Notify all callbacks
         */
        notifyCallbacks(eventType, data) {
            this.intentCallbacks.forEach(callback => {
                try {
                    callback(eventType, data);
                } catch (error) {
                    console.error('Homa Input Observer: Callback error:', error);
                }
            });
        }

        /**
         * Setup mutation observer for dynamic inputs
         */
        initMutationObserver() {
            const observer = new MutationObserver((mutations) => {
                let shouldAttach = false;

                mutations.forEach((mutation) => {
                    if (mutation.addedNodes.length > 0) {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                const hasInputs = 
                                    node.matches('input, textarea') ||
                                    node.querySelector('input, textarea');
                                
                                if (hasInputs) {
                                    shouldAttach = true;
                                }
                            }
                        });
                    }
                });

                if (shouldAttach) {
                    // Debounce the attach to avoid multiple rapid attachments
                    if (this.attachTimer) {
                        clearTimeout(this.attachTimer);
                    }
                    
                    this.attachTimer = setTimeout(() => {
                        console.log('Homa Input Observer: Detected new inputs, attaching observers...');
                        this.attachObservers();
                        this.attachTimer = null;
                    }, this.attachDelay);
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            console.log('Homa Input Observer: Mutation observer active (with debouncing)');
        }

        /**
         * Get buffer data for input
         */
        getBufferData(inputId) {
            return this.inputBuffer.get(inputId);
        }

        /**
         * Clear buffer
         */
        clearBuffer() {
            this.inputBuffer.clear();
            this.activeTimers.forEach(timer => clearTimeout(timer));
            this.activeTimers.clear();
        }
    }

    // Initialize input observer when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.HomaInputObserver = new HomaInputObserver();
        });
    } else {
        window.HomaInputObserver = new HomaInputObserver();
    }

    console.log('Homa Input Observer module loaded');
})();
