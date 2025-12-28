/**
 * Homa Global Event Bus
 * Central communication hub for all Homa components
 * Implements Pub/Sub pattern for event-driven architecture
 * 
 * @package HomayeTabesh
 * @since PR 6.5
 */

(function() {
    'use strict';

    /**
     * Global Homa namespace
     * Acts as the central orchestrator for state and events
     */
    window.Homa = window.Homa || {};

    /**
     * Initialize global state
     */
    window.Homa.state = window.Homa.state || {
        isSidebarOpen: false,
        currentUserInput: {},
        pageMap: {},
        indexerReady: false,
        aiProcessing: false
    };

    /**
     * Event listeners registry
     * Maps event names to arrays of callback functions
     */
    const eventListeners = new Map();

    /**
     * Track registered listeners to prevent duplicates
     * Maps event names to Sets of callback functions
     */
    const registeredListeners = new Map();

    /**
     * WeakMap to store wrapped callbacks for proper cleanup
     * Avoids mutating callback function objects
     */
    const wrappedCallbacks = new WeakMap();

    /**
     * Event history for debugging
     */
    const eventHistory = [];
    const MAX_HISTORY = 100;

    /**
     * Emit an event to all registered listeners
     * 
     * @param {string} eventName - Name of the event (will be prefixed with 'homa:')
     * @param {*} data - Data to pass to listeners
     */
    window.Homa.emit = function(eventName, data) {
        const fullEventName = `homa:${eventName}`;
        
        // Log event for debugging
        const eventLog = {
            name: fullEventName,
            data: data,
            timestamp: Date.now()
        };
        
        eventHistory.push(eventLog);
        if (eventHistory.length > MAX_HISTORY) {
            eventHistory.shift();
        }

        // Dispatch native CustomEvent for cross-framework compatibility
        const event = new CustomEvent(fullEventName, { 
            detail: data,
            bubbles: false,
            cancelable: false
        });
        window.dispatchEvent(event);

        // Also call direct listeners registered via .on()
        const listeners = eventListeners.get(eventName);
        if (listeners && listeners.length > 0) {
            listeners.forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`[Homa Event Bus] Error in listener for ${eventName}:`, error);
                }
            });
        }

        console.log(`[Homa Event Bus] Emitted: ${fullEventName}`, data);
    };

    /**
     * Register a listener for an event
     * 
     * @param {string} eventName - Name of the event (without 'homa:' prefix)
     * @param {Function} callback - Function to call when event is emitted
     * @returns {Function} Cleanup function to remove the listener
     */
    window.Homa.on = function(eventName, callback) {
        if (typeof callback !== 'function') {
            console.error('[Homa Event Bus] Callback must be a function');
            return () => {};
        }

        // Check if this exact callback is already registered to prevent duplicates
        if (!registeredListeners.has(eventName)) {
            registeredListeners.set(eventName, new Set());
        }
        
        if (registeredListeners.get(eventName).has(callback)) {
            console.warn(`[Homa Event Bus] Listener already registered for: ${eventName}, returning existing cleanup`);
            // Return the existing cleanup function
            return () => {
                window.Homa.off(eventName, callback);
            };
        }
        
        // Mark as registered
        registeredListeners.get(eventName).add(callback);

        // Register in our listeners map
        if (!eventListeners.has(eventName)) {
            eventListeners.set(eventName, []);
        }
        eventListeners.get(eventName).push(callback);

        // Also register as native event listener for CustomEvent
        const fullEventName = `homa:${eventName}`;
        const wrappedCallback = (e) => callback(e.detail);
        
        // Store wrapped callback in WeakMap to avoid mutating function object
        wrappedCallbacks.set(callback, wrappedCallback);
        window.addEventListener(fullEventName, wrappedCallback);

        console.log(`[Homa Event Bus] Registered listener for: ${eventName}`);

        // Return cleanup function
        return () => {
            window.Homa.off(eventName, callback);
        };
    };

    /**
     * Remove a listener for an event
     * 
     * @param {string} eventName - Name of the event
     * @param {Function} callback - The callback function to remove
     */
    window.Homa.off = function(eventName, callback) {
        const listeners = eventListeners.get(eventName);
        if (listeners) {
            const index = listeners.indexOf(callback);
            if (index > -1) {
                listeners.splice(index, 1);
                console.log(`[Homa Event Bus] Removed listener for: ${eventName}`);
            }
        }
        
        // Remove from registered set
        if (registeredListeners.has(eventName)) {
            registeredListeners.get(eventName).delete(callback);
        }
        
        // Remove native event listener using wrapped callback from WeakMap
        const wrappedCallback = wrappedCallbacks.get(callback);
        if (wrappedCallback) {
            const fullEventName = `homa:${eventName}`;
            window.removeEventListener(fullEventName, wrappedCallback);
            wrappedCallbacks.delete(callback);
        }
    };

    /**
     * Remove all listeners for an event
     * 
     * @param {string} eventName - Name of the event
     */
    window.Homa.offAll = function(eventName) {
        eventListeners.delete(eventName);
        console.log(`[Homa Event Bus] Removed all listeners for: ${eventName}`);
    };

    /**
     * Update global state
     * Triggers state change events
     * 
     * @param {Object} updates - Object with state updates
     */
    window.Homa.updateState = function(updates) {
        const oldState = { ...window.Homa.state };
        window.Homa.state = { ...window.Homa.state, ...updates };
        
        // Emit state change event
        window.Homa.emit('state:changed', {
            old: oldState,
            new: window.Homa.state,
            changes: updates
        });

        console.log('[Homa Event Bus] State updated:', updates);
    };

    /**
     * Get current state
     * 
     * @returns {Object} Current state object
     */
    window.Homa.getState = function() {
        return { ...window.Homa.state };
    };

    /**
     * Check connectivity of all components
     * Used for testing and validation
     * 
     * @returns {Object} Health status of all connections
     */
    window.Homa.checkConnectivity = function() {
        const status = {
            timestamp: Date.now(),
            eventBus: true,
            indexer: !!window.HomaIndexer,
            orchestrator: !!window.HomaOrchestrator,
            uiExecutor: !!window.HomaUIExecutor,
            reactSidebar: !!document.getElementById('homa-sidebar-view'),
            listeners: {},
            state: window.Homa.state
        };

        // Check listener counts
        eventListeners.forEach((listeners, eventName) => {
            status.listeners[eventName] = listeners.length;
        });

        // Test emit/receive
        let testReceived = false;
        const cleanup = window.Homa.on('test:connectivity', () => {
            testReceived = true;
        });
        window.Homa.emit('test:connectivity', { test: true });
        cleanup();
        
        status.testPassed = testReceived;

        // Color code output
        const color = status.testPassed && status.indexer && status.orchestrator ? 'green' : 'orange';
        console.log(`%c[Homa Connectivity Check]`, `color: ${color}; font-weight: bold`, status);

        return status;
    };

    /**
     * Get event history for debugging
     * 
     * @param {number} limit - Maximum number of events to return
     * @returns {Array} Recent events
     */
    window.Homa.getEventHistory = function(limit = 50) {
        return eventHistory.slice(-limit);
    };

    /**
     * Clear event history
     */
    window.Homa.clearEventHistory = function() {
        eventHistory.length = 0;
        console.log('[Homa Event Bus] Event history cleared');
    };

    // Initialize event bus
    console.log('[Homa Event Bus] Initialized - Ready for pub/sub communication');

    // Expose debug interface
    if (!window.HomaDebug) {
        window.HomaDebug = {};
    }
    
    window.HomaDebug.EventBus = {
        getListeners: () => {
            const obj = {};
            eventListeners.forEach((listeners, key) => {
                obj[key] = listeners.length;
            });
            return obj;
        },
        getHistory: window.Homa.getEventHistory,
        checkConnectivity: window.Homa.checkConnectivity
    };

})();
