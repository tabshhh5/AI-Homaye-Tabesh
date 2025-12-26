import React, { useState, useEffect } from 'react';
import ExecutiveOverview from './ExecutiveOverview';
import BehaviorAnalyzer from './BehaviorAnalyzer';
import RecommendationEngine from './RecommendationEngine';
import DecisionAssistant from './DecisionAssistant';
import AtlasSettings from './AtlasSettings';

/**
 * Atlas Control Center - Main Dashboard Component
 * 
 * مرکز کنترل اطلس - داشبورد اصلی
 * این کامپوننت با رعایت قانون "حداکثر ۳ کلیک" طراحی شده است
 */
const AtlasDashboard = () => {
    const [activeLayer, setActiveLayer] = useState('executive');
    const [isLoading, setIsLoading] = useState(false);

    // Layer navigation with max 3-click rule
    const layers = [
        { id: 'executive', name: 'نمای کلان', icon: '📊', component: ExecutiveOverview },
        { id: 'behavior', name: 'تحلیل رفتار', icon: '🔍', component: BehaviorAnalyzer },
        { id: 'recommendations', name: 'پیشنهادات', icon: '💡', component: RecommendationEngine },
        { id: 'simulation', name: 'شبیه‌ساز تصمیم', icon: '🎯', component: DecisionAssistant },
        { id: 'settings', name: 'تنظیمات هسته', icon: '⚙️', component: AtlasSettings, adminOnly: true },
    ];

    const ActiveComponent = layers.find(l => l.id === activeLayer)?.component || ExecutiveOverview;

    return (
        <div className="atlas-dashboard">
            {/* Navigation Tabs */}
            <div className="atlas-navigation">
                {layers.map(layer => {
                    // Hide admin-only layers for non-administrators
                    if (layer.adminOnly && window.atlasConfig?.userRole !== 'administrator') {
                        return null;
                    }

                    return (
                        <button
                            key={layer.id}
                            className={`atlas-nav-button ${activeLayer === layer.id ? 'active' : ''}`}
                            onClick={() => setActiveLayer(layer.id)}
                        >
                            <span className="icon">{layer.icon}</span>
                            <span className="label">{layer.name}</span>
                        </button>
                    );
                })}
            </div>

            {/* Active Layer Content */}
            <div className="atlas-content">
                {isLoading ? (
                    <div className="atlas-loading">
                        <div className="spinner"></div>
                        <p>در حال بارگذاری...</p>
                    </div>
                ) : (
                    <ActiveComponent />
                )}
            </div>

            {/* Footer Info */}
            <div className="atlas-footer">
                <p className="atlas-timestamp">
                    آخرین بروزرسانی: {new Date().toLocaleString('fa-IR')}
                </p>
                <p className="atlas-info">
                    🗺️ سیستم هوش تجاری اطلس - نسخه 1.0.0
                </p>
            </div>
        </div>
    );
};

export default AtlasDashboard;
