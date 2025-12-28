import React, { useState, useEffect } from 'react';

/**
 * System Health & Diagnostics Tab - Tab 3
 * تب ۳: وضعیت سلامت و عیبیابی
 * 
 * Live monitoring with automatic diagnostics and Fix All feature
 */
const SystemHealth = () => {
    const [diagnostics, setDiagnostics] = useState(null);
    const [loading, setLoading] = useState(true);
    const [fixing, setFixing] = useState(false);
    const [fixResults, setFixResults] = useState(null);

    useEffect(() => {
        runDiagnostics();
        // Auto-refresh every 30 seconds
        const interval = setInterval(runDiagnostics, 30000);
        return () => clearInterval(interval);
    }, []);

    const runDiagnostics = async () => {
        setLoading(true);
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/diagnostics`,
                {
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setDiagnostics(data.data);
            }
        } catch (error) {
            console.error('Failed to run diagnostics:', error);
        } finally {
            setLoading(false);
        }
    };

    const runAutoFix = async () => {
        setFixing(true);
        setFixResults(null);
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/diagnostics/fix`,
                {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce,
                        'Content-Type': 'application/json'
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setFixResults(data.data);
                // Refresh diagnostics after fixing
                setTimeout(runDiagnostics, 2000);
            }
        } catch (error) {
            console.error('Failed to run auto-fix:', error);
        } finally {
            setFixing(false);
        }
    };

    const getStatusIcon = (status) => {
        switch (status) {
            case 'healthy': return '✅';
            case 'warning': return '⚠️';
            case 'error': return '❌';
            default: return '❓';
        }
    };

    const getStatusColor = (status) => {
        switch (status) {
            case 'healthy': return '#2ecc71';
            case 'warning': return '#f39c12';
            case 'error': return '#e74c3c';
            default: return '#95a5a6';
        }
    };

    if (loading && !diagnostics) {
        return (
            <div className="loading-container">
                <div className="spinner"></div>
                <p>در حال اجرای تست‌های سلامت...</p>
            </div>
        );
    }

    const diag = diagnostics || {
        gapgpt_api: { status: 'unknown' },
        tabesh_database: { status: 'unknown' },
        index_status: { status: 'unknown' },
        meli_payamak: { status: 'unknown' },
        security: { status: 'unknown' },
        issues: []
    };

    // Helper function to get API diagnostic value with fallback
    const getApiValue = (key, defaultValue = 'unknown') => {
        return diag.gapgpt_api?.[key] || diag.gemini_api?.[key] || defaultValue;
    };

    const hasIssues = diag.issues && diag.issues.length > 0;

    return (
        <div className="system-health" dir="rtl">
            {/* Action Bar */}
            <div className="action-bar">
                <button className="refresh-btn" onClick={runDiagnostics} disabled={loading}>
                    {loading ? '🔄 در حال بررسی...' : '🔄 بررسی مجدد'}
                </button>
                {hasIssues && (
                    <button 
                        className="fix-all-btn" 
                        onClick={runAutoFix} 
                        disabled={fixing}
                    >
                        {fixing ? '⚙️ در حال رفع مشکلات...' : '🔧 Fix All - رفع خودکار'}
                    </button>
                )}
            </div>

            {/* Fix Results Alert */}
            {fixResults && (
                <div className={`fix-results ${fixResults.success ? 'success' : 'error'}`}>
                    <h4>{fixResults.success ? '✅ رفع مشکلات با موفقیت انجام شد' : '⚠️ برخی مشکلات رفع نشدند'}</h4>
                    <ul>
                        {fixResults.fixed?.map((item, idx) => (
                            <li key={idx}>✓ {item}</li>
                        ))}
                        {fixResults.failed?.map((item, idx) => (
                            <li key={idx}>✗ {item}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* System Components Grid */}
            <div className="components-grid">
                {/* GapGPT API Status */}
                <div className="component-card">
                    <div className="card-header">
                        <div className="component-icon">🧠</div>
                        <h3>GapGPT API</h3>
                        <div 
                            className="status-badge"
                            style={{ background: getStatusColor(getApiValue('status')) }}
                        >
                            {getStatusIcon(getApiValue('status'))}
                        </div>
                    </div>
                    <div className="card-body">
                        <div className="status-details">
                            <div className="detail-row">
                                <span className="label">وضعیت اتصال:</span>
                                <span className="value">{getApiValue('connection', 'نامشخص')}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">زمان پاسخ:</span>
                                <span className="value">{getApiValue('response_time', 'N/A')}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">مدل فعال:</span>
                                <span className="value">{getApiValue('model', 'gemini-2.5-flash')}</span>
                            </div>
                        </div>
                        {getApiValue('message', null) && (
                            <div className="status-message">{getApiValue('message')}</div>
                        )}
                    </div>
                </div>

                {/* Tabesh Database Status */}
                <div className="component-card">
                    <div className="card-header">
                        <div className="component-icon">🗄️</div>
                        <h3>دیتابیس تابش</h3>
                        <div 
                            className="status-badge"
                            style={{ background: getStatusColor(diag.tabesh_database.status) }}
                        >
                            {getStatusIcon(diag.tabesh_database.status)}
                        </div>
                    </div>
                    <div className="card-body">
                        <div className="status-details">
                            <div className="detail-row">
                                <span className="label">اتصال:</span>
                                <span className="value">{diag.tabesh_database.connected ? 'متصل' : 'قطع'}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">تعداد فکت‌ها:</span>
                                <span className="value">{diag.tabesh_database.facts_count || 0}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">آخرین همگام‌سازی:</span>
                                <span className="value">{diag.tabesh_database.last_sync || 'نامشخص'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Index Status */}
                <div className="component-card">
                    <div className="card-header">
                        <div className="component-icon">📑</div>
                        <h3>وضعیت ایندکس</h3>
                        <div 
                            className="status-badge"
                            style={{ background: getStatusColor(diag.index_status.status) }}
                        >
                            {getStatusIcon(diag.index_status.status)}
                        </div>
                    </div>
                    <div className="card-body">
                        <div className="status-details">
                            <div className="detail-row">
                                <span className="label">صفحات ایندکس شده:</span>
                                <span className="value">{diag.index_status.pages_indexed || 0}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">افزونه‌های رصد شده:</span>
                                <span className="value">{diag.index_status.plugins_monitored || 0}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">امتیاز سلامت:</span>
                                <span className="value">{diag.index_status.health_score || 0}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Meli Payamak Status */}
                <div className="component-card">
                    <div className="card-header">
                        <div className="component-icon">📱</div>
                        <h3>ملی پیامک</h3>
                        <div 
                            className="status-badge"
                            style={{ background: getStatusColor(diag.meli_payamak.status) }}
                        >
                            {getStatusIcon(diag.meli_payamak.status)}
                        </div>
                    </div>
                    <div className="card-body">
                        <div className="status-details">
                            <div className="detail-row">
                                <span className="label">وضعیت API:</span>
                                <span className="value">{diag.meli_payamak.api_status || 'نامشخص'}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">اعتبار باقیمانده:</span>
                                <span className="value">{diag.meli_payamak.credit || 'نامشخص'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Security Status */}
                <div className="component-card">
                    <div className="card-header">
                        <div className="component-icon">🛡️</div>
                        <h3>امنیت سیستم</h3>
                        <div 
                            className="status-badge"
                            style={{ background: getStatusColor(diag.security.status) }}
                        >
                            {getStatusIcon(diag.security.status)}
                        </div>
                    </div>
                    <div className="card-body">
                        <div className="status-details">
                            <div className="detail-row">
                                <span className="label">تهدیدهای فعال:</span>
                                <span className="value">{diag.security.active_threats || 0}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">IP‌های مسدود:</span>
                                <span className="value">{diag.security.blocked_ips || 0}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">فایروال:</span>
                                <span className="value">{diag.security.waf_enabled ? 'فعال' : 'غیرفعال'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Issues List */}
            {hasIssues && (
                <div className="issues-section">
                    <h3>⚠️ مشکلات شناسایی شده</h3>
                    <div className="issues-list">
                        {diag.issues.map((issue, idx) => (
                            <div key={idx} className={`issue-card ${issue.severity}`}>
                                <div className="issue-header">
                                    <span className="issue-severity">
                                        {issue.severity === 'critical' ? '🔴' : 
                                         issue.severity === 'warning' ? '🟡' : '🟢'}
                                    </span>
                                    <span className="issue-title">{issue.title}</span>
                                </div>
                                <div className="issue-description">{issue.description}</div>
                                {issue.fix_available && (
                                    <div className="issue-fix">
                                        ✓ رفع خودکار در دسترس است
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Recommendations */}
            {diag.recommendations && diag.recommendations.length > 0 && (
                <div className="recommendations-section">
                    <h3>💡 پیشنهادات بهینه‌سازی</h3>
                    <ul className="recommendations-list">
                        {diag.recommendations.map((rec, idx) => (
                            <li key={idx}>{rec}</li>
                        ))}
                    </ul>
                </div>
            )}

        </div>
    );
};

export default SystemHealth;
