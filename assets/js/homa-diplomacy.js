/**
 * Homa Smart Diplomacy - Arabic Translation Popup
 * 
 * @package HomayeTabesh
 * @since PR14
 */

(function($) {
    'use strict';

    // Translation popup controller
    const HomaDiplomacy = {
        init: function() {
            this.checkTranslationPopup();
            this.bindEvents();
        },

        checkTranslationPopup: function() {
            // Check if we should show the popup
            if (typeof homaTranslationData === 'undefined') {
                return;
            }

            // Don't show if already decided
            if (this.getCookie('homa_translation_decided')) {
                return;
            }

            // Show popup if visitor is from Arabic country
            if (homaTranslationData.shouldShow) {
                this.showPopup();
            }
        },

        showPopup: function() {
            const countryName = homaTranslationData.countryNameArabic || 'your country';
            
            const popupHtml = `
                <div class="homa-translation-popup-overlay" id="homaTranslationPopup">
                    <div class="homa-translation-popup">
                        <div class="homa-popup-header">
                            <h3>مرحباً بك! 👋</h3>
                            <button class="homa-popup-close" id="homaPopupClose">&times;</button>
                        </div>
                        <div class="homa-popup-body">
                            <div class="homa-popup-icon">🌍</div>
                            <p class="homa-popup-text-ar">
                                نحن نرى أنك تزورنا من <strong>${countryName}</strong>
                            </p>
                            <p class="homa-popup-text-fa">
                                می‌بینیم که شما از <strong>${homaTranslationData.countryNamePersian || countryName}</strong> بازدید می‌کنید
                            </p>
                            <p class="homa-popup-question">
                                هل ترغب في ترجمة الموقع إلى العربية؟<br>
                                <small>آیا می‌خواهید سایت را به عربی ترجمه کنیم؟</small>
                            </p>
                        </div>
                        <div class="homa-popup-actions">
                            <button class="homa-btn homa-btn-primary" id="homaAcceptTranslation">
                                نعم، ترجمة للعربية / بله، ترجمه کن
                            </button>
                            <button class="homa-btn homa-btn-secondary" id="homaRejectTranslation">
                                لا، شكراً / نه، ممنون
                            </button>
                        </div>
                        <div class="homa-popup-footer">
                            <small>يمكنك تغيير اللغة في أي وقت / می‌توانید هر زمان زبان را تغییر دهید</small>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(popupHtml);
            
            // Show popup with animation
            setTimeout(() => {
                $('#homaTranslationPopup').addClass('show');
            }, 500);
        },

        bindEvents: function() {
            const self = this;

            // Accept translation
            $(document).on('click', '#homaAcceptTranslation', function(e) {
                e.preventDefault();
                self.acceptTranslation();
            });

            // Reject translation
            $(document).on('click', '#homaRejectTranslation', function(e) {
                e.preventDefault();
                self.rejectTranslation();
            });

            // Close popup
            $(document).on('click', '#homaPopupClose', function(e) {
                e.preventDefault();
                self.rejectTranslation();
            });

            // Close on overlay click
            $(document).on('click', '.homa-translation-popup-overlay', function(e) {
                if ($(e.target).hasClass('homa-translation-popup-overlay')) {
                    self.rejectTranslation();
                }
            });

            // Language switcher in header (if exists)
            $('.homa-language-switcher').on('click', function(e) {
                e.preventDefault();
                const lang = $(this).data('lang');
                if (lang === 'ar') {
                    self.acceptTranslation();
                } else {
                    self.disableTranslation();
                }
            });
        },

        acceptTranslation: function() {
            // Set cookies
            this.setCookie('homa_translate_to', 'ar', 30);
            this.setCookie('homa_translation_decided', '1', 30);

            // Show loading
            this.showLoading();

            // Reload page to apply translation
            window.location.reload();
        },

        rejectTranslation: function() {
            // Set cookie to remember decision
            this.setCookie('homa_translation_decided', '1', 30);

            // Hide popup
            this.hidePopup();
        },

        disableTranslation: function() {
            // Clear translation cookie
            this.deleteCookie('homa_translate_to');
            this.setCookie('homa_translation_decided', '1', 30);

            // Show loading
            this.showLoading();

            // Reload page
            window.location.reload();
        },

        showLoading: function() {
            const loadingHtml = `
                <div class="homa-translation-loading">
                    <div class="homa-spinner"></div>
                    <p>جاري الترجمة... / در حال ترجمه...</p>
                </div>
            `;
            
            $('.homa-translation-popup-overlay').html(loadingHtml);
        },

        hidePopup: function() {
            $('#homaTranslationPopup').removeClass('show');
            setTimeout(() => {
                $('#homaTranslationPopup').remove();
            }, 300);
        },

        setCookie: function(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            const secure = window.location.protocol === 'https:' ? ';Secure' : '';
            document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax' + secure;
        },

        deleteCookie: function(name) {
            const secure = window.location.protocol === 'https:' ? ';Secure' : '';
            document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;path=/;SameSite=Lax' + secure;
        },

        getCookie: function(name) {
            const value = '; ' + document.cookie;
            const parts = value.split('; ' + name + '=');
            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }
            return null;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        HomaDiplomacy.init();
    });

    // Make available globally
    window.HomaDiplomacy = HomaDiplomacy;

})(jQuery);
