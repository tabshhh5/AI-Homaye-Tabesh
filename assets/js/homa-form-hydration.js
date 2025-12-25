/**
 * Homa Form Hydration
 * Bridge between React Sidebar and Shortcode Forms
 * Auto-fills and syncs form fields with chat-confirmed values
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

(function () {
    'use strict';

    /**
     * Form Hydration Manager
     * Injects data from chat into shortcode forms and triggers recalculations
     */
    class HomaFormHydration {
        constructor() {
            this.config = window.homayePerceptionConfig || window.homayeConfig || {};
            this.pendingSync = new Map(); // Queue for syncing data
            this.syncedFields = new Set(); // Track already synced fields
            this.fieldValueCache = new Map(); // Cache current values
            this.calculationTriggers = new Set(); // Elements that trigger price recalculation
            
            this.init();
        }

        /**
         * Initialize form hydration system
         */
        init() {
            console.log('Homa Form Hydration: Initializing form auto-fill system...');

            // Wait for indexer to be ready
            if (window.Homa && window.Homa.Indexer) {
                this.setupHydration();
            } else {
                // Wait for indexer
                document.addEventListener('homa:indexer:ready', () => {
                    this.setupHydration();
                });
            }

            // Listen for sync requests from chat/React sidebar
            this.setupSyncListeners();
        }

        /**
         * Setup hydration system
         */
        setupHydration() {
            console.log('Homa Form Hydration: System ready');

            // Detect form frameworks (Gravity Forms, Contact Form 7, etc.)
            this.detectFormFramework();

            // Setup mutation observer for AJAX-loaded forms
            this.setupAjaxFormWatcher();

            // Expose API
            this.exposeAPI();
        }

        /**
         * Detect which form framework is being used
         */
        detectFormFramework() {
            this.formFramework = {
                gravityForms: !!document.querySelector('.gform_wrapper'),
                contactForm7: !!document.querySelector('.wpcf7'),
                wpForms: !!document.querySelector('.wpforms-form'),
                elementorForms: !!document.querySelector('.elementor-form'),
                diviContactForm: !!document.querySelector('.et_pb_contact_form'),
                generic: true
            };

            console.log('Homa Form Hydration: Detected frameworks:', this.formFramework);
        }

        /**
         * Watch for AJAX-loaded forms and reattach listeners
         */
        setupAjaxFormWatcher() {
            // jQuery AJAX complete handler for form frameworks
            if (window.jQuery) {
                let ajaxDebounce = null;
                
                jQuery(document).ajaxComplete((event, xhr, settings) => {
                    console.log('Homa Form Hydration: AJAX detected, re-scanning forms...');
                    
                    // Debounced re-scan to handle multiple rapid AJAX calls
                    clearTimeout(ajaxDebounce);
                    ajaxDebounce = setTimeout(() => {
                        this.detectFormFramework();
                        // Re-index if indexer is available
                        if (window.Homa && window.Homa.Indexer && window.Homa.Indexer.scanPage) {
                            window.Homa.Indexer.scanPage();
                        }
                        // Process any pending syncs after re-scan
                        this.processPendingSync();
                    }, 300); // Reduced to 300ms with debouncing for better responsiveness
                });
            }
            
            // Alternative: Use MutationObserver as backup for non-jQuery forms
            const observer = new MutationObserver((mutations) => {
                const hasFormChanges = mutations.some(mutation => {
                    return Array.from(mutation.addedNodes).some(node => {
                        return node.nodeName === 'FORM' || 
                               (node.querySelector && node.querySelector('form'));
                    });
                });
                
                if (hasFormChanges) {
                    console.log('Homa Form Hydration: New form detected via MutationObserver');
                    this.detectFormFramework();
                    // Process pending syncs
                    setTimeout(() => this.processPendingSync(), 100);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        /**
         * Setup listeners for sync requests
         */
        setupSyncListeners() {
            // Listen for sync requests from chat
            document.addEventListener('homa:sync-field', (e) => {
                const { fieldName, value, triggerRecalc } = e.detail;
                this.syncField(fieldName, value, triggerRecalc !== false);
            });

            // Listen for bulk sync
            document.addEventListener('homa:sync-bulk', (e) => {
                const { fields } = e.detail;
                this.syncBulk(fields);
            });

            // Listen for form reset
            document.addEventListener('homa:reset-form', () => {
                this.resetForm();
            });
        }

        /**
         * Sync a single field with a value
         * @param {string} fieldIdentifier - Field name, ID, or semantic name
         * @param {any} value - Value to set
         * @param {boolean} triggerRecalc - Whether to trigger price recalculation
         */
        syncField(fieldIdentifier, value, triggerRecalc = true) {
            console.log(`Homa Form Hydration: Syncing field "${fieldIdentifier}" with value:`, value);

            // Find the field element
            const field = this.findField(fieldIdentifier);

            if (!field) {
                console.warn(`Homa Form Hydration: Field "${fieldIdentifier}" not found`);
                this.pendingSync.set(fieldIdentifier, { value, triggerRecalc });
                return false;
            }

            // Set the value using appropriate method
            const success = this.setFieldValue(field, value);

            if (success) {
                this.syncedFields.add(fieldIdentifier);
                this.fieldValueCache.set(fieldIdentifier, value);

                // Trigger recalculation if needed
                if (triggerRecalc) {
                    this.triggerRecalculation(field);
                }

                // Dispatch success event
                document.dispatchEvent(new CustomEvent('homa:field-synced', {
                    detail: { fieldIdentifier, value, field }
                }));

                return true;
            }

            return false;
        }

        /**
         * Sync multiple fields at once
         * @param {Object} fields - Object with field identifiers as keys and values
         */
        syncBulk(fields) {
            console.log('Homa Form Hydration: Bulk sync:', fields);

            let successCount = 0;
            const fieldEntries = Object.entries(fields);

            fieldEntries.forEach(([fieldId, value], index) => {
                // Don't trigger recalc on each field, only on the last one
                const isLast = index === fieldEntries.length - 1;
                if (this.syncField(fieldId, value, isLast)) {
                    successCount++;
                }
            });

            console.log(`Homa Form Hydration: Synced ${successCount}/${fieldEntries.length} fields`);

            return successCount;
        }

        /**
         * Find field element by various identifiers
         */
        findField(identifier) {
            // Try direct ID
            let field = document.getElementById(identifier);
            if (field) return field;

            // Try name attribute
            field = document.querySelector(`[name="${identifier}"]`);
            if (field) return field;

            // Try data attribute
            field = document.querySelector(`[data-homa-semantic="${identifier}"]`);
            if (field) return field;

            // Try semantic search via indexer
            if (window.Homa && window.Homa.Indexer && window.Homa.Indexer.findBySemanticName) {
                field = window.Homa.Indexer.findBySemanticName(identifier);
                if (field) return field;
            }

            // Try partial name match
            field = document.querySelector(`[name*="${identifier}"]`);
            if (field) return field;

            // Try label text search
            const labels = document.querySelectorAll('label');
            for (const label of labels) {
                if (label.textContent.toLowerCase().includes(identifier.toLowerCase())) {
                    const forAttr = label.getAttribute('for');
                    if (forAttr) {
                        field = document.getElementById(forAttr);
                        if (field) return field;
                    }
                    // Check next sibling
                    const sibling = label.nextElementSibling;
                    if (sibling && (sibling.tagName === 'INPUT' || sibling.tagName === 'SELECT' || sibling.tagName === 'TEXTAREA')) {
                        return sibling;
                    }
                }
            }

            return null;
        }

        /**
         * Set field value with proper triggering of events
         * Uses Object.defineProperty to ensure form frameworks detect the change
         */
        setFieldValue(field, value) {
            const tagName = field.tagName.toLowerCase();
            const fieldType = field.type?.toLowerCase();

            try {
                // Store original value
                const originalValue = field.value;

                // Different handling for different field types
                switch (tagName) {
                    case 'input':
                        if (fieldType === 'checkbox') {
                            field.checked = !!value;
                            this.dispatchNativeEvent(field, 'change');
                        } else if (fieldType === 'radio') {
                            if (field.value === value) {
                                field.checked = true;
                                this.dispatchNativeEvent(field, 'change');
                            }
                        } else {
                            this.setTextValue(field, value);
                        }
                        break;

                    case 'select':
                        this.setSelectValue(field, value);
                        break;

                    case 'textarea':
                        this.setTextValue(field, value);
                        break;

                    default:
                        field.value = value;
                        this.dispatchNativeEvent(field, 'change');
                }

                console.log(`Homa Form Hydration: Successfully set value for ${field.name || field.id}`);
                return true;

            } catch (error) {
                console.error('Homa Form Hydration: Error setting field value:', error);
                return false;
            }
        }

        /**
         * Set text value with proper event triggering
         */
        setTextValue(field, value) {
            // Method 1: Direct assignment
            field.value = value;

            // Method 2: Using Object.defineProperty to bypass React/form framework internals
            const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
                window.HTMLInputElement.prototype,
                'value'
            )?.set;

            if (nativeInputValueSetter) {
                nativeInputValueSetter.call(field, value);
            }

            // Trigger all necessary events
            this.dispatchNativeEvent(field, 'input');
            this.dispatchNativeEvent(field, 'change');
            this.dispatchNativeEvent(field, 'blur');

            // Trigger jQuery events if jQuery is available
            if (window.jQuery && window.jQuery(field).length) {
                window.jQuery(field).trigger('input').trigger('change');
            }
        }

        /**
         * Set select value
         */
        setSelectValue(field, value) {
            // Try exact match
            let optionFound = false;

            for (let i = 0; i < field.options.length; i++) {
                if (field.options[i].value === value || field.options[i].text === value) {
                    field.selectedIndex = i;
                    optionFound = true;
                    break;
                }
            }

            // Try partial match if exact match fails
            if (!optionFound) {
                for (let i = 0; i < field.options.length; i++) {
                    if (field.options[i].text.toLowerCase().includes(value.toLowerCase())) {
                        field.selectedIndex = i;
                        optionFound = true;
                        break;
                    }
                }
            }

            if (optionFound) {
                this.dispatchNativeEvent(field, 'change');
                
                if (window.jQuery) {
                    window.jQuery(field).trigger('change');
                }
            }
        }

        /**
         * Dispatch native event
         */
        dispatchNativeEvent(element, eventType) {
            const event = new Event(eventType, {
                bubbles: true,
                cancelable: true
            });
            element.dispatchEvent(event);
        }

        /**
         * Trigger form recalculation (for price calculators, conditional logic, etc.)
         */
        triggerRecalculation(field) {
            console.log('Homa Form Hydration: Triggering recalculation...');

            // Find parent form
            const form = field.closest('form');

            if (form) {
                // Trigger form validation/calculation events
                this.dispatchNativeEvent(form, 'change');
                this.dispatchNativeEvent(form, 'input');

                // Gravity Forms specific
                if (this.formFramework.gravityForms && window.gform) {
                    const formId = form.id.replace('gform_', '');
                    if (window.gform.doCalculation) {
                        window.gform.doCalculation(formId);
                    }
                }

                // WPForms specific
                if (this.formFramework.wpForms && window.wpforms) {
                    // WPForms uses jQuery triggers
                    if (window.jQuery) {
                        window.jQuery(form).trigger('wpformsFieldUpdate');
                    }
                }

                // Generic calculation trigger - look for calculate buttons or functions
                const calculateButton = form.querySelector('[data-calculate], .calculate-button, [name*="calculate"]');
                if (calculateButton) {
                    calculateButton.click();
                }
            }

            // Trigger custom event for external listeners
            document.dispatchEvent(new CustomEvent('homa:recalculation-triggered', {
                detail: { field, form }
            }));
        }

        /**
         * Reset form to initial state
         */
        resetForm() {
            console.log('Homa Form Hydration: Resetting form...');

            this.syncedFields.clear();
            this.fieldValueCache.clear();

            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.reset();
            });
        }

        /**
         * Process pending syncs (for fields that weren't found initially)
         */
        processPendingSync() {
            if (this.pendingSync.size === 0) return;

            console.log(`Homa Form Hydration: Processing ${this.pendingSync.size} pending syncs...`);

            const processed = [];
            
            this.pendingSync.forEach((data, fieldId) => {
                if (this.syncField(fieldId, data.value, data.triggerRecalc)) {
                    processed.push(fieldId);
                }
            });

            // Remove processed items
            processed.forEach(fieldId => this.pendingSync.delete(fieldId));

            if (this.pendingSync.size > 0) {
                console.log(`Homa Form Hydration: ${this.pendingSync.size} syncs still pending`);
            }
        }

        /**
         * Expose public API
         */
        exposeAPI() {
            // Create public API
            const api = {
                syncField: this.syncField.bind(this),
                syncBulk: this.syncBulk.bind(this),
                resetForm: this.resetForm.bind(this),
                findField: this.findField.bind(this),
                getSyncedFields: () => Array.from(this.syncedFields),
                getPendingSync: () => Array.from(this.pendingSync.keys()),
                processPendingSync: this.processPendingSync.bind(this)
            };

            // Attach to Homa namespace
            if (window.Homa) {
                window.Homa.FormHydration = api;
                window.Homa.actions = window.Homa.actions || {};
                window.Homa.actions.syncField = api.syncField;
            }

            // Make globally accessible
            window.HomaFormHydration = api;

            console.log('Homa Form Hydration: API exposed');
        }
    }

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new HomaFormHydration();
        });
    } else {
        new HomaFormHydration();
    }

    console.log('Homa Form Hydration: Module loaded successfully');
})();
