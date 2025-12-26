import React from 'react';

/**
 * Security Warning Component
 * Displays security warning for detected intruders
 * 
 * @package HomayeTabesh
 * @since PR15
 */
const SecurityWarning = ({ detectionReason }) => {
    return (
        <div className="homa-security-warning">
            <div className="homa-warning-icon">
                <span className="homa-icon-shield">🛡️</span>
                <span className="homa-icon-warning">⚠️</span>
            </div>

            <div className="homa-warning-content">
                <h3 className="homa-warning-title">هشدار امنیتی</h3>
                
                <p className="homa-warning-message">
                    دسترسی شما به دلیل فعالیت‌های مشکوک محدود شده است.
                </p>

                {detectionReason && (
                    <div className="homa-warning-details">
                        <p className="homa-warning-reason">
                            <strong>دلیل:</strong> {detectionReason}
                        </p>
                    </div>
                )}

                <div className="homa-warning-actions">
                    <p className="homa-warning-info">
                        در صورتی که فکر می‌کنید این یک اشتباه است، لطفاً با مدیر سایت تماس بگیرید.
                    </p>
                    
                    <div className="homa-warning-buttons">
                        <a 
                            href="/contact" 
                            className="homa-warning-button primary"
                        >
                            تماس با پشتیبانی
                        </a>
                        
                        <button 
                            onClick={() => window.location.reload()}
                            className="homa-warning-button secondary"
                        >
                            تلاش مجدد
                        </button>
                    </div>
                </div>

                <div className="homa-warning-footer">
                    <p className="homa-warning-note">
                        این سیستم جهت حفاظت از امنیت وب‌سایت طراحی شده است.
                    </p>
                </div>
            </div>
        </div>
    );
};

export default SecurityWarning;
