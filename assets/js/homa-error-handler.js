/**
 * Global Error Handler for Homa Frontend
 * Catches and reports JavaScript errors to prevent white screens
 * 
 * @package HomayeTabesh
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Global Error Handler Class
     * Implements fail-safe error catching and reporting
     */
    class HomaErrorHandler {
        constructor() {
            this.errors = [];
            this.maxErrors = 50; // Prevent memory overflow
            this.reportedErrors = new Set(); // Deduplicate errors
            this.init();
        }

        /**
         * Initialize error handlers
         */
        init() {
            // Catch unhandled errors
            window.addEventListener('error', (event) => {
                this.handleError({
                    message: event.message,
                    source: event.filename,
                    line: event.lineno,
                    column: event.colno,
                    error: event.error,
                    type: 'error'
                });
                
                // Don't prevent default to allow other handlers
                return false;
            });

            // Catch unhandled promise rejections
            window.addEventListener('unhandledrejection', (event) => {
                this.handleError({
                    message: 'Unhandled Promise Rejection: ' + (event.reason?.message || event.reason),
                    error: event.reason,
                    type: 'promise'
                });
            });

            // Add console override to capture console errors
            this.overrideConsole();

            console.log('[Homa Error Handler] Initialized - protecting against crashes');
        }

        /**
         * Override console.error to capture errors
         */
        overrideConsole() {
            const originalError = console.error;
            console.error = (...args) => {
                // Call original first
                originalError.apply(console, args);
                
                // Log to our handler
                this.handleError({
                    message: args.join(' '),
                    type: 'console',
                    severity: 'error'
                });
            };
        }

        /**
         * Handle an error
         * @param {Object} errorInfo Error information object
         */
        handleError(errorInfo) {
            try {
                // Create error signature for deduplication
                const signature = this.getErrorSignature(errorInfo);
                
                // Skip if already reported
                if (this.reportedErrors.has(signature)) {
                    return;
                }

                // Add to errors list
                if (this.errors.length < this.maxErrors) {
                    this.errors.push({
                        ...errorInfo,
                        timestamp: new Date().toISOString(),
                        url: window.location.href,
                        userAgent: navigator.userAgent
                    });
                }

                // Mark as reported
                this.reportedErrors.add(signature);

                // Log to console with styling
                this.logError(errorInfo);

                // Report to server if admin
                if (this.isAdmin()) {
                    this.reportToServer(errorInfo);
                }

                // Show user-friendly message for critical errors
                if (errorInfo.type === 'error' && !errorInfo.message.includes('Script error')) {
                    this.showUserMessage();
                }

            } catch (e) {
                // Fail silently - don't crash the error handler!
                console.log('[Homa Error Handler] Failed to handle error:', e);
            }
        }

        /**
         * Get unique signature for error deduplication
         * @param {Object} errorInfo Error information
         * @returns {string} Error signature
         */
        getErrorSignature(errorInfo) {
            return `${errorInfo.type}:${errorInfo.message}:${errorInfo.source}:${errorInfo.line}`;
        }

        /**
         * Log error to console with styling
         * @param {Object} errorInfo Error information
         */
        logError(errorInfo) {
            const style = 'color: #ff6b6b; font-weight: bold; padding: 4px 8px; background: #fee;';
            console.groupCollapsed('%c[Homa Error]', style, errorInfo.message);
            console.log('Type:', errorInfo.type);
            console.log('Source:', errorInfo.source);
            console.log('Line:', errorInfo.line);
            console.log('Column:', errorInfo.column);
            if (errorInfo.error) {
                console.log('Stack:', errorInfo.error.stack);
            }
            console.log('Time:', new Date().toLocaleString());
            console.groupEnd();
        }

        /**
         * Check if current user is admin
         * @returns {boolean} True if admin
         */
        isAdmin() {
            // Check if WordPress admin bar exists
            return document.body.classList.contains('admin-bar') || 
                   document.getElementById('wpadminbar') !== null;
        }

        /**
         * Report error to server for admin review
         * @param {Object} errorInfo Error information
         */
        reportToServer(errorInfo) {
            try {
                // Don't report in quick succession
                if (this.lastReportTime && (Date.now() - this.lastReportTime) < 5000) {
                    return;
                }

                this.lastReportTime = Date.now();

                // Send to WordPress REST API if available
                if (window.homaConfig?.restUrl) {
                    // Construct URL properly - ensure proper path separator
                    const restUrl = window.homaConfig.restUrl.replace(/\/$/, ''); // Remove trailing slash
                    const reportUrl = `${restUrl}/homaye/v1/error-report`;
                    
                    fetch(reportUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': window.homaConfig.nonce || ''
                        },
                        body: JSON.stringify({
                            error: errorInfo,
                            context: {
                                url: window.location.href,
                                userAgent: navigator.userAgent,
                                timestamp: new Date().toISOString()
                            }
                        })
                    }).catch(() => {
                        // Fail silently
                    });
                }
            } catch (e) {
                // Fail silently
            }
        }

        /**
         * Show user-friendly error message
         */
        showUserMessage() {
            // Only show once per session
            if (this.messageShown) {
                return;
            }
            this.messageShown = true;

            // Create and show a friendly message
            const message = document.createElement('div');
            message.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #fff3cd;
                color: #856404;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 999999;
                max-width: 400px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: 14px;
                line-height: 1.5;
            `;
            
            message.innerHTML = `
                <div style="display: flex; align-items: start; gap: 10px;">
                    <div style="font-size: 20px;">⚠️</div>
                    <div>
                        <strong>یک مشکل کوچک رخ داد</strong><br>
                        همای تابش در حال کار است، اما ممکن است برخی ویژگی‌ها محدود باشند.
                        <button style="
                            margin-top: 8px;
                            padding: 4px 12px;
                            background: #856404;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 12px;
                        " onclick="this.parentElement.parentElement.parentElement.remove()">
                            بستن
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(message);

            // Auto-dismiss after 10 seconds
            setTimeout(() => {
                if (message.parentElement) {
                    message.remove();
                }
            }, 10000);
        }

        /**
         * Get all captured errors
         * @returns {Array} List of errors
         */
        getErrors() {
            return this.errors;
        }

        /**
         * Clear all errors
         */
        clearErrors() {
            this.errors = [];
            this.reportedErrors.clear();
        }
    }

    // Create global instance
    window.HomaErrorHandler = new HomaErrorHandler();

    // Expose error reporting API
    window.Homa = window.Homa || {};
    window.Homa.reportError = function(error, context) {
        if (window.HomaErrorHandler) {
            window.HomaErrorHandler.handleError({
                message: error.message || String(error),
                error: error,
                type: 'manual',
                context: context
            });
        }
    };

    // Wrap common initialization functions to prevent crashes
    window.Homa.safeInit = function(fn, name) {
        try {
            return fn();
        } catch (error) {
            console.error(`[Homa] Failed to initialize ${name}:`, error);
            window.Homa.reportError(error, { component: name, phase: 'init' });
            return null;
        }
    };

    console.log('[Homa] Error handler loaded - frontend protected');

})();
