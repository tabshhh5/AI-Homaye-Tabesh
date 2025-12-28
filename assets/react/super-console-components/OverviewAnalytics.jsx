import React, { useState, useEffect, useCallback, useMemo } from 'react';

/**
 * Overview & Analytics Tab - Tab 1
 * تب ۱: داشبورد اجرایی
 * 
 * Displays token usage, sales data, conversion rates, and interest heatmap
 */
const OverviewAnalytics = ({ onRefresh }) => {
    const [analytics, setAnalytics] = useState(null);
    const [loading, setLoading] = useState(true);
    const [timeRange, setTimeRange] = useState('7days');

    const loadAnalytics = useCallback(async () => {
        setLoading(true);
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/analytics?range=${timeRange}`,
                {
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setAnalytics(data.data);
            }
        } catch (error) {
            console.error('Failed to load analytics:', error);
        } finally {
            setLoading(false);
        }
    }, [timeRange]);

    useEffect(() => {
        loadAnalytics();
    }, [loadAnalytics]);

    const stats = useMemo(() => {
        return analytics || {
            token_usage: {
                total: 0,
                by_section: { chat: 0, translation: 0, index: 0 }
            },
            leads: {
                total: 0,
                conversion_rate: 0
            },
            interests: []
        };
    }, [analytics]);

    if (loading) {
        return (
            <div className="loading-container">
                <div className="spinner"></div>
                <p>در حال بارگذاری آمار...</p>
            </div>
        );
    }

    return (
        <div className="overview-analytics" dir="rtl">
            {/* Time Range Selector */}
            <div className="controls-bar">
                <div className="time-range-selector">
                    <button 
                        className={timeRange === '24hours' ? 'active' : ''} 
                        onClick={() => setTimeRange('24hours')}
                    >
                        ۲۴ ساعت
                    </button>
                    <button 
                        className={timeRange === '7days' ? 'active' : ''} 
                        onClick={() => setTimeRange('7days')}
                    >
                        ۷ روز
                    </button>
                    <button 
                        className={timeRange === '30days' ? 'active' : ''} 
                        onClick={() => setTimeRange('30days')}
                    >
                        ۳۰ روز
                    </button>
                </div>
                <button className="refresh-btn" onClick={loadAnalytics}>
                    🔄 بروزرسانی
                </button>
            </div>

            {/* Key Metrics Grid */}
            <div className="metrics-grid">
                {/* Token Usage Card */}
                <div className="metric-card token-usage">
                    <div className="card-header">
                        <h3>📈 مصرف توکن</h3>
                        <span className="total">{stats.token_usage.total.toLocaleString('fa-IR')}</span>
                    </div>
                    <div className="card-body">
                        <div className="usage-breakdown">
                            <div className="usage-item">
                                <span className="label">💬 چت:</span>
                                <span className="value">{stats.token_usage.by_section.chat.toLocaleString('fa-IR')}</span>
                            </div>
                            <div className="usage-item">
                                <span className="label">🌐 ترجمه:</span>
                                <span className="value">{stats.token_usage.by_section.translation.toLocaleString('fa-IR')}</span>
                            </div>
                            <div className="usage-item">
                                <span className="label">🗂️ ایندکس:</span>
                                <span className="value">{stats.token_usage.by_section.index.toLocaleString('fa-IR')}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Leads & Conversion Card */}
                <div className="metric-card leads">
                    <div className="card-header">
                        <h3>👤 ثبت‌نام‌ها</h3>
                        <span className="total">{stats.leads.total.toLocaleString('fa-IR')}</span>
                    </div>
                    <div className="card-body">
                        <div className="conversion-rate">
                            <div className="rate-circle">
                                <svg viewBox="0 0 36 36" className="circular-chart">
                                    <path className="circle-bg"
                                        d="M18 2.0845
                                        a 15.9155 15.9155 0 0 1 0 31.831
                                        a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                    <path className="circle"
                                        strokeDasharray={`${stats.leads.conversion_rate}, 100`}
                                        d="M18 2.0845
                                        a 15.9155 15.9155 0 0 1 0 31.831
                                        a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                    <text x="18" y="20.35" className="percentage">{stats.leads.conversion_rate}%</text>
                                </svg>
                            </div>
                            <p className="rate-label">نرخ تبدیل (Conversion Rate)</p>
                        </div>
                    </div>
                </div>

                {/* Top Interests Card */}
                <div className="metric-card interests">
                    <div className="card-header">
                        <h3>🔥 محبوب‌ترین موضوعات</h3>
                    </div>
                    <div className="card-body">
                        {stats.interests && stats.interests.length > 0 ? (
                            <div className="interests-list">
                                {stats.interests.slice(0, 5).map((interest, index) => (
                                    <div key={index} className="interest-item">
                                        <span className="rank">#{index + 1}</span>
                                        <span className="topic">{interest.topic}</span>
                                        <span className="count">{interest.count}</span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="no-data">داده‌ای موجود نیست</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Heatmap Section */}
            <div className="heatmap-section">
                <h3>🗺️ هیتمپ علایق و جستجوها</h3>
                <div className="heatmap-container">
                    {stats.interests && stats.interests.length > 0 ? (
                        <div className="heatmap-grid">
                            {stats.interests.map((item, index) => (
                                <div 
                                    key={index} 
                                    className="heatmap-cell"
                                    style={{
                                        opacity: 0.3 + (item.count / Math.max(...stats.interests.map(i => i.count))) * 0.7,
                                        fontSize: `${12 + (item.count / Math.max(...stats.interests.map(i => i.count))) * 8}px`
                                    }}
                                >
                                    {item.topic}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="no-data">هنوز داده‌ای جمع‌آوری نشده است</p>
                    )}
                </div>
            </div>

        </div>
    );
};

export default OverviewAnalytics;
