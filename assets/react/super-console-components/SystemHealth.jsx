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
                            style={{ background: getStatusColor(diag.gapgpt_api?.status || diag.gemini_api?.status || 'unknown') }}
                        >
                            {getStatusIcon(diag.gapgpt_api?.status || diag.gemini_api?.status || 'unknown')}
                        </div>
                    </div>
                    <div className="card-body">
                        <div className="status-details">
                            <div className="detail-row">
                                <span className="label">وضعیت اتصال:</span>
                                <span className="value">{diag.gapgpt_api?.connection || diag.gemini_api?.connection || 'نامشخص'}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">زمان پاسخ:</span>
                                <span className="value">{diag.gapgpt_api?.response_time || diag.gemini_api?.response_time || 'N/A'}</span>
                            </div>
                            <div className="detail-row">
                                <span className="label">مدل فعال:</span>
                                <span className="value">{diag.gapgpt_api?.model || diag.gemini_api?.model || 'gemini-2.5-flash'}</span>
                            </div>
                        </div>
                        {(diag.gapgpt_api?.message || diag.gemini_api?.message) && (
                            <div className="status-message">{diag.gapgpt_api?.message || diag.gemini_api?.message}</div>
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

            <style jsx>{`
                .system-health {
                    padding: 20px;
                }

                .action-bar {
                    display: flex;
                    gap: 15px;
                    margin-bottom: 20px;
                }

                .refresh-btn, .fix-all-btn {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 6px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                }

                .refresh-btn {
                    background: #667eea;
                    color: white;
                }

                .fix-all-btn {
                    background: #2ecc71;
                    color: white;
                }

                .refresh-btn:disabled, .fix-all-btn:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                .fix-results {
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }

                .fix-results.success {
                    background: #d4edda;
                    border: 1px solid #c3e6cb;
                    color: #155724;
                }

                .fix-results.error {
                    background: #f8d7da;
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                }

                .fix-results h4 {
                    margin: 0 0 10px 0;
                }

                .fix-results ul {
                    margin: 0;
                    padding-right: 20px;
                }

                .components-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }

                .component-card {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                }

                .card-header {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 15px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #f0f0f0;
                }

                .component-icon {
                    font-size: 32px;
                }

                .card-header h3 {
                    flex: 1;
                    margin: 0;
                    font-size: 16px;
                    color: #333;
                }

                .status-badge {
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 16px;
                }

                .status-details {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .detail-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 12px;
                    background: #f9f9f9;
                    border-radius: 6px;
                }

                .detail-row .label {
                    color: #666;
                    font-size: 14px;
                }

                .detail-row .value {
                    font-weight: 600;
                    color: #333;
                    font-size: 14px;
                }

                .status-message {
                    margin-top: 10px;
                    padding: 10px;
                    background: #fff3cd;
                    border-radius: 6px;
                    font-size: 13px;
                    color: #856404;
                }

                .issues-section {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 20px;
                }

                .issues-section h3 {
                    margin: 0 0 15px 0;
                    color: #333;
                }

                .issues-list {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                }

                .issue-card {
                    padding: 15px;
                    border-radius: 8px;
                    border-right: 4px solid;
                }

                .issue-card.critical {
                    background: #f8d7da;
                    border-color: #e74c3c;
                }

                .issue-card.warning {
                    background: #fff3cd;
                    border-color: #f39c12;
                }

                .issue-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 8px;
                }

                .issue-title {
                    font-weight: 600;
                    color: #333;
                }

                .issue-description {
                    color: #666;
                    font-size: 14px;
                    margin-bottom: 8px;
                }

                .issue-fix {
                    font-size: 13px;
                    color: #2ecc71;
                    font-weight: 600;
                }

                .recommendations-section {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 20px;
                }

                .recommendations-section h3 {
                    margin: 0 0 15px 0;
                    color: #333;
                }

                .recommendations-list {
                    margin: 0;
                    padding-right: 20px;
                    color: #666;
                }

                .recommendations-list li {
                    margin: 8px 0;
                }

                .loading-container {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 60px;
                }

                .spinner {
                    width: 50px;
                    height: 50px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #667eea;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }

                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `}</style>
        </div>
    );
};

export default SystemHealth;
