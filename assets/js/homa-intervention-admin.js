/**
 * Admin Intervention Interface
 * Admin-side UI for sending real-time messages to users
 * 
 * @package HomayeTabesh
 * @since PR10
 */

(function($) {
    'use strict';

    /**
     * Admin Intervention Manager
     */
    class HomaInterventionAdmin {
        constructor() {
            this.config = window.homaInterventionConfig || {};
            this.activeSessions = [];
            this.selectedSession = null;
            this.init();
        }

        /**
         * Initialize
         */
        init() {
            console.log('[Homa Intervention Admin] Initializing...');
            this.render();
            this.loadActiveSessions();
            this.startPolling();
        }

        /**
         * Render UI
         */
        render() {
            const container = $('#homa-intervention-app');
            
            container.html(`
                <div class="homa-intervention-container">
                    <div class="sessions-panel">
                        <h2>جلسات فعال</h2>
                        <div class="sessions-list" id="sessions-list">
                            <div class="loading">در حال بارگذاری...</div>
                        </div>
                    </div>
                    
                    <div class="intervention-panel">
                        <h2>ارسال پیام</h2>
                        <div class="intervention-form" id="intervention-form">
                            <p class="no-selection">لطفاً یک جلسه را انتخاب کنید</p>
                        </div>
                    </div>
                </div>
            `);
        }

        /**
         * Load active sessions
         */
        async loadActiveSessions() {
            try {
                const response = await fetch(`${this.config.restUrl}/sessions`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    this.activeSessions = data.sessions;
                    this.renderSessions();
                }
            } catch (error) {
                console.error('[Homa Intervention] Error loading sessions:', error);
                $('#sessions-list').html('<div class="error">خطا در بارگذاری جلسات</div>');
            }
        }

        /**
         * Render sessions list
         */
        renderSessions() {
            const listContainer = $('#sessions-list');
            
            if (this.activeSessions.length === 0) {
                listContainer.html('<div class="empty">هیچ جلسه فعالی وجود ندارد</div>');
                return;
            }

            let html = '';
            this.activeSessions.forEach(session => {
                const timeAgo = this.getTimeAgo(session.last_activity);
                const isSelected = this.selectedSession?.session_id === session.session_id;
                
                html += `
                    <div class="session-item ${isSelected ? 'selected' : ''}" data-session-id="${session.session_id}">
                        <div class="session-user">
                            <span class="user-icon">👤</span>
                            <div class="user-info">
                                <div class="user-name">${session.user_name}</div>
                                <div class="user-email">${session.user_email || 'مهمان'}</div>
                            </div>
                        </div>
                        <div class="session-time">
                            <span class="time-label">آخرین فعالیت:</span>
                            <span class="time-value">${timeAgo}</span>
                        </div>
                    </div>
                `;
            });
            
            listContainer.html(html);
            
            // Bind click handlers
            $('.session-item').on('click', (e) => {
                const sessionId = $(e.currentTarget).data('session-id');
                this.selectSession(sessionId);
            });
        }

        /**
         * Select a session
         */
        selectSession(sessionId) {
            this.selectedSession = this.activeSessions.find(s => s.session_id === sessionId);
            
            // Update UI
            $('.session-item').removeClass('selected');
            $(`.session-item[data-session-id="${sessionId}"]`).addClass('selected');
            
            // Render intervention form
            this.renderInterventionForm();
        }

        /**
         * Render intervention form
         */
        renderInterventionForm() {
            if (!this.selectedSession) {
                return;
            }

            const formContainer = $('#intervention-form');
            
            formContainer.html(`
                <div class="selected-user">
                    <strong>کاربر:</strong> ${this.selectedSession.user_name}
                    ${this.selectedSession.user_email ? `(${this.selectedSession.user_email})` : ''}
                </div>
                
                <div class="form-group">
                    <label for="intervention-message">پیام شما:</label>
                    <textarea 
                        id="intervention-message" 
                        rows="4" 
                        placeholder="پیام خود را اینجا بنویسید..."
                    ></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="add-visual-highlight"> 
                        افزودن هایلایت بصری
                    </label>
                    <input 
                        type="text" 
                        id="visual-selector" 
                        placeholder="CSS Selector (مثلاً .checkout-button)"
                        style="display: none; margin-top: 8px;"
                    >
                </div>
                
                <button id="send-intervention" class="button button-primary">
                    📤 ارسال پیام
                </button>
                
                <div id="intervention-status" class="intervention-status"></div>
            `);

            // Bind handlers
            $('#add-visual-highlight').on('change', (e) => {
                $('#visual-selector').toggle(e.target.checked);
            });

            $('#send-intervention').on('click', () => {
                this.sendIntervention();
            });
        }

        /**
         * Send intervention message
         */
        async sendIntervention() {
            const message = $('#intervention-message').val().trim();
            const addHighlight = $('#add-visual-highlight').is(':checked');
            const selector = $('#visual-selector').val().trim();
            
            if (!message) {
                this.showStatus('لطفاً پیامی بنویسید', 'error');
                return;
            }

            if (addHighlight && !selector) {
                this.showStatus('لطفاً یک selector CSS وارد کنید', 'error');
                return;
            }

            const visualCommands = [];
            if (addHighlight && selector) {
                visualCommands.push({
                    action_type: 'ui_interaction',
                    command: 'HIGHLIGHT',
                    target_selector: selector
                });
            }

            try {
                this.showStatus('در حال ارسال...', 'info');
                $('#send-intervention').prop('disabled', true);

                const response = await fetch(`${this.config.restUrl}/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.selectedSession.session_id,
                        message: message,
                        visual_commands: visualCommands
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    this.showStatus('✅ پیام با موفقیت ارسال شد', 'success');
                    $('#intervention-message').val('');
                    $('#add-visual-highlight').prop('checked', false);
                    $('#visual-selector').val('').hide();
                } else {
                    this.showStatus('❌ خطا در ارسال پیام', 'error');
                }
            } catch (error) {
                console.error('[Homa Intervention] Send error:', error);
                this.showStatus('❌ خطا در ارسال پیام', 'error');
            } finally {
                $('#send-intervention').prop('disabled', false);
            }
        }

        /**
         * Show status message
         */
        showStatus(message, type) {
            const statusDiv = $('#intervention-status');
            statusDiv
                .removeClass('success error info')
                .addClass(type)
                .text(message)
                .fadeIn();
            
            if (type === 'success' || type === 'error') {
                setTimeout(() => {
                    statusDiv.fadeOut();
                }, 5000);
            }
        }

        /**
         * Start polling for session updates
         */
        startPolling() {
            setInterval(() => {
                this.loadActiveSessions();
            }, this.config.pollInterval || 10000);
        }

        /**
         * Get time ago string
         */
        getTimeAgo(timestamp) {
            const now = new Date();
            const then = new Date(timestamp);
            const diffMs = now - then;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'اکنون';
            if (diffMins < 60) return `${diffMins} دقیقه پیش`;
            
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours} ساعت پیش`;
            
            const diffDays = Math.floor(diffHours / 24);
            return `${diffDays} روز پیش`;
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#homa-intervention-app').length) {
            window.HomaInterventionAdmin = new HomaInterventionAdmin();
        }
    });

})(jQuery);
