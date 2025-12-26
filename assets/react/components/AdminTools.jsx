import React from 'react';

/**
 * Admin Tools Component
 * Displays admin-specific tools and shortcuts for Homa
 * 
 * @package HomayeTabesh
 * @since PR15
 */
const AdminTools = ({ userContext }) => {
    const handleShowStats = () => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('admin:show_stats', { period: 'today' });
        }
    };

    const handleShowUsers = () => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('admin:show_online_users', {});
        }
    };

    const handleSecurityAlerts = () => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('admin:show_security_alerts', {});
        }
    };

    return (
        <div className="homa-admin-tools">
            <div className="homa-tools-header">
                <h4>🎛️ ابزارهای مدیریتی</h4>
                <span className="homa-role-badge admin">مدیر</span>
            </div>
            
            <div className="homa-tools-grid">
                <button 
                    className="homa-tool-button analytics"
                    onClick={handleShowStats}
                    title="نمایش آمار و گزارش فروش امروز"
                >
                    <span className="homa-tool-icon">📊</span>
                    <span className="homa-tool-label">آمار امروز</span>
                </button>

                <button 
                    className="homa-tool-button users"
                    onClick={handleShowUsers}
                    title="لیست کاربران آنلاین و فعالیت‌ها"
                >
                    <span className="homa-tool-icon">👥</span>
                    <span className="homa-tool-label">کاربران آنلاین</span>
                </button>

                <button 
                    className="homa-tool-button security"
                    onClick={handleSecurityAlerts}
                    title="هشدارهای امنیتی و تشخیص مهاجم"
                >
                    <span className="homa-tool-icon">🛡️</span>
                    <span className="homa-tool-label">هشدارهای امنیتی</span>
                </button>

                <a 
                    href="/wp-admin/admin.php?page=homa-atlas"
                    className="homa-tool-button atlas"
                    title="باز کردن داشبورد اطلس"
                >
                    <span className="homa-tool-icon">⚡</span>
                    <span className="homa-tool-label">داشبورد اطلس</span>
                </a>
            </div>

            <div className="homa-admin-summary">
                <p className="homa-welcome-admin">
                    سلام {userContext?.identity} عزیز! 👋
                    <br />
                    من می‌توانم گزارش‌های لحظه‌ای، وضعیت سرور و رفتار کاربران را برای شما تحلیل کنم.
                </p>
            </div>
        </div>
    );
};

export default AdminTools;
