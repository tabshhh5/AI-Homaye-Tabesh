import React from 'react';

/**
 * Lead Generator Component
 * Displays guest-focused tools and lead capture prompts
 * 
 * @package HomayeTabesh
 * @since PR15
 */
const LeadGenerator = ({ userContext }) => {
    const handleExploreServices = () => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('guest:show_services', {});
        }
    };

    const handleCalculateTirage = () => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('guest:calculate_tirage', {});
        }
    };

    const handleStartRegistration = () => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('lead:start_otp_registration', {});
        }
    };

    const handleBrowseProducts = () => {
        window.location.href = '/shop';
    };

    return (
        <div className="homa-lead-generator">
            <div className="homa-tools-header">
                <h4>🌟 خوش آمدید به چاپکو</h4>
                <span className="homa-role-badge guest">میهمان</span>
            </div>

            <div className="homa-welcome-guest">
                <div className="homa-greeting-icon">👋</div>
                <p>
                    سلام! به چاپکو خوش آمدید
                    <br />
                    من هما هستم، دستیار هوشمند شما. می‌توانم در انتخاب محصولات، محاسبه تیراژ و آشنایی با خدمات کمکتان کنم.
                </p>
            </div>

            <div className="homa-guest-actions">
                <button 
                    className="homa-guest-button primary"
                    onClick={handleExploreServices}
                >
                    <span className="homa-button-icon">🔍</span>
                    <span className="homa-button-text">معرفی خدمات چاپکو</span>
                </button>

                <button 
                    className="homa-guest-button secondary"
                    onClick={handleCalculateTirage}
                >
                    <span className="homa-button-icon">📚</span>
                    <span className="homa-button-text">محاسبه تیراژ کتاب</span>
                </button>

                <button 
                    className="homa-guest-button secondary"
                    onClick={handleBrowseProducts}
                >
                    <span className="homa-button-icon">🛍️</span>
                    <span className="homa-button-text">مشاهده محصولات</span>
                </button>
            </div>

            <div className="homa-registration-prompt">
                <div className="homa-prompt-content">
                    <p className="homa-prompt-title">💡 عضویت و دسترسی بیشتر</p>
                    <p className="homa-prompt-text">
                        با ثبت‌نام در چاپکو، می‌توانید از امکانات ویژه استفاده کنید:
                    </p>
                    <ul className="homa-benefits-list">
                        <li>پیگیری سفارشات</li>
                        <li>دریافت تخفیف‌های ویژه</li>
                        <li>ذخیره پروژه‌های شما</li>
                        <li>دسترسی به پشتیبانی اختصاصی</li>
                    </ul>
                    <button 
                        className="homa-register-button"
                        onClick={handleStartRegistration}
                    >
                        ثبت‌نام سریع (با کد یکبار مصرف)
                    </button>
                </div>
            </div>

            <div className="homa-guest-help">
                <p className="homa-help-text">
                    سوال دارید؟ می‌توانید در این چت سوال بپرسید یا از منوهای بالا استفاده کنید.
                </p>
            </div>
        </div>
    );
};

export default LeadGenerator;
