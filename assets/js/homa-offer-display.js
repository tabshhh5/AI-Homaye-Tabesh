/**
 * Homa Offer Display
 * Dynamic Offer UI Components - Countdown Timers, Discount Badges, CTA Buttons
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

(function () {
    'use strict';

    /**
     * Offer Display Manager
     * Manages visual offer elements in the sidebar and page
     */
    class HomaOfferDisplay {
        constructor() {
            this.config = window.homayePerceptionConfig || window.homayeConfig || {};
            this.activeOffers = new Map();
            this.offerHistory = [];
            this.styles = this.injectStyles();
            
            this.init();
        }

        /**
         * Initialize offer display system
         */
        init() {
            console.log('Homa Offer Display: Initializing dynamic offers...');

            // Listen for trigger events
            this.setupEventListeners();

            // Expose API
            this.exposeAPI();
        }

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Listen for conversion triggers
            document.addEventListener('homa:trigger', (e) => {
                const { trigger, data } = e.detail;
                this.handleTrigger(trigger, data);
            });

            // Listen for manual offer requests
            document.addEventListener('homa:show-offer', (e) => {
                const { offerType, offerData } = e.detail;
                this.showOffer(offerType, offerData);
            });

            // Listen for offer dismissal
            document.addEventListener('homa:dismiss-offer', (e) => {
                const { offerId } = e.detail;
                this.dismissOffer(offerId);
            });
        }

        /**
         * Handle conversion trigger and show appropriate offer
         */
        handleTrigger(trigger, data) {
            console.log('Homa Offer Display: Handling trigger:', trigger);

            switch (trigger) {
                case 'EXIT_INTENT':
                    this.showExitIntentOffer(data);
                    break;

                case 'SCROLL_DEPTH':
                    this.showScrollOffer(data);
                    break;

                case 'FIELD_HESITATION':
                    this.showHelpOffer(data);
                    break;

                case 'PRICE_HESITATION':
                    this.showDiscountOffer(data);
                    break;

                default:
                    console.log('Homa Offer Display: Unknown trigger type');
            }
        }

        /**
         * Show exit intent offer
         */
        showExitIntentOffer(data) {
            const offer = {
                id: 'exit_intent_' + Date.now(),
                type: 'EXIT_INTENT',
                title: '⚡ یک لحظه صبر کنید!',
                message: 'ما میتونیم کمکتون کنیم. یک تخفیف ویژه برای شما داریم!',
                discountPercent: 15,
                expiresIn: 600, // 10 minutes
                cta: 'دریافت تخفیف',
                ctaAction: () => this.applyDiscount(15, 'exit_intent')
            };

            this.showOffer('discount', offer);
        }

        /**
         * Show scroll depth offer
         */
        showScrollOffer(data) {
            if (data.depth >= 90) {
                const offer = {
                    id: 'scroll_90_' + Date.now(),
                    type: 'SCROLL_OFFER',
                    title: '🎉 شما تا اینجا اومدین!',
                    message: 'یک هدیه ویژه برای شما: ۱۰٪ تخفیف',
                    discountPercent: 10,
                    expiresIn: 300,
                    cta: 'استفاده از تخفیف',
                    ctaAction: () => this.applyDiscount(10, 'scroll_depth')
                };

                this.showOffer('discount', offer);
            }
        }

        /**
         * Show help offer
         */
        showHelpOffer(data) {
            const offer = {
                id: 'help_' + Date.now(),
                type: 'HELP_OFFER',
                title: '🤝 نیاز به کمک دارید؟',
                message: 'من اینجام تا بهتون کمک کنم. بگید چی براتون مشکله؟',
                cta: 'گفتگو با هما',
                ctaAction: () => this.openChat()
            };

            this.showOffer('help', offer);
        }

        /**
         * Show discount offer
         */
        showDiscountOffer(data) {
            const offer = {
                id: 'price_discount_' + Date.now(),
                type: 'PRICE_OFFER',
                title: '💰 قیمت مناسب نیست؟',
                message: 'یک پیشنهاد ویژه برای شما: ۲۰٪ تخفیف',
                discountPercent: 20,
                expiresIn: 900, // 15 minutes
                cta: 'اعمال تخفیف',
                ctaAction: () => this.applyDiscount(20, 'price_hesitation')
            };

            this.showOffer('discount', offer);
        }

        /**
         * Show offer with specific type
         */
        showOffer(offerType, offerData) {
            // Check if offer already shown
            if (this.activeOffers.has(offerData.id)) {
                console.log('Homa Offer Display: Offer already active');
                return;
            }

            // Create offer element
            let offerElement;

            switch (offerType) {
                case 'discount':
                    offerElement = this.createDiscountOffer(offerData);
                    break;

                case 'help':
                    offerElement = this.createHelpOffer(offerData);
                    break;

                case 'checkout':
                    offerElement = this.createCheckoutOffer(offerData);
                    break;

                default:
                    offerElement = this.createGenericOffer(offerData);
            }

            // Add to active offers
            this.activeOffers.set(offerData.id, {
                element: offerElement,
                data: offerData,
                shownAt: Date.now()
            });

            // Add to history
            this.offerHistory.push({
                id: offerData.id,
                type: offerType,
                shownAt: Date.now()
            });

            // Append to sidebar or page
            this.displayOffer(offerElement);

            // Auto-dismiss after some time (if not a critical offer)
            if (offerType !== 'checkout') {
                setTimeout(() => {
                    this.dismissOffer(offerData.id);
                }, 30000); // 30 seconds
            }
        }

        /**
         * Create discount offer element
         */
        createDiscountOffer(data) {
            const container = document.createElement('div');
            container.className = 'homa-offer homa-offer-discount';
            container.id = data.id;

            // Create countdown timer if expires
            let timerHTML = '';
            if (data.expiresIn) {
                timerHTML = `<div class="homa-offer-timer" data-expires="${Date.now() + (data.expiresIn * 1000)}"></div>`;
            }

            container.innerHTML = `
                <div class="homa-offer-badge">
                    <span class="homa-offer-percent">${data.discountPercent}%</span>
                    <span class="homa-offer-label">تخفیف</span>
                </div>
                <div class="homa-offer-content">
                    <h3 class="homa-offer-title">${data.title}</h3>
                    <p class="homa-offer-message">${data.message}</p>
                    ${timerHTML}
                </div>
                <div class="homa-offer-actions">
                    <button class="homa-offer-cta homa-offer-cta-primary">${data.cta}</button>
                    <button class="homa-offer-dismiss">بستن</button>
                </div>
            `;

            // Attach event listeners
            const ctaButton = container.querySelector('.homa-offer-cta-primary');
            ctaButton.addEventListener('click', () => {
                data.ctaAction();
                this.dismissOffer(data.id);
            });

            const dismissButton = container.querySelector('.homa-offer-dismiss');
            dismissButton.addEventListener('click', () => {
                this.dismissOffer(data.id);
            });

            // Start countdown if applicable
            if (data.expiresIn) {
                this.startCountdown(container.querySelector('.homa-offer-timer'));
            }

            return container;
        }

        /**
         * Create help offer element
         */
        createHelpOffer(data) {
            const container = document.createElement('div');
            container.className = 'homa-offer homa-offer-help';
            container.id = data.id;

            container.innerHTML = `
                <div class="homa-offer-content">
                    <h3 class="homa-offer-title">${data.title}</h3>
                    <p class="homa-offer-message">${data.message}</p>
                </div>
                <div class="homa-offer-actions">
                    <button class="homa-offer-cta homa-offer-cta-primary">${data.cta}</button>
                    <button class="homa-offer-dismiss">نه، ممنون</button>
                </div>
            `;

            // Attach event listeners
            const ctaButton = container.querySelector('.homa-offer-cta-primary');
            ctaButton.addEventListener('click', () => {
                data.ctaAction();
                this.dismissOffer(data.id);
            });

            const dismissButton = container.querySelector('.homa-offer-dismiss');
            dismissButton.addEventListener('click', () => {
                this.dismissOffer(data.id);
            });

            return container;
        }

        /**
         * Create checkout offer element
         */
        createCheckoutOffer(data) {
            const container = document.createElement('div');
            container.className = 'homa-offer homa-offer-checkout';
            container.id = data.id;

            container.innerHTML = `
                <div class="homa-offer-content">
                    <h3 class="homa-offer-title">${data.title || '🚀 آماده پرداخت؟'}</h3>
                    <p class="homa-offer-message">${data.message || 'همه چیز آماده است. با یک کلیک به صفحه پرداخت برید.'}</p>
                    <div class="homa-offer-summary">
                        <div class="homa-offer-summary-item">
                            <span>جمع سبد خرید:</span>
                            <strong>${data.cartTotal || '---'}</strong>
                        </div>
                    </div>
                </div>
                <div class="homa-offer-actions">
                    <button class="homa-offer-cta homa-offer-cta-checkout">پرداخت با هما</button>
                </div>
            `;

            // Attach event listeners
            const ctaButton = container.querySelector('.homa-offer-cta-checkout');
            ctaButton.addEventListener('click', () => {
                this.goToCheckout();
            });

            return container;
        }

        /**
         * Create generic offer element
         */
        createGenericOffer(data) {
            const container = document.createElement('div');
            container.className = 'homa-offer homa-offer-generic';
            container.id = data.id;

            container.innerHTML = `
                <div class="homa-offer-content">
                    <h3 class="homa-offer-title">${data.title}</h3>
                    <p class="homa-offer-message">${data.message}</p>
                </div>
                <div class="homa-offer-actions">
                    ${data.cta ? `<button class="homa-offer-cta homa-offer-cta-primary">${data.cta}</button>` : ''}
                    <button class="homa-offer-dismiss">بستن</button>
                </div>
            `;

            if (data.cta && data.ctaAction) {
                const ctaButton = container.querySelector('.homa-offer-cta-primary');
                ctaButton.addEventListener('click', () => {
                    data.ctaAction();
                    this.dismissOffer(data.id);
                });
            }

            const dismissButton = container.querySelector('.homa-offer-dismiss');
            dismissButton.addEventListener('click', () => {
                this.dismissOffer(data.id);
            });

            return container;
        }

        /**
         * Display offer on the page
         */
        displayOffer(offerElement) {
            // Try to find sidebar container first
            let container = document.getElementById('homa-offers-container');

            // If no container, create one
            if (!container) {
                container = document.createElement('div');
                container.id = 'homa-offers-container';
                container.className = 'homa-offers-container';
                document.body.appendChild(container);
            }

            // Add with animation
            offerElement.style.opacity = '0';
            offerElement.style.transform = 'translateY(20px)';
            container.appendChild(offerElement);

            // Animate in
            setTimeout(() => {
                offerElement.style.transition = 'all 0.3s ease';
                offerElement.style.opacity = '1';
                offerElement.style.transform = 'translateY(0)';
            }, 10);
        }

        /**
         * Dismiss offer
         */
        dismissOffer(offerId) {
            const offer = this.activeOffers.get(offerId);
            if (!offer) return;

            // Animate out
            offer.element.style.opacity = '0';
            offer.element.style.transform = 'translateY(20px)';

            setTimeout(() => {
                offer.element.remove();
                this.activeOffers.delete(offerId);
            }, 300);
        }

        /**
         * Start countdown timer
         */
        startCountdown(timerElement) {
            if (!timerElement) return;

            const expiresAt = parseInt(timerElement.dataset.expires);

            const updateTimer = () => {
                const now = Date.now();
                const remaining = Math.max(0, expiresAt - now);

                if (remaining === 0) {
                    timerElement.textContent = 'منقضی شد';
                    return;
                }

                const minutes = Math.floor(remaining / 60000);
                const seconds = Math.floor((remaining % 60000) / 1000);

                timerElement.textContent = `⏱️ ${minutes}:${seconds.toString().padStart(2, '0')} دقیقه باقی مانده`;

                if (remaining > 0) {
                    setTimeout(updateTimer, 1000);
                }
            };

            updateTimer();
        }

        /**
         * Apply discount
         */
        applyDiscount(percent, reason) {
            console.log(`Homa Offer Display: Applying ${percent}% discount (reason: ${reason})`);

            const nonce = this.config.nonce || '';

            fetch(`${this.config.apiUrl || '/wp-json/homaye/v1'}/cart/apply-discount`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    discount_type: 'percentage',
                    discount_value: percent,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Homa Offer Display: Discount applied successfully');
                    this.showSuccessMessage(`تخفیف ${percent}% با موفقیت اعمال شد!`);
                    
                    // Show checkout offer
                    setTimeout(() => {
                        this.showCheckoutOffer(data.cart_total);
                    }, 2000);
                } else {
                    console.error('Homa Offer Display: Failed to apply discount');
                    this.showErrorMessage('خطا در اعمال تخفیف');
                }
            })
            .catch(error => {
                console.error('Homa Offer Display: Error applying discount:', error);
            });
        }

        /**
         * Show checkout offer
         */
        showCheckoutOffer(cartTotal) {
            const offer = {
                id: 'checkout_' + Date.now(),
                type: 'CHECKOUT',
                title: '✅ تخفیف اعمال شد!',
                message: 'حالا میتونید خرید رو نهایی کنید',
                cartTotal: cartTotal,
                cta: 'پرداخت',
                ctaAction: () => this.goToCheckout()
            };

            this.showOffer('checkout', offer);
        }

        /**
         * Open chat
         */
        openChat() {
            document.dispatchEvent(new CustomEvent('homa:open-chat'));
        }

        /**
         * Go to checkout
         */
        goToCheckout() {
            // Get checkout URL from WooCommerce
            fetch(`${this.config.apiUrl || '/wp-json/homaye/v1'}/cart/status`, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.checkout_url) {
                    window.location.href = data.checkout_url;
                } else {
                    // Fallback
                    window.location.href = '/checkout';
                }
            })
            .catch(() => {
                window.location.href = '/checkout';
            });
        }

        /**
         * Show success message
         */
        showSuccessMessage(message) {
            this.showToast(message, 'success');
        }

        /**
         * Show error message
         */
        showErrorMessage(message) {
            this.showToast(message, 'error');
        }

        /**
         * Show toast notification
         */
        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `homa-toast homa-toast-${type}`;
            toast.textContent = message;

            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            }, 10);

            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        /**
         * Inject CSS styles
         */
        injectStyles() {
            const styleId = 'homa-offer-styles';
            if (document.getElementById(styleId)) return;

            const style = document.createElement('style');
            style.id = styleId;
            style.textContent = `
                .homa-offers-container {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 999999;
                    max-width: 400px;
                    direction: rtl;
                }

                .homa-offer {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                    padding: 20px;
                    margin-bottom: 15px;
                    transition: all 0.3s ease;
                }

                .homa-offer-discount {
                    border-top: 4px solid #ff6b6b;
                }

                .homa-offer-help {
                    border-top: 4px solid #4ecdc4;
                }

                .homa-offer-checkout {
                    border-top: 4px solid #51cf66;
                }

                .homa-offer-badge {
                    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
                    color: white;
                    border-radius: 8px;
                    padding: 10px;
                    text-align: center;
                    margin-bottom: 15px;
                }

                .homa-offer-percent {
                    display: block;
                    font-size: 32px;
                    font-weight: bold;
                }

                .homa-offer-label {
                    display: block;
                    font-size: 14px;
                }

                .homa-offer-title {
                    margin: 0 0 10px 0;
                    font-size: 18px;
                    font-weight: bold;
                    color: #333;
                }

                .homa-offer-message {
                    margin: 0 0 15px 0;
                    color: #666;
                    line-height: 1.6;
                }

                .homa-offer-timer {
                    background: #fff3cd;
                    color: #856404;
                    padding: 8px 12px;
                    border-radius: 6px;
                    font-size: 14px;
                    text-align: center;
                    margin-bottom: 15px;
                }

                .homa-offer-actions {
                    display: flex;
                    gap: 10px;
                }

                .homa-offer-cta {
                    flex: 1;
                    padding: 12px 20px;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: bold;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .homa-offer-cta-primary {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                }

                .homa-offer-cta-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }

                .homa-offer-cta-checkout {
                    background: linear-gradient(135deg, #51cf66, #37b24d);
                    color: white;
                }

                .homa-offer-cta-checkout:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(81, 207, 102, 0.4);
                }

                .homa-offer-dismiss {
                    background: #f1f3f5;
                    color: #868e96;
                    padding: 12px 20px;
                    border: none;
                    border-radius: 8px;
                    font-size: 14px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .homa-offer-dismiss:hover {
                    background: #e9ecef;
                }

                .homa-toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    z-index: 1000000;
                    opacity: 0;
                    transform: translateY(-20px);
                    transition: all 0.3s ease;
                }

                .homa-toast-success {
                    border-left: 4px solid #51cf66;
                }

                .homa-toast-error {
                    border-left: 4px solid #ff6b6b;
                }

                .homa-offer-summary {
                    background: #f8f9fa;
                    padding: 12px;
                    border-radius: 8px;
                    margin: 15px 0;
                }

                .homa-offer-summary-item {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                }

                .homa-offer-summary-item:last-child {
                    margin-bottom: 0;
                }
            `;

            document.head.appendChild(style);
        }

        /**
         * Expose public API
         */
        exposeAPI() {
            const api = {
                showOffer: this.showOffer.bind(this),
                dismissOffer: this.dismissOffer.bind(this),
                applyDiscount: this.applyDiscount.bind(this),
                goToCheckout: this.goToCheckout.bind(this),
                getActiveOffers: () => Array.from(this.activeOffers.keys()),
                getOfferHistory: () => this.offerHistory
            };

            // Attach to Homa namespace
            if (window.Homa) {
                window.Homa.OfferDisplay = api;
            } else {
                window.Homa = { OfferDisplay: api };
            }

            console.log('Homa Offer Display: API exposed');
        }
    }

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new HomaOfferDisplay();
        });
    } else {
        new HomaOfferDisplay();
    }

    console.log('Homa Offer Display: Module loaded successfully');
})();
