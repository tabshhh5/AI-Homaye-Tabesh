import React, { useState, useEffect } from 'react';
import OverviewAnalytics from './OverviewAnalytics';
import UserIntelligence from './UserIntelligence';
import SystemHealth from './SystemHealth';
import BrainGrowth from './BrainGrowth';
import SuperSettings from './SuperSettings';

/**
 * Homa Super Console - Central Control Dashboard
 * سوپر پنل هما - مرکز کنترل متمرکز
 * 
 * Single Page Application for unified management of all Homa modules
 */
const SuperConsole = () => {
    const [activeTab, setActiveTab] = useState('overview');
    const [systemStatus, setSystemStatus] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    // Tab configuration with Persian labels
    const tabs = [
        { 
            id: 'overview', 
            name: 'داشبورد اجرایی', 
            icon: '📊', 
            component: OverviewAnalytics,
            description: 'نمودارهای مصرف و دادههای استراتژیک'
        },
        { 
            id: 'users', 
            name: 'مدیریت کاربران', 
            icon: '👥', 
            component: UserIntelligence,
            description: 'پروفایل ۳۶۰ درجه و تاریخچه گفتگوها'
        },
        { 
            id: 'health', 
            name: 'سلامت و عیبیابی', 
            icon: '🏥', 
            component: SystemHealth,
            description: 'مانیتورینگ زنده و عیبیاب خودکار'
        },
        { 
            id: 'brain', 
            name: 'توسعه مغز', 
            icon: '🧠', 
            component: BrainGrowth,
            description: 'رشد دانش و مدیریت محتوا'
        },
        { 
            id: 'settings', 
            name: 'پیکربندی پیشرفته', 
            icon: '⚙️', 
            component: SuperSettings,
            description: 'تنظیمات طبقهبندی شده و فایروال',
            adminOnly: true
        },
    ];

    // Check if current user has admin privileges
    const isAdmin = window.homaConsoleConfig?.userRole === 'administrator';

    // Load system status on mount
    useEffect(() => {
        loadSystemStatus();
    }, []);

    const loadSystemStatus = async () => {
        try {
            const response = await fetch(window.homaConsoleConfig.apiUrl + '/system/status', {
                headers: {
                    'X-WP-Nonce': window.homaConsoleConfig.nonce
                }
            });
            const data = await response.json();
            if (data.success) {
                setSystemStatus(data.data);
            }
        } catch (error) {
            console.error('Failed to load system status:', error);
        }
    };

    const ActiveComponent = tabs.find(t => t.id === activeTab)?.component || OverviewAnalytics;

    return (
        <div className="homa-super-console" dir="rtl">
            {/* Header with system status indicator */}
            <div className="console-header">
                <div className="console-title">
                    <h1>🎛️ سوپر پنل هما (Homa Super Console)</h1>
                    <p className="console-subtitle">مرکز کنترل متمرکز و داشبورد تحلیل داده‌های استراتژیک</p>
                </div>
                
                {systemStatus && (
                    <div className="system-status-indicator">
                        <div className={`status-badge ${systemStatus.overall_health || 'healthy'}`}>
                            <span className="status-icon">
                                {systemStatus.overall_health === 'healthy' ? '✓' : '⚠'}
                            </span>
                            <span className="status-text">
                                {systemStatus.overall_health === 'healthy' ? 'سالم' : 'نیاز به توجه'}
                            </span>
                        </div>
                    </div>
                )}
            </div>

            {/* Tab Navigation */}
            <div className="console-tabs">
                {tabs.map(tab => {
                    // Hide admin-only tabs for non-administrators
                    if (tab.adminOnly && !isAdmin) {
                        return null;
                    }

                    return (
                        <button
                            key={tab.id}
                            className={`console-tab ${activeTab === tab.id ? 'active' : ''}`}
                            onClick={() => setActiveTab(tab.id)}
                            title={tab.description}
                        >
                            <span className="tab-icon">{tab.icon}</span>
                            <span className="tab-label">{tab.name}</span>
                        </button>
                    );
                })}
            </div>

            {/* Tab Description */}
            <div className="tab-description">
                <p>{tabs.find(t => t.id === activeTab)?.description}</p>
            </div>

            {/* Active Tab Content */}
            <div className="console-content">
                <ActiveComponent onRefresh={loadSystemStatus} />
            </div>

            {/* Console Styles */}
        </div>
    );
};

export default SuperConsole;
