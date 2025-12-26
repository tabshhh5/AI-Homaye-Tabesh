import React, { useState, useEffect } from 'react';

/**
 * Executive Overview - Layer 1
 * نمای کلان: پایش ۳۰ ثانیه‌ای سلامت سایت
 */
const ExecutiveOverview = () => {
    const [healthData, setHealthData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetchHealthData();
        // Auto-refresh every 30 seconds
        const interval = setInterval(fetchHealthData, 30000);
        return () => clearInterval(interval);
    }, []);

    const fetchHealthData = async () => {
        try {
            const response = await fetch(
                `${window.atlasConfig.apiUrl}/health`,
                {
                    headers: {
                        'X-WP-Nonce': window.atlasConfig.nonce,
                    },
                }
            );
            const result = await response.json();
            if (result.success) {
                setHealthData(result.data);
                setError(null);
            } else {
                setError('خطا در دریافت داده‌ها');
            }
        } catch (err) {
            setError('خطا در ارتباط با سرور');
            console.error('Atlas Health Data Error:', err);
        } finally {
            setIsLoading(false);
        }
    };

    if (isLoading) {
        return <div className="atlas-loading">در حال بارگذاری...</div>;
    }

    if (error) {
        return <div className="atlas-error">{error}</div>;
    }

    const { health_score, health_status, metrics, insights } = healthData;

    // Determine health color
    const getHealthColor = (score) => {
        if (score >= 80) return '#10b981'; // green
        if (score >= 60) return '#3b82f6'; // blue
        if (score >= 40) return '#f59e0b'; // orange
        return '#ef4444'; // red
    };

    const healthColor = getHealthColor(health_score);

    return (
        <div className="executive-overview">
            <h2>📊 نمای کلان (Executive Overview)</h2>
            <p className="description">پایش ۳۰ ثانیه‌ای سلامت سایت</p>

            {/* Health Score Widget */}
            <div className="health-score-widget">
                <div className="score-circle" style={{ borderColor: healthColor }}>
                    <div className="score-value" style={{ color: healthColor }}>
                        {health_score}
                    </div>
                    <div className="score-label">امتیاز سلامت</div>
                </div>
                <div className="health-status">
                    <span className={`status-badge status-${health_status}`}>
                        {getHealthStatusText(health_status)}
                    </span>
                </div>
            </div>

            {/* Key Metrics Grid */}
            <div className="metrics-grid">
                <MetricCard
                    title="کل نشست‌ها"
                    value={metrics.total_sessions}
                    description="در ۳۰ روز گذشته"
                    icon="👥"
                />
                <MetricCard
                    title="نرخ تبدیل"
                    value={`${metrics.conversion_rate}%`}
                    description={`${metrics.total_conversions} تبدیل انجام شده`}
                    icon="✅"
                    highlight={metrics.conversion_rate < 2}
                />
                <MetricCard
                    title="کاربران فعال"
                    value={metrics.active_users_7d}
                    description="در ۷ روز گذشته"
                    icon="🔥"
                />
                <MetricCard
                    title="میانگین ارزش سبد"
                    value={`${metrics.avg_cart_value.toLocaleString('fa-IR')} تومان`}
                    description="میانگین ارزش خرید"
                    icon="💰"
                />
                <MetricCard
                    title="تبدیل‌های در حال انجام"
                    value={metrics.in_progress_conversions}
                    description="کاربران در حال خرید"
                    icon="⏳"
                />
                <MetricCard
                    title="کل رویدادها"
                    value={metrics.total_events}
                    description="رویدادهای ثبت شده"
                    icon="📈"
                />
            </div>

            {/* Insights & Alerts */}
            <div className="insights-section">
                <h3>🚨 هشدارها و بینش‌ها</h3>
                <div className="insights-list">
                    {insights.map((insight, index) => (
                        <InsightCard key={index} insight={insight} />
                    ))}
                </div>
            </div>

            {/* Atlas Map - Site Structure Visualization */}
            <div className="atlas-map-section">
                <h3>🗺️ نقشه اطلس (Atlas Map)</h3>
                <div className="atlas-map">
                    <p className="map-info">
                        نقشه هوشمند سایت شامل نمایش بصری مسیرهای کاربر، نقاط کور و گلوگاه‌ها
                    </p>
                    <div className="map-placeholder">
                        <p>📍 در نسخه‌های بعدی: نمایش تعاملی مسیرهای کاربر</p>
                    </div>
                </div>
            </div>
        </div>
    );
};

/**
 * Metric Card Component
 */
const MetricCard = ({ title, value, description, icon, highlight }) => {
    return (
        <div className={`metric-card ${highlight ? 'highlight' : ''}`}>
            <div className="metric-icon">{icon}</div>
            <div className="metric-content">
                <div className="metric-title">{title}</div>
                <div className="metric-value">{value}</div>
                <div className="metric-description">{description}</div>
            </div>
        </div>
    );
};

/**
 * Insight Card Component
 * قانون توضیح انسانی: هر عددی باید توضیح دارد
 */
const InsightCard = ({ insight }) => {
    const iconMap = {
        critical: '🔴',
        warning: '⚠️',
        info: 'ℹ️',
        success: '✅',
    };

    return (
        <div className={`insight-card insight-${insight.type}`}>
            <div className="insight-icon">{iconMap[insight.type]}</div>
            <div className="insight-content">
                <h4>{insight.title}</h4>
                <p className="insight-description">{insight.description}</p>
                {insight.action && (
                    <div className="insight-action">
                        <strong>اقدام پیشنهادی:</strong> {insight.action}
                    </div>
                )}
            </div>
        </div>
    );
};

/**
 * Get health status text in Persian
 */
const getHealthStatusText = (status) => {
    const statusMap = {
        excellent: '🟢 عالی',
        good: '🔵 خوب',
        warning: '🟠 هشدار',
        critical: '🔴 بحرانی',
    };
    return statusMap[status] || 'نامشخص';
};

export default ExecutiveOverview;
