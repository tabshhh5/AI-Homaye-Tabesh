/**
 * Client-side Intervention Listener
 * Polls for admin interventions and displays them in chat
 * 
 * @package HomayeTabesh
 * @since PR10
 */

(function() {
    'use strict';

    /**
     * Intervention Listener Class
     */
    class HomaInterventionListener {
        constructor() {
            this.config = window.homayeParallelUIConfig || {};
            this.pollInterval = 5000; // 5 seconds
            this.isPolling = false;
            this.sessionId = this.getSessionId();
            this.init();
        }

        /**
         * Initialize listener
         */
        init() {
            console.log('[Homa Intervention Listener] Initializing...');
            
            // Wait for Event Bus to be ready
            if (window.Homa) {
                this.startPolling();
            } else {
                setTimeout(() => this.init(), 100);
            }
        }

        /**
         * Get session ID
         */
        getSessionId() {
            // Try to get from cookie
            const cookieMatch = document.cookie.match(/homa_session_id=([^;]+)/);
            if (cookieMatch) {
                return cookieMatch[1];
            }

            // Generate new session ID
            const sessionId = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            // Set cookie with secure flags
            const isSecure = window.location.protocol === 'https:';
            const cookieString = `homa_session_id=${sessionId}; path=/; max-age=86400; SameSite=Lax${isSecure ? '; Secure' : ''}`;
            document.cookie = cookieString;
            
            return sessionId;
        }

        /**
         * Start polling for interventions
         */
        startPolling() {
            if (this.isPolling) return;
            
            this.isPolling = true;
            console.log('[Homa Intervention Listener] Started polling');
            
            this.poll();
        }

        /**
         * Poll for interventions
         */
        async poll() {
            if (!this.isPolling) return;

            try {
                const response = await fetch(`/wp-json/homaye/v1/intervention/poll?session_id=${this.sessionId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    
                    if (data.success && data.interventions && data.interventions.length > 0) {
                        this.handleInterventions(data.interventions);
                    }
                }
            } catch (error) {
                console.error('[Homa Intervention Listener] Poll error:', error);
            }

            // Schedule next poll
            setTimeout(() => this.poll(), this.pollInterval);
        }

        /**
         * Handle received interventions
         */
        handleInterventions(interventions) {
            console.log('[Homa Intervention Listener] Received interventions:', interventions);

            interventions.forEach(intervention => {
                this.displayIntervention(intervention);
                
                // Execute visual commands if present
                if (intervention.visual_commands && intervention.visual_commands.length > 0) {
                    this.executeVisualCommands(intervention.visual_commands);
                }
                
                // Mark as delivered
                this.markDelivered(intervention.id);
            });
        }

        /**
         * Display intervention in chat
         */
        displayIntervention(intervention) {
            // Emit event to add message to chat
            if (window.Homa) {
                window.Homa.emit('ai:response_received', {
                    text: '👨‍💼 پیام ادمین: ' + intervention.message,
                    timestamp: intervention.created_at,
                    isIntervention: true
                });

                // Also emit a notification sound/visual if available
                window.Homa.emit('intervention:received', intervention);
            }

            // Show browser notification if permission granted
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('پیام از ادمین', {
                    body: intervention.message,
                    icon: this.config.pluginUrl + '/assets/images/homa-icon.png'
                });
            }

            console.log('[Homa Intervention Listener] Displayed intervention:', intervention.message);
        }

        /**
         * Execute visual commands
         */
        executeVisualCommands(commands) {
            if (!commands || commands.length === 0) return;

            console.log('[Homa Intervention Listener] Executing visual commands:', commands);

            commands.forEach(command => {
                if (window.Homa) {
                    window.Homa.emit('ai:command', command);
                }
            });
        }

        /**
         * Mark intervention as delivered
         */
        async markDelivered(interventionId) {
            try {
                await fetch(`/wp-json/homaye/v1/intervention/${interventionId}/delivered`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                console.log('[Homa Intervention Listener] Marked as delivered:', interventionId);
            } catch (error) {
                console.error('[Homa Intervention Listener] Mark delivered error:', error);
            }
        }

        /**
         * Stop polling
         */
        stopPolling() {
            this.isPolling = false;
            console.log('[Homa Intervention Listener] Stopped polling');
        }

        /**
         * Request notification permission
         */
        requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    console.log('[Homa Intervention Listener] Notification permission:', permission);
                });
            }
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.HomaInterventionListener = new HomaInterventionListener();
        });
    } else {
        window.HomaInterventionListener = new HomaInterventionListener();
    }

    // Request notification permission after user interaction
    document.addEventListener('click', function requestOnce() {
        if (window.HomaInterventionListener) {
            window.HomaInterventionListener.requestNotificationPermission();
        }
        document.removeEventListener('click', requestOnce);
    }, { once: true });

})();
