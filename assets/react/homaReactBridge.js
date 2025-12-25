/**
 * Homa React Bridge
 * Connects React components to the Homa Event Bus
 * Provides hooks and utilities for React integration
 * 
 * @package HomayeTabesh
 * @since PR 6.5
 */

import { useEffect, useCallback, useState } from 'react';

/**
 * Custom hook to subscribe to Homa events
 * 
 * @param {string} eventName - Name of the event (without 'homa:' prefix)
 * @param {Function} callback - Function to call when event is emitted
 */
export const useHomaEvent = (eventName, callback) => {
    useEffect(() => {
        if (!window.Homa || !window.Homa.on) {
            console.warn('[Homa React Bridge] Event bus not available');
            return;
        }

        // Register listener
        const cleanup = window.Homa.on(eventName, callback);

        // Cleanup on unmount
        return cleanup;
    }, [eventName, callback]);
};

/**
 * Custom hook to emit Homa events
 * 
 * @returns {Function} Emit function
 */
export const useHomaEmit = () => {
    return useCallback((eventName, data) => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit(eventName, data);
        } else {
            console.warn('[Homa React Bridge] Event bus not available');
        }
    }, []);
};

/**
 * Custom hook to access Homa global state
 * 
 * @returns {Object} State object and update function
 */
export const useHomaState = () => {
    const [state, setState] = useState(() => {
        return window.Homa?.state || {};
    });

    useEffect(() => {
        if (!window.Homa || !window.Homa.on) {
            return;
        }

        // Listen for state changes
        const cleanup = window.Homa.on('state:changed', (data) => {
            setState(data.new);
        });

        return cleanup;
    }, []);

    const updateState = useCallback((updates) => {
        if (window.Homa && window.Homa.updateState) {
            window.Homa.updateState(updates);
        }
    }, []);

    return { state, updateState };
};

/**
 * Custom hook to listen for site input changes
 * 
 * @param {Function} callback - Function to call when input changes
 */
export const useSiteInputChanges = (callback) => {
    useHomaEvent('site:input_change', callback);
};

/**
 * Custom hook to send commands to the AI
 * 
 * @returns {Function} Send command function
 */
export const useAICommand = () => {
    const emit = useHomaEmit();

    return useCallback((command) => {
        emit('ai:command', command);
    }, [emit]);
};

/**
 * Custom hook to listen for AI responses
 * 
 * @param {Function} callback - Function to call when AI responds
 */
export const useAIResponse = (callback) => {
    useHomaEvent('ai:response_received', callback);
};

/**
 * React component that bridges site changes to React state
 */
export const HomaSiteObserver = ({ onSiteChange, children }) => {
    useSiteInputChanges((data) => {
        if (onSiteChange) {
            onSiteChange(data);
        }
    });

    return children || null;
};

/**
 * Higher-order component to inject Homa event bus capabilities
 * 
 * @param {Component} Component - React component to wrap
 * @returns {Component} Enhanced component
 */
export const withHomaEvents = (Component) => {
    return (props) => {
        const emit = useHomaEmit();
        const { state, updateState } = useHomaState();
        const sendCommand = useAICommand();

        const homaProps = {
            homaEmit: emit,
            homaState: state,
            homaUpdateState: updateState,
            homaSendCommand: sendCommand
        };

        return <Component {...props} {...homaProps} />;
    };
};

/**
 * Utility function to emit from class components
 * 
 * @param {string} eventName - Event name
 * @param {*} data - Event data
 */
export const emitHomaEvent = (eventName, data) => {
    if (window.Homa && window.Homa.emit) {
        window.Homa.emit(eventName, data);
    }
};

/**
 * Utility function to get current Homa state
 * 
 * @returns {Object} Current state
 */
export const getHomaState = () => {
    return window.Homa?.getState() || {};
};

/**
 * Utility function to update Homa state
 * 
 * @param {Object} updates - State updates
 */
export const updateHomaState = (updates) => {
    if (window.Homa && window.Homa.updateState) {
        window.Homa.updateState(updates);
    }
};

export default {
    useHomaEvent,
    useHomaEmit,
    useHomaState,
    useSiteInputChanges,
    useAICommand,
    useAIResponse,
    HomaSiteObserver,
    withHomaEvents,
    emitHomaEvent,
    getHomaState,
    updateHomaState
};
