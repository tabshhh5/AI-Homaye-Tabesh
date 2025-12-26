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
            <style jsx>{`
                .homa-super-console {
                    padding: 20px;
                    max-width: 1600px;
                    margin: 0 auto;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }

                .console-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 30px;
                    padding: 20px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                }

                .console-title h1 {
                    margin: 0 0 5px 0;
                    font-size: 28px;
                    font-weight: 700;
                }

                .console-subtitle {
                    margin: 0;
                    font-size: 14px;
                    opacity: 0.9;
                }

                .system-status-indicator {
                    display: flex;
                    align-items: center;
                }

                .status-badge {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 16px;
                    border-radius: 20px;
                    background: rgba(255, 255, 255, 0.2);
                    backdrop-filter: blur(10px);
                    font-size: 14px;
                    font-weight: 600;
                }

                .status-badge.healthy {
                    background: rgba(46, 213, 115, 0.3);
                }

                .status-badge.warning {
                    background: rgba(255, 193, 7, 0.3);
                }

                .status-badge.error {
                    background: rgba(255, 71, 87, 0.3);
                }

                .console-tabs {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 10px;
                    overflow-x: auto;
                    padding: 10px 0;
                    border-bottom: 2px solid #e0e0e0;
                }

                .console-tab {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 20px;
                    border: none;
                    background: #f5f5f5;
                    color: #333;
                    border-radius: 8px 8px 0 0;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-size: 14px;
                    font-weight: 600;
                    white-space: nowrap;
                }

                .console-tab:hover {
                    background: #e8e8e8;
                    transform: translateY(-2px);
                }

                .console-tab.active {
                    background: white;
                    color: #667eea;
                    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
                    border-bottom: 3px solid #667eea;
                }

                .tab-icon {
                    font-size: 18px;
                }

                .tab-description {
                    padding: 15px 20px;
                    background: #f9f9f9;
                    border-radius: 0 0 8px 8px;
                    margin-bottom: 20px;
                    color: #666;
                    font-size: 13px;
                }

                .console-content {
                    background: white;
                    border-radius: 12px;
                    padding: 30px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
                    min-height: 500px;
                }
            `}</style>
        </div>
    );
};

export default SuperConsole;
