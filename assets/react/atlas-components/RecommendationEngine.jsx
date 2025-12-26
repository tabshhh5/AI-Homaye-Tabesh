import React, { useState, useEffect } from 'react';

/**
 * Recommendation Engine - Layer 3
 * موتور پیشنهادات: تبدیل داده به پیشنهاد عملی
 */
const RecommendationEngine = () => {
    const [recommendations, setRecommendations] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [filterPriority, setFilterPriority] = useState('all');

    useEffect(() => {
        fetchRecommendations();
    }, []);

    const fetchRecommendations = async () => {
        try {
            const response = await fetch(
                `${window.atlasConfig.apiUrl}/recommendations`,
                {
                    headers: {
                        'X-WP-Nonce': window.atlasConfig.nonce,
                    },
                }
            );
            const result = await response.json();
            if (result.success) {
                setRecommendations(result.data);
            }
        } catch (err) {
            console.error('Atlas Recommendations Error:', err);
        } finally {
            setIsLoading(false);
        }
    };

    if (isLoading) {
        return <div className="atlas-loading">در حال تولید پیشنهادات...</div>;
    }

    const filteredRecommendations = recommendations?.recommendations?.filter(rec => {
        if (filterPriority === 'all') return true;
        return rec.priority === filterPriority;
    }) || [];

    return (
        <div className="recommendation-engine">
            <h2>💡 موتور پیشنهادات (Recommendation Engine)</h2>
            <p className="description">
                ارائه راهکارهای ساختاری و تجاری برای بهبود عملکرد سایت
            </p>

            {/* Priority Filter */}
            <div className="filter-section">
                <label>فیلتر بر اساس اولویت:</label>
                <div className="filter-buttons">
                    <button
                        className={filterPriority === 'all' ? 'active' : ''}
                        onClick={() => setFilterPriority('all')}
                    >
                        همه
                    </button>
                    <button
                        className={filterPriority === 'high' ? 'active' : ''}
                        onClick={() => setFilterPriority('high')}
                    >
                        🔴 بالا
                    </button>
                    <button
                        className={filterPriority === 'medium' ? 'active' : ''}
                        onClick={() => setFilterPriority('medium')}
                    >
                        🟡 متوسط
                    </button>
                    <button
                        className={filterPriority === 'low' ? 'active' : ''}
                        onClick={() => setFilterPriority('low')}
                    >
                        🟢 کم
                    </button>
                </div>
            </div>

            {/* Recommendations List */}
            <div className="recommendations-list">
                {filteredRecommendations.length > 0 ? (
                    filteredRecommendations.map((rec, index) => (
                        <RecommendationCard key={index} recommendation={rec} />
                    ))
                ) : (
                    <div className="no-recommendations">
                        <p>پیشنهادی با این فیلتر یافت نشد.</p>
                    </div>
                )}
            </div>

            {/* Data to Recommendation Transformer Info */}
            <div className="transformer-info">
                <h3>🔄 تبدیل داده به پیشنهاد</h3>
                <div className="transformer-example">
                    <div className="example-flow">
                        <div className="flow-step">
                            <strong>داده خام:</strong>
                            <p>نرخ ریزش بالاست (60%)</p>
                        </div>
                        <div className="flow-arrow">→</div>
                        <div className="flow-step">
                            <strong>تحلیل اطلس:</strong>
                            <p>کاربران در فرم دچار سردرگمی می‌شوند</p>
                        </div>
                        <div className="flow-arrow">→</div>
                        <div className="flow-step">
                            <strong>پیشنهاد عملی:</strong>
                            <p>فرم را ساده کن و راهنمایی اضافه کن</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

/**
 * Recommendation Card Component
 */
const RecommendationCard = ({ recommendation }) => {
    const [isExpanded, setIsExpanded] = useState(false);

    const getPriorityColor = (priority) => {
        const colors = {
            high: '#ef4444',
            medium: '#f59e0b',
            low: '#10b981',
        };
        return colors[priority] || '#6b7280';
    };

    const getPriorityIcon = (priority) => {
        const icons = {
            high: '🔴',
            medium: '🟡',
            low: '🟢',
        };
        return icons[priority] || '⚪';
    };

    const getCategoryIcon = (category) => {
        const icons = {
            conversion: '📈',
            traffic: '🚀',
            user_experience: '✨',
            general: '📋',
        };
        return icons[category] || '📌';
    };

    return (
        <div
            className="recommendation-card"
            style={{ borderLeftColor: getPriorityColor(recommendation.priority) }}
        >
            <div className="recommendation-header">
                <div className="recommendation-title-section">
                    <span className="category-icon">{getCategoryIcon(recommendation.category)}</span>
                    <h3>{recommendation.title}</h3>
                </div>
                <span className="priority-badge" style={{ backgroundColor: getPriorityColor(recommendation.priority) }}>
                    {getPriorityIcon(recommendation.priority)} {getPriorityText(recommendation.priority)}
                </span>
            </div>

            <p className="recommendation-description">{recommendation.description}</p>

            {/* Expected Impact */}
            <div className="expected-impact">
                <strong>تاثیر پیش‌بینی شده:</strong>
                <span className="impact-value">{recommendation.expected_impact}</span>
            </div>

            {/* Actions - Expandable */}
            {recommendation.actions && recommendation.actions.length > 0 && (
                <div className="actions-section">
                    <button
                        className="expand-button"
                        onClick={() => setIsExpanded(!isExpanded)}
                    >
                        {isExpanded ? '▼' : '▶'} اقدامات پیشنهادی ({recommendation.actions.length})
                    </button>
                    {isExpanded && (
                        <ul className="actions-list">
                            {recommendation.actions.map((action, index) => (
                                <li key={index}>
                                    <span className="action-bullet">✓</span>
                                    {action}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {/* Apply Button (Placeholder for future implementation) */}
            <div className="recommendation-footer">
                <button className="apply-button" title="در نسخه‌های بعدی">
                    اعمال پیشنهاد
                </button>
                <button className="dismiss-button" title="رد کردن این پیشنهاد">
                    رد کردن
                </button>
            </div>
        </div>
    );
};

/**
 * Get priority text in Persian
 */
const getPriorityText = (priority) => {
    const priorityMap = {
        high: 'بالا',
        medium: 'متوسط',
        low: 'کم',
    };
    return priorityMap[priority] || 'نامشخص';
};

export default RecommendationEngine;
