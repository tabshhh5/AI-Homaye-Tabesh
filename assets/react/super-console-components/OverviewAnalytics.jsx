import React, { useState, useEffect } from 'react';

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

    useEffect(() => {
        loadAnalytics();
    }, [timeRange]);

    const loadAnalytics = async () => {
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
    };

    if (loading) {
        return (
            <div className="loading-container">
                <div className="spinner"></div>
                <p>در حال بارگذاری آمار...</p>
            </div>
        );
    }

    const stats = analytics || {
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

            <style jsx>{`
                .overview-analytics {
                    padding: 20px;
                }

                .controls-bar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                }

                .time-range-selector {
                    display: flex;
                    gap: 10px;
                }

                .time-range-selector button {
                    padding: 8px 16px;
                    border: 1px solid #ddd;
                    background: white;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.3s;
                }

                .time-range-selector button.active {
                    background: #667eea;
                    color: white;
                    border-color: #667eea;
                }

                .refresh-btn {
                    padding: 8px 16px;
                    background: #667eea;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                }

                .metrics-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }

                .metric-card {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                }

                .card-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 15px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #f0f0f0;
                }

                .card-header h3 {
                    margin: 0;
                    font-size: 16px;
                    color: #333;
                }

                .card-header .total {
                    font-size: 24px;
                    font-weight: bold;
                    color: #667eea;
                }

                .usage-breakdown {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .usage-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 12px;
                    background: #f9f9f9;
                    border-radius: 6px;
                }

                .usage-item .label {
                    color: #666;
                }

                .usage-item .value {
                    font-weight: bold;
                    color: #333;
                }

                .conversion-rate {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 10px;
                }

                .rate-circle {
                    width: 120px;
                    height: 120px;
                }

                .circular-chart {
                    display: block;
                    max-width: 100%;
                    max-height: 100%;
                }

                .circle-bg {
                    fill: none;
                    stroke: #f0f0f0;
                    stroke-width: 3.8;
                }

                .circle {
                    fill: none;
                    stroke: #667eea;
                    stroke-width: 2.8;
                    stroke-linecap: round;
                    animation: progress 1s ease-out forwards;
                }

                .percentage {
                    fill: #333;
                    font-family: sans-serif;
                    font-size: 0.5em;
                    text-anchor: middle;
                    font-weight: bold;
                }

                .rate-label {
                    color: #666;
                    font-size: 14px;
                    margin: 0;
                }

                .interests-list {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .interest-item {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 10px;
                    background: #f9f9f9;
                    border-radius: 6px;
                }

                .interest-item .rank {
                    font-weight: bold;
                    color: #667eea;
                    min-width: 30px;
                }

                .interest-item .topic {
                    flex: 1;
                    color: #333;
                }

                .interest-item .count {
                    background: #667eea;
                    color: white;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: bold;
                }

                .heatmap-section {
                    margin-top: 30px;
                    padding: 20px;
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                }

                .heatmap-section h3 {
                    margin: 0 0 20px 0;
                    color: #333;
                }

                .heatmap-grid {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 15px;
                    justify-content: center;
                    padding: 20px;
                }

                .heatmap-cell {
                    padding: 12px 20px;
                    background: #667eea;
                    color: white;
                    border-radius: 20px;
                    font-weight: 600;
                    transition: all 0.3s;
                }

                .heatmap-cell:hover {
                    transform: scale(1.1);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }

                .no-data {
                    text-align: center;
                    color: #999;
                    padding: 40px;
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

                @keyframes progress {
                    0% {
                        stroke-dasharray: 0 100;
                    }
                }
            `}</style>
        </div>
    );
};

export default OverviewAnalytics;
