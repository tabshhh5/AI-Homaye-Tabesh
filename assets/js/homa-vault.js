/**
 * Homa Vault - Client-side Storage Manager
 * Connects Event Bus to Database Persistence
 * 
 * @package HomayeTabesh
 * @since PR7
 */

(function() {
    'use strict';

    if (!window.Homa) {
        console.error('[Homa Vault] Event Bus not found. Make sure homa-event-bus.js is loaded first.');
        return;
    }

    /**
     * HomaAPI - REST API Client
     */
    window.HomaAPI = {
        /**
         * Base URL for API endpoints
         */
        baseURL: window.homaConfig?.restUrl || '/wp-json/homaye-tabesh/v1',

        /**
         * POST request to API
         * @param {string} endpoint - API endpoint
         * @param {object} data - Request data
         * @returns {Promise} Response promise
         */
        post: async function(endpoint, data) {
            try {
                const response = await fetch(this.baseURL + endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.homaConfig?.nonce || ''
                    },
                    body: JSON.stringify(data)
                });

                return await response.json();
            } catch (error) {
                console.error('[HomaAPI] POST error:', error);
                throw error;
            }
        },

        /**
         * GET request to API
         * @param {string} endpoint - API endpoint
         * @param {object} params - Query parameters
         * @returns {Promise} Response promise
         */
        get: async function(endpoint, params = {}) {
            try {
                const url = new URL(this.baseURL + endpoint, window.location.origin);
                Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': window.homaConfig?.nonce || ''
                    }
                });

                return await response.json();
            } catch (error) {
                console.error('[HomaAPI] GET error:', error);
                throw error;
            }
        }
    };

    /**
     * HomaStore - Local & Remote Storage Manager
     */
    window.HomaStore = {
        /**
         * Local cache for performance
         */
        cache: {},

        /**
         * Debounce timer for auto-save
         */
        debounceTimer: null,

        /**
         * Debounce delay (ms) - configurable for different use cases
         */
        debounceDelay: 1000,

        /**
         * Known referral sources for tracking
         */
        referralSources: {
            TOROB: 'torob.com',
            GOOGLE: 'google.com',
            ORGANIC: 'organic'
        },

        /**
         * Update local cache and trigger sync
         * @param {object} data - Data to update
         */
        update: function(data) {
            // Update local cache
            if (data.field && data.value !== undefined) {
                this.cache[data.field] = data.value;
            } else {
                Object.assign(this.cache, data);
            }

            // Emit local update event
            window.Homa.emit('vault:local_update', this.cache);

            // Debounced sync to server
            this.debouncedSync(data);
        },

        /**
         * Debounced sync to server
         * @param {object} data - Data to sync
         */
        debouncedSync: function(data) {
            clearTimeout(this.debounceTimer);

            this.debounceTimer = setTimeout(() => {
                this.syncToServer(data);
            }, this.debounceDelay);
        },

        /**
         * Sync data to server
         * @param {object} data - Data to sync
         */
        syncToServer: async function(data) {
            try {
                const result = await window.HomaAPI.post('/vault/sync', {
                    field: data.field,
                    value: data.value,
                    page_url: window.location.href
                });

                if (result.success) {
                    window.Homa.emit('vault:synced', {
                        field: data.field,
                        session_token: result.session_token
                    });
                    console.log('[HomaStore] Data synced:', data.field);
                } else {
                    console.error('[HomaStore] Sync failed:', result.message);
                }
            } catch (error) {
                console.error('[HomaStore] Sync error:', error);
            }
        },

        /**
         * Get value from cache
         * @param {string} key - Key to retrieve
         * @returns {*} Cached value
         */
        get: function(key) {
            return this.cache[key];
        },

        /**
         * Get all cached data
         * @returns {object} All cached data
         */
        getAll: function() {
            return { ...this.cache };
        },

        /**
         * Load data from server
         * @param {string} sessionToken - Optional session token
         * @returns {Promise} Response promise
         */
        restore: async function(sessionToken = null) {
            try {
                const params = sessionToken ? { session_token: sessionToken } : {};
                const result = await window.HomaAPI.get('/vault/restore', params);

                if (result.success) {
                    // Update cache with restored data
                    if (result.vault_data) {
                        Object.keys(result.vault_data).forEach(key => {
                            this.cache[key] = result.vault_data[key].value;
                        });
                    }

                    // Update from session snapshot
                    if (result.session && result.session.form_snapshot) {
                        Object.assign(this.cache, result.session.form_snapshot);
                    }

                    window.Homa.emit('vault:restored', {
                        vault_data: result.vault_data,
                        session: result.session,
                        interests: result.interests
                    });

                    console.log('[HomaStore] Data restored successfully');
                    return result;
                }
            } catch (error) {
                console.error('[HomaStore] Restore error:', error);
            }

            return null;
        },

        /**
         * Clear all data
         */
        clear: async function() {
            try {
                const result = await window.HomaAPI.post('/vault/clear', {});

                if (result.success) {
                    this.cache = {};
                    window.Homa.emit('vault:cleared', {});
                    console.log('[HomaStore] Vault cleared');
                }
            } catch (error) {
                console.error('[HomaStore] Clear error:', error);
            }
        },

        /**
         * Save snapshot of current state
         * @param {string} chatSummary - Optional chat summary
         */
        saveSnapshot: async function(chatSummary = null) {
            try {
                const result = await window.HomaAPI.post('/session/snapshot', {
                    form_snapshot: this.cache,
                    chat_summary: chatSummary
                });

                if (result.success) {
                    window.Homa.emit('vault:snapshot_saved', {
                        timestamp: Date.now()
                    });
                    console.log('[HomaStore] Snapshot saved');
                }
            } catch (error) {
                console.error('[HomaStore] Snapshot error:', error);
            }
        },

        /**
         * Get memory summary for AI prompt
         * @returns {string} Memory summary
         */
        getMemorySummary: async function() {
            try {
                const result = await window.HomaAPI.get('/memory/summary');

                if (result.success) {
                    return result.memory_summary || '';
                }
            } catch (error) {
                console.error('[HomaStore] Memory summary error:', error);
            }

            return '';
        },

        /**
         * Track user interest in category
         * @param {string} category - Category slug
         * @param {number} score - Score increment
         * @param {string} source - Traffic source
         */
        trackInterest: async function(category, score = 1, source = 'organic') {
            try {
                await window.HomaAPI.post('/interest/track', {
                    category,
                    score,
                    source
                });

                console.log('[HomaStore] Interest tracked:', category);
            } catch (error) {
                console.error('[HomaStore] Track interest error:', error);
            }
        },

        /**
         * Get persona analysis
         * @returns {Promise} Persona data
         */
        getPersona: async function() {
            try {
                const result = await window.HomaAPI.get('/persona/analyze');

                if (result.success) {
                    return result.persona;
                }
            } catch (error) {
                console.error('[HomaStore] Persona error:', error);
            }

            return null;
        }
    };

    /**
     * Connect Vault to Event Bus
     * Monitor critical events and persist to database
     */
    function connectVaultToEventBus() {
        // Monitor form input changes
        window.Homa.on('site:input_change', (data) => {
            if (data.field && data.value !== undefined) {
                window.HomaStore.update({
                    field: data.field,
                    value: data.value
                });
            }
        });

        // Monitor price calculations (critical snapshot moment)
        window.Homa.on('calculator:price_calculated', (data) => {
            window.HomaStore.update({
                field: 'last_calculated_price',
                value: data.price
            });

            // Save snapshot at this critical moment
            window.HomaStore.saveSnapshot();
        });

        // Monitor chat messages for compression
        window.Homa.on('chat:message_sent', (data) => {
            // Store in local cache for later compression
            const messages = window.HomaStore.get('chat_messages') || [];
            messages.push({
                role: 'user',
                content: data.message,
                timestamp: Date.now()
            });
            window.HomaStore.update({
                field: 'chat_messages',
                value: messages
            });
        });

        // Monitor AI responses
        window.Homa.on('chat:message_received', (data) => {
            const messages = window.HomaStore.get('chat_messages') || [];
            messages.push({
                role: 'assistant',
                content: data.message,
                timestamp: Date.now()
            });
            window.HomaStore.update({
                field: 'chat_messages',
                value: messages
            });
        });

        // Auto-restore on page load
        window.addEventListener('DOMContentLoaded', async () => {
            const restored = await window.HomaStore.restore();
            
            if (restored && restored.session) {
                // Ask user if they want to continue from previous session
                const hasData = Object.keys(restored.session.form_snapshot || {}).length > 0;
                
                if (hasData) {
                    window.Homa.emit('vault:restore_prompt', {
                        session: restored.session,
                        message: 'مشخصات قبلی رو لود کنم؟'
                    });
                }
            }

            // Track page category interest
            const category = document.body.getAttribute('data-category');
            if (category) {
                const referrer = document.referrer;
                let source = window.HomaStore.referralSources.ORGANIC;
                
                // Check if from known referral sources
                if (referrer.includes(window.HomaStore.referralSources.TOROB)) {
                    source = 'torob';
                } else if (referrer.includes(window.HomaStore.referralSources.GOOGLE)) {
                    source = 'google';
                }
                
                window.HomaStore.trackInterest(category, 1, source);
            }
        });

        console.log('[Homa Vault] Connected to Event Bus');
    }

    // Initialize
    connectVaultToEventBus();

    // Expose vault for debugging
    window.Homa.vault = window.HomaStore;

})();
