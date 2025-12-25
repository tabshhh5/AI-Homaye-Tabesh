/**
 * Homa Command Interpreter
 * Parses JSON commands from Gemini AI and converts them to executable actions
 * 
 * @package HomayeTabesh
 * @since PR 6.5
 */

(function() {
    'use strict';

    /**
     * Command Interpreter Class
     * Translates AI outputs into UI actions
     */
    class HomaCommandInterpreter {
        constructor() {
            this.commandQueue = [];
            this.processing = false;
            this.executionHistory = [];
            this.init();
        }

        /**
         * Initialize the interpreter
         */
        init() {
            console.log('[Homa Command Interpreter] Initializing...');

            // Listen for AI responses
            if (window.Homa) {
                window.Homa.on('ai:response_received', (response) => {
                    this.handleAIResponse(response);
                });

                window.Homa.on('ai:command', (command) => {
                    this.executeCommand(command);
                });
            } else {
                console.warn('[Homa Command Interpreter] Event bus not ready, waiting...');
                // Retry after a short delay
                setTimeout(() => this.init(), 100);
                return;
            }

            console.log('[Homa Command Interpreter] Ready');
        }

        /**
         * Handle AI response
         * 
         * @param {Object} response - AI response object
         */
        handleAIResponse(response) {
            console.log('[Homa Command Interpreter] Processing AI response:', response);

            if (!response) {
                console.warn('[Homa Command Interpreter] Empty response received');
                return;
            }

            // Parse different response formats
            let commands = [];

            // Format 1: Direct command object
            if (response.action_type || response.command) {
                commands = [response];
            }
            // Format 2: Array of commands
            else if (Array.isArray(response.commands)) {
                commands = response.commands;
            }
            // Format 3: Nested in data property
            else if (response.data && response.data.commands) {
                commands = response.data.commands;
            }
            // Format 4: Single command in data
            else if (response.data && response.data.command) {
                commands = [response.data];
            }

            // Execute all commands
            commands.forEach(cmd => this.queueCommand(cmd));
        }

        /**
         * Queue a command for execution
         * 
         * @param {Object} command - Command object
         */
        queueCommand(command) {
            if (!command) return;

            this.commandQueue.push(command);
            console.log('[Homa Command Interpreter] Command queued:', command);

            // Process queue if not already processing
            if (!this.processing) {
                this.processQueue();
            }
        }

        /**
         * Process command queue
         */
        async processQueue() {
            if (this.processing || this.commandQueue.length === 0) {
                return;
            }

            this.processing = true;

            while (this.commandQueue.length > 0) {
                const command = this.commandQueue.shift();
                try {
                    await this.executeCommand(command);
                    // Small delay between commands for visual clarity
                    await this.delay(200);
                } catch (error) {
                    console.error('[Homa Command Interpreter] Command execution failed:', error);
                }
            }

            this.processing = false;
        }

        /**
         * Execute a single command
         * 
         * @param {Object} command - Command object
         */
        async executeCommand(command) {
            console.log('[Homa Command Interpreter] Executing command:', command);

            // Store in history
            this.executionHistory.push({
                command: command,
                timestamp: Date.now()
            });

            // Keep history limited
            if (this.executionHistory.length > 50) {
                this.executionHistory.shift();
            }

            const actionType = command.action_type || command.type || command.action;
            const commandType = command.command || command.cmd;

            // Handle different action types
            if (actionType === 'ui_interaction' || actionType === 'ui_command') {
                this.executeUICommand(command, commandType);
            } else if (actionType === 'layout') {
                this.executeLayoutCommand(command, commandType);
            } else if (actionType === 'navigation') {
                this.executeNavigationCommand(command, commandType);
            } else if (actionType === 'data') {
                this.executeDataCommand(command, commandType);
            } else {
                // Try to infer action from command type
                this.executeInferredCommand(command);
            }

            // Emit command executed event
            if (window.Homa) {
                window.Homa.emit('command:executed', {
                    command: command,
                    timestamp: Date.now()
                });
            }
        }

        /**
         * Execute UI interaction command
         * 
         * @param {Object} command - Command object
         * @param {string} commandType - Type of command
         */
        executeUICommand(command, commandType) {
            const cmd = (commandType || '').toUpperCase();

            switch (cmd) {
                case 'HIGHLIGHT':
                    this.highlightElement(command);
                    break;

                case 'SQUEEZE_LAYOUT':
                case 'OPEN_SIDEBAR':
                    this.openSidebar();
                    break;

                case 'CLOSE_SIDEBAR':
                    this.closeSidebar();
                    break;

                case 'SCROLL_TO':
                    this.scrollToElement(command);
                    break;

                case 'SHOW_TOOLTIP':
                    this.showTooltip(command);
                    break;

                case 'CLICK':
                    this.clickElement(command);
                    break;

                case 'FILL_FIELD':
                    this.fillField(command);
                    break;

                default:
                    // Try to use HomaUIExecutor if available
                    if (window.HomaUIExecutor) {
                        window.HomaUIExecutor.executeAction(command);
                    } else {
                        console.warn('[Homa Command Interpreter] Unknown UI command:', cmd);
                    }
            }
        }

        /**
         * Execute layout command
         * 
         * @param {Object} command - Command object
         * @param {string} commandType - Type of command
         */
        executeLayoutCommand(command, commandType) {
            const squeeze = command.squeeze !== undefined ? command.squeeze : true;

            if (window.HomaOrchestrator) {
                if (squeeze) {
                    window.HomaOrchestrator.openSidebar();
                } else {
                    window.HomaOrchestrator.closeSidebar();
                }
            }
        }

        /**
         * Execute navigation command
         * 
         * @param {Object} command - Command object
         * @param {string} commandType - Type of command
         */
        executeNavigationCommand(command, commandType) {
            const url = command.url || command.target_url;
            if (url) {
                const delay = command.delay || 0;
                setTimeout(() => {
                    window.location.href = url;
                }, delay);
            }
        }

        /**
         * Execute data command
         * 
         * @param {Object} command - Command object
         * @param {string} commandType - Type of command
         */
        executeDataCommand(command, commandType) {
            if (window.Homa) {
                window.Homa.emit('data:update', command);
            }
        }

        /**
         * Execute inferred command (fallback)
         * 
         * @param {Object} command - Command object
         */
        executeInferredCommand(command) {
            // Try to use existing executor
            if (window.HomaUIExecutor) {
                window.HomaUIExecutor.executeAction(command);
            } else if (command.target || command.selector) {
                this.highlightElement(command);
            } else {
                console.warn('[Homa Command Interpreter] Could not infer command action:', command);
            }
        }

        /**
         * Highlight an element
         * 
         * @param {Object} command - Command with target selector
         */
        highlightElement(command) {
            const selector = command.target_selector || command.selector || command.target;
            if (!selector) return;

            // Use HomaUIExecutor if available
            if (window.HomaUIExecutor) {
                window.HomaUIExecutor.executeAction({
                    type: 'highlight_element',
                    target: selector
                });
            } else {
                // Fallback to direct DOM manipulation
                const element = document.querySelector(selector);
                if (element) {
                    element.classList.add('homa-pulse');
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => {
                        element.classList.remove('homa-pulse');
                    }, 3000);
                }
            }

            // Emit highlight event
            if (window.Homa) {
                window.Homa.emit('ui:highlight', { selector });
            }
        }

        /**
         * Open sidebar
         */
        openSidebar() {
            if (window.HomaOrchestrator) {
                window.HomaOrchestrator.openSidebar();
            }
            if (window.Homa) {
                window.Homa.updateState({ isSidebarOpen: true });
            }
        }

        /**
         * Close sidebar
         */
        closeSidebar() {
            if (window.HomaOrchestrator) {
                window.HomaOrchestrator.closeSidebar();
            }
            if (window.Homa) {
                window.Homa.updateState({ isSidebarOpen: false });
            }
        }

        /**
         * Scroll to element
         * 
         * @param {Object} command - Command with target selector
         */
        scrollToElement(command) {
            const selector = command.target_selector || command.selector || command.target;
            if (!selector) return;

            const element = document.querySelector(selector);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        /**
         * Show tooltip
         * 
         * @param {Object} command - Command with message
         */
        showTooltip(command) {
            if (window.HomaUIExecutor) {
                window.HomaUIExecutor.executeAction({
                    type: 'show_tooltip',
                    target: command.target_selector || command.target,
                    message: command.message || command.text
                });
            }
        }

        /**
         * Click element
         * 
         * @param {Object} command - Command with target selector
         */
        clickElement(command) {
            const selector = command.target_selector || command.selector || command.target;
            if (!selector) return;

            const element = document.querySelector(selector);
            if (element) {
                element.click();
            }
        }

        /**
         * Fill form field
         * 
         * @param {Object} command - Command with field and value
         */
        fillField(command) {
            const selector = command.target_selector || command.selector || command.target;
            const value = command.value;
            
            if (!selector || value === undefined) return;

            const element = document.querySelector(selector);
            if (element) {
                element.value = value;
                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        /**
         * Delay helper
         * 
         * @param {number} ms - Milliseconds to delay
         * @returns {Promise}
         */
        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        /**
         * Get execution history
         * 
         * @returns {Array} Command execution history
         */
        getHistory() {
            return this.executionHistory;
        }

        /**
         * Clear execution history
         */
        clearHistory() {
            this.executionHistory = [];
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.HomaCommandInterpreter = new HomaCommandInterpreter();
        });
    } else {
        window.HomaCommandInterpreter = new HomaCommandInterpreter();
    }

    console.log('[Homa Command Interpreter] Module loaded');

})();
