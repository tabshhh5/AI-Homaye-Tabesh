import React, { useState, useEffect } from 'react';

/**
 * Behavior Analyzer - Layer 2
 * تحلیل رفتار: شناسایی گلوگاه‌ها و نقاط تردید کاربر
 */
const BehaviorAnalyzer = () => {
    const [flowData, setFlowData] = useState(null);
    const [bottlenecks, setBottlenecks] = useState(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        fetchAnalysisData();
    }, []);

    const fetchAnalysisData = async () => {
        try {
            // Fetch flow analysis
            const flowResponse = await fetch(
                `${window.atlasConfig.apiUrl}/flow-analysis`,
                {
                    headers: {
                        'X-WP-Nonce': window.atlasConfig.nonce,
                    },
                }
            );
            const flowResult = await flowResponse.json();

            // Fetch bottlenecks
            const bottleneckResponse = await fetch(
                `${window.atlasConfig.apiUrl}/bottlenecks`,
                {
                    headers: {
                        'X-WP-Nonce': window.atlasConfig.nonce,
                    },
                }
            );
            const bottleneckResult = await bottleneckResponse.json();

            if (flowResult.success) {
                setFlowData(flowResult.data);
            }
            if (bottleneckResult.success) {
                setBottlenecks(bottleneckResult.data);
            }
        } catch (err) {
            console.error('Atlas Behavior Analysis Error:', err);
        } finally {
            setIsLoading(false);
        }
    };

    if (isLoading) {
        return <div className="atlas-loading">در حال تحلیل رفتار کاربران...</div>;
    }

    return (
        <div className="behavior-analyzer">
            <h2>🔍 تحلیل رفتار (User Flow Intelligence)</h2>
            <p className="description">
                شناسایی مسیرهای ناقص و رفتارهای تکرارشونده که منجر به خرید نمی‌شوند
            </p>

            {/* Bottlenecks Detection */}
            <div className="bottlenecks-section">
                <h3>🚧 گلوگاه‌های شناسایی شده</h3>
                {bottlenecks?.total_detected > 0 ? (
                    <div className="bottlenecks-list">
                        {bottlenecks.bottlenecks.map((bottleneck, index) => (
                            <BottleneckCard key={index} bottleneck={bottleneck} />
                        ))}
                    </div>
                ) : (
                    <div className="no-data">
                        <p>✅ گلوگاه قابل توجهی شناسایی نشد. عملکرد خوبی دارید!</p>
                    </div>
                )}
            </div>

            {/* Event Distribution Analysis */}
            <div className="flow-distribution-section">
                <h3>📊 توزیع رویدادهای کاربر</h3>
                {flowData?.event_distribution && flowData.event_distribution.length > 0 ? (
                    <div className="event-distribution">
                        {flowData.event_distribution.map((event, index) => (
                            <EventBar key={index} event={event} />
                        ))}
                    </div>
                ) : (
                    <div className="no-data">
                        <p>هنوز داده کافی برای تحلیل رویدادها جمع‌آوری نشده است.</p>
                    </div>
                )}
            </div>

            {/* Indecision Points */}
            <div className="indecision-section">
                <h3>🤔 نقاط تردید کاربر</h3>
                <p className="section-description">
                    مکان‌هایی که کاربران در آن دچار تردید می‌شوند و معمولاً سایت را ترک می‌کنند
                </p>
                {bottlenecks?.bottlenecks?.filter(b => b.severity === 'high').length > 0 ? (
                    <div className="indecision-list">
                        {bottlenecks.bottlenecks
                            .filter(b => b.severity === 'high')
                            .map((point, index) => (
                                <IndecisionPoint key={index} point={point} />
                            ))}
                    </div>
                ) : (
                    <div className="no-data">
                        <p>✅ نقطه تردید بحرانی شناسایی نشد.</p>
                    </div>
                )}
            </div>

            {/* Algorithm Info */}
            <div className="algorithm-info">
                <h4>⚙️ الگوریتم تشخیص گلوگاه</h4>
                <pre className="algorithm-code">
{`// الگوریتم شناسایی نقاط کور در مسیر کاربر
const detectBottlenecks = (userPath) => {
    const dropOffPoints = userPath.filter(
        step => step.exitRate > 0.6
    );
    return dropOffPoints.map(point => ({
        location: point.pageName,
        insight: 'کاربر در این مرحله دچار تردید می‌شود'
    }));
};`}
                </pre>
            </div>
        </div>
    );
};

/**
 * Bottleneck Card Component
 */
const BottleneckCard = ({ bottleneck }) => {
    const getSeverityColor = (severity) => {
        const colors = {
            high: '#ef4444',
            medium: '#f59e0b',
            low: '#3b82f6',
        };
        return colors[severity] || '#6b7280';
    };

    return (
        <div className="bottleneck-card" style={{ borderLeftColor: getSeverityColor(bottleneck.severity) }}>
            <div className="bottleneck-header">
                <span className={`severity-badge severity-${bottleneck.severity}`}>
                    {bottleneck.severity === 'high' && '🔴 بحرانی'}
                    {bottleneck.severity === 'medium' && '🟠 متوسط'}
                    {bottleneck.severity === 'low' && '🟡 کم'}
                </span>
                <span className="exit-rate">{bottleneck.exit_rate}% نرخ خروج</span>
            </div>
            <h4>{bottleneck.location}</h4>
            <p className="bottleneck-insight">{bottleneck.insight}</p>
            <div className="bottleneck-stats">
                <span>میانگین تکمیل: {bottleneck.avg_completion}%</span>
            </div>
        </div>
    );
};

/**
 * Event Bar Component
 */
const EventBar = ({ event }) => {
    // Calculate max for percentage
    const maxCount = 1000; // This should be dynamically calculated
    const percentage = Math.min((event.count / maxCount) * 100, 100);

    return (
        <div className="event-bar">
            <div className="event-label">
                <span className="event-type">{event.event_type}</span>
                <span className="event-count">{event.count} رویداد</span>
            </div>
            <div className="event-progress">
                <div
                    className="event-progress-fill"
                    style={{ width: `${percentage}%` }}
                ></div>
            </div>
        </div>
    );
};

/**
 * Indecision Point Component
 */
const IndecisionPoint = ({ point }) => {
    return (
        <div className="indecision-point">
            <div className="indecision-icon">🤔</div>
            <div className="indecision-content">
                <h4>{point.location}</h4>
                <p>{point.insight}</p>
                <div className="indecision-recommendation">
                    <strong>پیشنهاد اطلس:</strong> ساده‌سازی CTA و اضافه کردن راهنمایی بیشتر
                </div>
            </div>
        </div>
    );
};

export default BehaviorAnalyzer;
