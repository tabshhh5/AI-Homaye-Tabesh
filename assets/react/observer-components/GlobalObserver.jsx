import React, { useState, useEffect } from 'react';

/**
 * Global Observer Dashboard - Observer Control Panel
 * ناظر کل - پنل کنترل نظارت بر افزونه‌ها
 * 
 * Modern React implementation for monitoring plugins and extracting metadata
 */
const GlobalObserver = () => {
    const [observerStatus, setObserverStatus] = useState(null);
    const [pluginsList, setPluginsList] = useState([]);
    const [recentChanges, setRecentChanges] = useState([]);
    const [recentFacts, setRecentFacts] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [refreshStatus, setRefreshStatus] = useState('');

    const apiBase = window.homaObserverConfig?.apiUrl || '/wp-json/homaye/v1';
    const nonce = window.homaObserverConfig?.nonce || '';

    // API helper function
    const apiRequest = async (endpoint, method = 'GET', data = null) => {
        const options = {
            method,
            headers: {
                'X-WP-Nonce': nonce,
                'Content-Type': 'application/json'
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`${apiBase}${endpoint}`, options);
            return await response.json();
        } catch (error) {
            console.error('API request failed:', error);
            return { success: false, message: 'خطا در ارتباط با سرور' };
        }
    };

    // Load observer status
    const loadObserverStatus = async () => {
        const response = await apiRequest('/observer/status');
        if (response.success) {
            setObserverStatus(response.data);
        }
    };

    // Load plugins list
    const loadPluginsList = async () => {
        const response = await apiRequest('/observer/plugins');
        if (response.success) {
            setPluginsList(response.data);
        }
    };

    // Load recent changes
    const loadRecentChanges = async () => {
        const response = await apiRequest('/observer/changes');
        if (response.success) {
            setRecentChanges(response.data || []);
        }
    };

    // Load recent facts
    const loadRecentFacts = async () => {
        const response = await apiRequest('/observer/facts');
        if (response.success) {
            setRecentFacts(response.data || []);
        }
    };

    // Toggle plugin monitoring
    const toggleMonitoring = async (pluginPath, isMonitored) => {
        const endpoint = isMonitored ? '/observer/monitor/remove' : '/observer/monitor/add';
        const response = await apiRequest(endpoint, 'POST', { plugin_path: pluginPath });
        
        if (response.success) {
            // Reload data
            await Promise.all([loadObserverStatus(), loadPluginsList()]);
        } else {
            alert('خطا در انجام عملیات');
        }
    };

    // Refresh metadata
    const handleRefreshMetadata = async () => {
        setIsRefreshing(true);
        setRefreshStatus('');
        
        const response = await apiRequest('/observer/refresh', 'POST');
        
        if (response.success) {
            setRefreshStatus('✅ متادیتا به‌روزرسانی شد!');
            await loadObserverStatus();
        } else {
            setRefreshStatus('❌ خطا در به‌روزرسانی');
        }
        
        setIsRefreshing(false);
    };

    // Initial load
    useEffect(() => {
        const loadAllData = async () => {
            setIsLoading(true);
            await Promise.all([
                loadObserverStatus(),
                loadPluginsList(),
                loadRecentChanges(),
                loadRecentFacts()
            ]);
            setIsLoading(false);
        };

        loadAllData();

        // Auto-refresh every 30 seconds
        const interval = setInterval(() => {
            loadRecentChanges();
            loadRecentFacts();
        }, 30000);

        return () => clearInterval(interval);
    }, []);

    if (isLoading) {
        return (
            <div className="homa-observer" dir="rtl">
                <div className="observer-loading">
                    <div className="loading-spinner"></div>
                    <p>در حال بارگذاری...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="homa-observer" dir="rtl">
            {/* Header */}
            <div className="observer-header">
                <h1>🔍 ناظر کل افزونه‌ها</h1>
                <p className="observer-subtitle">
                    مدیریت نظارت بر افزونه‌ها و استخراج اطلاعات برای هوش مصنوعی
                </p>
            </div>

            {/* Observer Status Card */}
            <div className="observer-card">
                <h2>وضعیت ناظر کل</h2>
                {observerStatus ? (
                    <div className="status-grid">
                        <div className="status-item">
                            <span className="status-icon">✅</span>
                            <div className="status-info">
                                <span className="status-label">افزونه‌های تحت نظر</span>
                                <span className="status-value">{observerStatus.monitored_count}</span>
                            </div>
                        </div>
                        <div className="status-item">
                            <span className="status-icon">🔌</span>
                            <div className="status-info">
                                <span className="status-label">افزونه‌های فعال</span>
                                <span className="status-value">{observerStatus.active_count}</span>
                            </div>
                        </div>
                        <div className="status-item">
                            <span className="status-icon">🕐</span>
                            <div className="status-info">
                                <span className="status-label">آخرین همگام‌سازی</span>
                                <span className="status-value">{observerStatus.last_sync}</span>
                            </div>
                        </div>
                    </div>
                ) : (
                    <p>در حال بارگذاری...</p>
                )}
            </div>

            {/* Plugins List Card */}
            <div className="observer-card">
                <h2>افزونه‌های نصب شده</h2>
                <p className="card-description">
                    افزونه‌های تحت نظر با ✅ مشخص شده‌اند. برای اضافه/حذف کردن افزونه از لیست نظارت، روی دکمه کلیک کنید.
                </p>
                <div className="plugins-table-container">
                    {pluginsList.length > 0 ? (
                        <table className="plugins-table">
                            <thead>
                                <tr>
                                    <th>نام افزونه</th>
                                    <th>نسخه</th>
                                    <th>وضعیت</th>
                                    <th>نظارت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pluginsList.map((plugin, index) => (
                                    <tr key={index}>
                                        <td>
                                            <strong>{plugin.name}</strong>
                                            <br />
                                            <small className="plugin-description">{plugin.description}</small>
                                        </td>
                                        <td>{plugin.version}</td>
                                        <td>
                                            <span className={`status-badge ${plugin.is_active ? 'active' : 'inactive'}`}>
                                                {plugin.is_active ? '✅ فعال' : '❌ غیرفعال'}
                                            </span>
                                        </td>
                                        <td>
                                            <span className={`monitor-badge ${plugin.is_monitored ? 'monitored' : 'not-monitored'}`}>
                                                {plugin.is_monitored ? '✅ تحت نظر' : '➖ خیر'}
                                            </span>
                                        </td>
                                        <td>
                                            <button
                                                className={`toggle-btn ${plugin.is_monitored ? 'remove' : 'add'}`}
                                                onClick={() => toggleMonitoring(plugin.path, plugin.is_monitored)}
                                            >
                                                {plugin.is_monitored ? 'حذف از نظارت' : 'اضافه به نظارت'}
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <p className="no-data">هیچ افزونه‌ای یافت نشد</p>
                    )}
                </div>
            </div>

            {/* Recent Changes Card */}
            <div className="observer-card">
                <h2>تغییرات اخیر</h2>
                {recentChanges.length > 0 ? (
                    <table className="changes-table">
                        <thead>
                            <tr>
                                <th>نوع رویداد</th>
                                <th>زمان</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recentChanges.map((change, index) => (
                                <tr key={index}>
                                    <td>{change.event_type}</td>
                                    <td>{change.created_at}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <p className="no-data">هیچ تغییری ثبت نشده است.</p>
                )}
            </div>

            {/* Recent Facts Card */}
            <div className="observer-card">
                <h2>فکت‌های استخراج شده</h2>
                {recentFacts.length > 0 ? (
                    <ul className="facts-list">
                        {recentFacts.map((fact, index) => (
                            <li key={index}>
                                <strong>{fact.fact}</strong>
                                <small className="fact-date"> ({fact.created_at})</small>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="no-data">هیچ فکتی استخراج نشده است.</p>
                )}
            </div>

            {/* Actions Card */}
            <div className="observer-card">
                <h2>عملیات</h2>
                <div className="actions-container">
                    <button
                        className="refresh-btn"
                        onClick={handleRefreshMetadata}
                        disabled={isRefreshing}
                    >
                        {isRefreshing ? 'در حال به‌روزرسانی...' : 'به‌روزرسانی متادیتا'}
                    </button>
                    {refreshStatus && (
                        <span className={`refresh-status ${refreshStatus.includes('✅') ? 'success' : 'error'}`}>
                            {refreshStatus}
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
};

export default GlobalObserver;
