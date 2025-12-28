import React, { useState, useEffect } from 'react';

/**
 * Security Center - Homa Guardian
 * مرکز امنیت - هما گاردین
 * 
 * Comprehensive security dashboard with WAF, LLM Shield, Behavior Tracking, and Access Control
 */
const SecurityCenter = () => {
    const [activeTab, setActiveTab] = useState('dashboard');
    const [loading, setLoading] = useState(true);
    const [notification, setNotification] = useState(null);
    
    // Security Data States
    const [stats, setStats] = useState({});
    const [blacklistedIps, setBlacklistedIps] = useState([]);
    const [recentActivities, setRecentActivities] = useState([]);
    const [topEvents, setTopEvents] = useState([]);
    const [authorizedRoles, setAuthorizedRoles] = useState([]);
    const [authorizedUsers, setAuthorizedUsers] = useState([]);
    
    // Settings States
    const [wafEnabled, setWafEnabled] = useState(true);
    const [llmShieldEnabled, setLlmShieldEnabled] = useState(true);
    const [behaviorTrackingEnabled, setBehaviorTrackingEnabled] = useState(true);
    const [sensitivity, setSensitivity] = useState('medium');
    const [blockThreshold, setBlockThreshold] = useState(20);
    const [blockDuration, setBlockDuration] = useState(24);
    
    // Search State
    const [userSearchQuery, setUserSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);

    useEffect(() => {
        loadSecurityData();
    }, []);

    useEffect(() => {
        if (notification) {
            const timer = setTimeout(() => setNotification(null), 3000);
            return () => clearTimeout(timer);
        }
    }, [notification]);

    const loadSecurityData = async () => {
        setLoading(true);
        try {
            // Load stats from behavior tracker
            const statsResponse = await fetch(
                `${window.wpApiSettings.root}homaye-tabesh/v1/security/statistics`,
                {
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce
                    }
                }
            );
            
            if (statsResponse.ok) {
                const statsData = await statsResponse.json();
                setStats(statsData.data || {});
            }

            // Load blacklisted IPs
            await loadBlacklistedIps();
            
            // Load recent activities
            await loadRecentActivities();
            
            // Load access control data
            await loadAccessControlData();
            
        } catch (error) {
            console.error('Failed to load security data:', error);
            showNotification('خطا در بارگذاری داده‌های امنیتی', 'error');
        } finally {
            setLoading(false);
        }
    };

    const loadBlacklistedIps = async () => {
        try {
            const response = await fetch(
                `${window.wpApiSettings.root}homaye/v1/waf/blacklist`,
                {
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce
                    }
                }
            );
            
            if (response.ok) {
                const data = await response.json();
                setBlacklistedIps(data.ips || []);
            }
        } catch (error) {
            console.error('Failed to load blacklisted IPs:', error);
        }
    };

    const loadRecentActivities = async () => {
        try {
            const response = await fetch(
                `${window.wpApiSettings.root}homaye-tabesh/v1/security/alerts`,
                {
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce
                    }
                }
            );
            
            if (response.ok) {
                const data = await response.json();
                setRecentActivities(data.alerts || []);
            }
        } catch (error) {
            console.error('Failed to load recent activities:', error);
        }
    };

    const loadAccessControlData = async () => {
        try {
            // Load authorized roles
            const rolesResponse = await fetch(
                `${window.wpApiSettings.root}homaye/v1/access-control/roles`,
                {
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce
                    }
                }
            );
            
            if (rolesResponse.ok) {
                const rolesData = await rolesResponse.json();
                setAuthorizedRoles(rolesData.roles || []);
            }

            // Load authorized users
            const usersResponse = await fetch(
                `${window.wpApiSettings.root}homaye/v1/access-control/users`,
                {
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce
                    }
                }
            );
            
            if (usersResponse.ok) {
                const usersData = await usersResponse.json();
                setAuthorizedUsers(usersData.users || []);
            }
        } catch (error) {
            console.error('Failed to load access control data:', error);
        }
    };

    const showNotification = (message, type = 'success') => {
        setNotification({ message, type });
    };

    const exportToCSV = (data, filename) => {
        if (!data || data.length === 0) {
            showNotification('داده‌ای برای export وجود ندارد', 'error');
            return;
        }

        // Convert data to CSV format
        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => 
                headers.map(header => {
                    const value = row[header] || '';
                    // Escape commas and quotes
                    return `"${String(value).replace(/"/g, '""')}"`;
                }).join(',')
            )
        ].join('\n');

        // Create blob and download
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `${filename}_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('گزارش با موفقیت دانلود شد', 'success');
    };

    const handleExportBlacklistedIps = () => {
        exportToCSV(blacklistedIps, 'blacklisted_ips');
    };

    const handleExportRecentActivities = () => {
        exportToCSV(recentActivities, 'security_activities');
    };

    const handleUnblockIp = async (ipAddress) => {
        if (!confirm(`آیا از رفع مسدودیت ${ipAddress} مطمئن هستید؟`)) {
            return;
        }

        try {
            const response = await fetch(
                `${window.wpApiSettings.root}homaye/v1/waf/blacklist/${encodeURIComponent(ipAddress)}`,
                {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce
                    }
                }
            );

            if (response.ok) {
                showNotification('مسدودیت با موفقیت رفع شد', 'success');
                loadBlacklistedIps();
            } else {
                showNotification('خطا در رفع مسدودیت', 'error');
            }
        } catch (error) {
            console.error('Failed to unblock IP:', error);
            showNotification('خطا در رفع مسدودیت', 'error');
        }
    };

    const handleToggleRole = async (roleKey) => {
        try {
            const response = await fetch(
                `${window.wpApiSettings.root}homaye/v1/access-control/roles`,
                {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ role: roleKey })
                }
            );

            if (response.ok) {
                showNotification('نقش با موفقیت به‌روزرسانی شد', 'success');
                loadAccessControlData();
            } else {
                showNotification('خطا در به‌روزرسانی نقش', 'error');
            }
        } catch (error) {
            console.error('Failed to toggle role:', error);
            showNotification('خطا در به‌روزرسانی نقش', 'error');
        }
    };

    const handleSearchUsers = async (query) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        try {
            const response = await fetch(
                `${window.wpApiSettings.root}homaye/v1/access-control/users/search?search=${encodeURIComponent(query)}`,
                {
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce
                    }
                }
            );

            if (response.ok) {
                const data = await response.json();
                setSearchResults(data.users || []);
            }
        } catch (error) {
            console.error('Failed to search users:', error);
        }
    };

    const handleAddUser = async (userId) => {
        try {
            const response = await fetch(
                `${window.wpApiSettings.root}homaye/v1/access-control/users`,
                {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ user_id: userId })
                }
            );

            if (response.ok) {
                showNotification('کاربر با موفقیت اضافه شد', 'success');
                setUserSearchQuery('');
                setSearchResults([]);
                loadAccessControlData();
            } else {
                showNotification('خطا در افزودن کاربر', 'error');
            }
        } catch (error) {
            console.error('Failed to add user:', error);
            showNotification('خطا در افزودن کاربر', 'error');
        }
    };

    const handleRemoveUser = async (userId) => {
        if (!confirm('آیا از حذف این کاربر مطمئن هستید؟')) {
            return;
        }

        try {
            const response = await fetch(
                `${window.wpApiSettings.root}homaye/v1/access-control/users/${userId}`,
                {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': window.wpApiSettings.nonce
                    }
                }
            );

            if (response.ok) {
                showNotification('کاربر با موفقیت حذف شد', 'success');
                loadAccessControlData();
            } else {
                showNotification('خطا در حذف کاربر', 'error');
            }
        } catch (error) {
            console.error('Failed to remove user:', error);
            showNotification('خطا در حذف کاربر', 'error');
        }
    };

    const getScoreColor = (score) => {
        if (score >= 80) return '#00a32a'; // Green - Safe
        if (score >= 50) return '#dba617'; // Yellow - Suspicious
        if (score >= 20) return '#d63638'; // Red - Dangerous
        return '#000000'; // Black - Blocked
    };

    const getScoreLabel = (score) => {
        if (score >= 80) return '🟢 ایمن';
        if (score >= 50) return '🟡 مشکوک';
        if (score >= 20) return '🔴 خطرناک';
        return '⚫ مسدود';
    };

    const tabs = [
        { id: 'dashboard', name: '📊 داشبورد', icon: '📊' },
        { id: 'waf', name: '🔥 فایروال (WAF)', icon: '🔥' },
        { id: 'llm-shield', name: '🛡️ سپر مدل زبانی', icon: '🛡️' },
        { id: 'behavior', name: '👁️ ردیابی رفتار', icon: '👁️' },
        { id: 'access-control', name: '👥 کنترل دسترسی', icon: '👥' },
        { id: 'settings', name: '⚙️ تنظیمات', icon: '⚙️' }
    ];

    if (loading) {
        return (
            <div className="security-center-loading" dir="rtl">
                <div className="spinner"></div>
                <p>در حال بارگذاری مرکز امنیت...</p>
            </div>
        );
    }

    return (
        <div className="security-center" dir="rtl">
            {/* Header */}
            <div className="security-header">
                <h1>🛡️ مرکز امنیت - هما گاردین (Homa Guardian)</h1>
                <p className="security-subtitle">سیستم امنیتی پیشرفته با فایروال چندلایه، محافظت از مدل زبانی و امتیازدهی رفتاری کاربران</p>
            </div>

            {/* Notification Banner */}
            {notification && (
                <div className={`notification-banner ${notification.type}`}>
                    <span>{notification.type === 'success' ? '✅' : '❌'} {notification.message}</span>
                </div>
            )}

            {/* Tabs Navigation */}
            <div className="security-tabs">
                {tabs.map(tab => (
                    <button
                        key={tab.id}
                        className={`security-tab ${activeTab === tab.id ? 'active' : ''}`}
                        onClick={() => setActiveTab(tab.id)}
                    >
                        <span className="tab-icon">{tab.icon}</span>
                        <span className="tab-name">{tab.name}</span>
                    </button>
                ))}
            </div>

            {/* Tab Content */}
            <div className="security-content">
                {activeTab === 'dashboard' && (
                    <div className="dashboard-tab">
                        {/* Stats Cards */}
                        <div className="stats-grid">
                            <div className="stat-card">
                                <h3>📊 آمار امنیتی</h3>
                                <div className="stat-item">
                                    <span>کل رویدادها:</span>
                                    <strong>{stats.total_events || 0}</strong>
                                </div>
                                <div className="stat-item">
                                    <span>رویدادهای 24h:</span>
                                    <strong>{stats.events_24h || 0}</strong>
                                </div>
                                <div className="stat-item" style={{ color: '#d63638' }}>
                                    <span>کاربران مسدود:</span>
                                    <strong>{stats.blocked_users || 0}</strong>
                                </div>
                                <div className="stat-item" style={{ color: '#dba617' }}>
                                    <span>کاربران مشکوک:</span>
                                    <strong>{stats.suspicious_users || 0}</strong>
                                </div>
                                <div className="stat-item" style={{ color: '#00a32a' }}>
                                    <span>کاربران ایمن:</span>
                                    <strong>{stats.safe_users || 0}</strong>
                                </div>
                            </div>

                            <div className="stat-card">
                                <h3>🔥 فایروال (WAF)</h3>
                                <div className="status-badge active">
                                    ✓ فعال
                                </div>
                                <div className="stat-item">
                                    <span>IPهای مسدود شده:</span>
                                    <strong>{blacklistedIps.length}</strong>
                                </div>
                                <button className="btn-secondary" onClick={loadBlacklistedIps}>
                                    🔄 بروزرسانی
                                </button>
                            </div>

                            <div className="stat-card">
                                <h3>🛡️ سپر مدل زبانی</h3>
                                <div className="status-badge active">
                                    ✓ فعال
                                </div>
                                <p className="card-description">محافظت از ورودی و خروجی Gemini API در برابر:</p>
                                <ul className="protection-list">
                                    <li>Prompt Injection</li>
                                    <li>Data Leaking</li>
                                    <li>PII Protection</li>
                                </ul>
                            </div>

                            <div className="stat-card">
                                <h3>👥 کنترل دسترسی</h3>
                                <p className="card-description">مدیریت دسترسی تیم داخلی به ابزارهای اطلس و مانیتورینگ</p>
                                <button 
                                    className="btn-primary" 
                                    onClick={() => setActiveTab('access-control')}
                                >
                                    ⚙️ تنظیمات دسترسی
                                </button>
                            </div>
                        </div>

                        {/* Blacklisted IPs Table */}
                        <div className="section-card">
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
                                <h2 style={{ margin: 0 }}>🚫 IPهای مسدود شده</h2>
                                {blacklistedIps.length > 0 && (
                                    <button className="btn-secondary btn-small" onClick={handleExportBlacklistedIps}>
                                        📥 Export CSV
                                    </button>
                                )}
                            </div>
                            {blacklistedIps.length === 0 ? (
                                <p className="empty-message">هیچ IP مسدود شده‌ای وجود ندارد.</p>
                            ) : (
                                <div className="table-container">
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                <th>آدرس IP</th>
                                                <th>دلیل مسدودسازی</th>
                                                <th>زمان مسدودسازی</th>
                                                <th>انقضا</th>
                                                <th>عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {blacklistedIps.map((ip, index) => (
                                                <tr key={index}>
                                                    <td><code>{ip.ip_address}</code></td>
                                                    <td>{ip.reason}</td>
                                                    <td>{ip.blocked_at}</td>
                                                    <td>{ip.expires_at || 'دائمی'}</td>
                                                    <td>
                                                        <button 
                                                            className="btn-small btn-danger"
                                                            onClick={() => handleUnblockIp(ip.ip_address)}
                                                        >
                                                            🔓 رفع مسدودیت
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        {/* Recent Suspicious Activities */}
                        <div className="section-card">
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
                                <h2 style={{ margin: 0 }}>⚠️ فعالیت‌های مشکوک اخیر (24 ساعت)</h2>
                                {recentActivities.length > 0 && (
                                    <button className="btn-secondary btn-small" onClick={handleExportRecentActivities}>
                                        📥 Export CSV
                                    </button>
                                )}
                            </div>
                            {recentActivities.length === 0 ? (
                                <p className="success-message">فعالیت مشکوکی در 24 ساعت اخیر ثبت نشده است. ✓</p>
                            ) : (
                                <div className="table-container">
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                <th>شناسه کاربر</th>
                                                <th>نوع رویداد</th>
                                                <th>امتیاز کسر شده</th>
                                                <th>امتیاز فعلی</th>
                                                <th>زمان</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {recentActivities.map((activity, index) => (
                                                <tr key={index}>
                                                    <td><code>{activity.user_identifier}</code></td>
                                                    <td>{activity.event_type}</td>
                                                    <td style={{ color: '#d63638' }}>-{activity.penalty_points}</td>
                                                    <td style={{ color: getScoreColor(activity.current_score) }}>
                                                        <strong>{activity.current_score}</strong> {getScoreLabel(activity.current_score)}
                                                    </td>
                                                    <td>{activity.created_at}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        {/* Top Events */}
                        {stats.top_events && stats.top_events.length > 0 && (
                            <div className="section-card">
                                <h2>📈 انواع رویدادهای امنیتی (7 روز اخیر)</h2>
                                <div className="table-container">
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                <th>نوع رویداد</th>
                                                <th>تعداد</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {stats.top_events.map((event, index) => (
                                                <tr key={index}>
                                                    <td>{event.event_type}</td>
                                                    <td><strong>{event.count}</strong></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'access-control' && (
                    <div className="access-control-tab">
                        <div className="section-card">
                            <h2>👥 مدیریت سطوح دسترسی تیم داخلی</h2>
                            <p className="section-description">تنظیم دسترسی کارمندان و تیم عملیاتی به ابزارهای اطلس، گزارشات و امکانات مدیریتی هما</p>

                            {/* Authorized Roles */}
                            <div className="subsection">
                                <h3>نقش‌های کاربری مجاز:</h3>
                                <div className="roles-grid">
                                    {authorizedRoles.map((role) => (
                                        <label key={role.key} className={`role-card ${role.authorized ? 'active' : ''}`}>
                                            <input
                                                type="checkbox"
                                                checked={role.authorized}
                                                onChange={() => handleToggleRole(role.key)}
                                            />
                                            <span className="role-name">{role.name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {/* Authorized Users */}
                            <div className="subsection">
                                <h3>کاربران مجاز (انتخاب فردی):</h3>
                                {authorizedUsers.length === 0 ? (
                                    <p className="empty-message">هیچ کاربر فردی اضافه نشده است.</p>
                                ) : (
                                    <div className="users-list">
                                        {authorizedUsers.map((user) => (
                                            <div key={user.id} className="user-card">
                                                <div className="user-info">
                                                    <strong>{user.display_name}</strong> ({user.username})
                                                    <br />
                                                    <small>{user.email}</small>
                                                </div>
                                                <button
                                                    className="btn-small btn-danger"
                                                    onClick={() => handleRemoveUser(user.id)}
                                                >
                                                    حذف
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Add New User */}
                            <div className="subsection">
                                <h3>افزودن کاربر جدید:</h3>
                                <div className="user-search">
                                    <input
                                        type="text"
                                        className="search-input"
                                        placeholder="جستجوی کاربر..."
                                        value={userSearchQuery}
                                        onChange={(e) => {
                                            setUserSearchQuery(e.target.value);
                                            handleSearchUsers(e.target.value);
                                        }}
                                    />
                                    {searchResults.length > 0 && (
                                        <div className="search-results">
                                            {searchResults.map((user) => (
                                                <div key={user.id} className="search-result-item">
                                                    <div className="user-info">
                                                        <strong>{user.display_name}</strong> ({user.username})
                                                        <br />
                                                        <small>{user.email}</small>
                                                    </div>
                                                    <button
                                                        className="btn-small btn-primary"
                                                        onClick={() => handleAddUser(user.id)}
                                                    >
                                                        افزودن
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'settings' && (
                    <div className="settings-tab">
                        <div className="section-card">
                            <h2>⚙️ تنظیمات امنیتی</h2>
                            
                            <div className="settings-grid">
                                <div className="setting-group">
                                    <h3>فعال‌سازی لایه‌های امنیتی</h3>
                                    
                                    <div className="setting-item">
                                        <label className="toggle-label">
                                            <input
                                                type="checkbox"
                                                checked={wafEnabled}
                                                onChange={(e) => setWafEnabled(e.target.checked)}
                                            />
                                            <span>🔥 فایروال وب (WAF)</span>
                                        </label>
                                    </div>

                                    <div className="setting-item">
                                        <label className="toggle-label">
                                            <input
                                                type="checkbox"
                                                checked={llmShieldEnabled}
                                                onChange={(e) => setLlmShieldEnabled(e.target.checked)}
                                            />
                                            <span>🛡️ سپر مدل زبانی (LLM Shield)</span>
                                        </label>
                                    </div>

                                    <div className="setting-item">
                                        <label className="toggle-label">
                                            <input
                                                type="checkbox"
                                                checked={behaviorTrackingEnabled}
                                                onChange={(e) => setBehaviorTrackingEnabled(e.target.checked)}
                                            />
                                            <span>👁️ ردیابی رفتار کاربران</span>
                                        </label>
                                    </div>
                                </div>

                                <div className="setting-group">
                                    <h3>پارامترهای امنیتی</h3>
                                    
                                    <div className="setting-item">
                                        <label>سطح حساسیت فایروال:</label>
                                        <select 
                                            value={sensitivity}
                                            onChange={(e) => setSensitivity(e.target.value)}
                                        >
                                            <option value="low">کم - سازگار با همه</option>
                                            <option value="medium">متوسط - توصیه شده</option>
                                            <option value="high">بالا - سختگیرانه</option>
                                        </select>
                                    </div>

                                    <div className="setting-item">
                                        <label>حد آستانه مسدودسازی (امتیاز امنیتی):</label>
                                        <input
                                            type="number"
                                            value={blockThreshold}
                                            onChange={(e) => setBlockThreshold(parseInt(e.target.value))}
                                            min="0"
                                            max="100"
                                        />
                                        <small>کاربران با امتیاز کمتر از این مقدار مسدود می‌شوند</small>
                                    </div>

                                    <div className="setting-item">
                                        <label>مدت زمان مسدودسازی IP (ساعت):</label>
                                        <input
                                            type="number"
                                            value={blockDuration}
                                            onChange={(e) => setBlockDuration(parseInt(e.target.value))}
                                            min="1"
                                            max="720"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="settings-actions">
                                <button className="btn-primary">
                                    💾 ذخیره تنظیمات
                                </button>
                                <button className="btn-secondary" onClick={loadSecurityData}>
                                    🔄 بازنشانی
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* WAF Tab */}
                {activeTab === 'waf' && (
                    <div className="waf-tab">
                        <div className="section-card">
                            <h2>🔥 فایروال وب اپلیکیشن (WAF)</h2>
                            <p className="section-description">
                                فایروال وب اپلیکیشن (WAF) لایه اول دفاعی هما گاردین است که تمام درخواست‌های HTTP را قبل از پردازش توسط وردپرس بررسی می‌کند.
                            </p>

                            <div className="info-grid">
                                <div className="info-box">
                                    <h3>🛡️ الگوهای تشخیص حمله</h3>
                                    <ul className="feature-list">
                                        <li><strong>SQL Injection:</strong> تشخیص الگوهای UNION SELECT, DROP TABLE, OR 1=1</li>
                                        <li><strong>XSS (Cross-Site Scripting):</strong> فیلتر script tags, javascript:, onerror</li>
                                        <li><strong>RCE (Remote Code Execution):</strong> جلوگیری از eval(), exec(), system()</li>
                                        <li><strong>File Access:</strong> حفاظت از wp-config.php, .env, .git</li>
                                    </ul>
                                </div>

                                <div className="info-box">
                                    <h3>🎯 سیستم امتیازدهی تهدید (Threat Scoring)</h3>
                                    <ul className="score-list">
                                        <li>دسترسی به فایل‌های حساس: <strong style={{ color: '#d63638' }}>+80 امتیاز</strong></li>
                                        <li>RCE Attempt: <strong style={{ color: '#d63638' }}>+80 امتیاز</strong></li>
                                        <li>SQL Injection: <strong style={{ color: '#d63638' }}>+60 امتیاز</strong></li>
                                        <li>XSS Attempt: <strong style={{ color: '#d63638' }}>+60 امتیاز</strong></li>
                                        <li>Rapid Scanning: <strong style={{ color: '#dba617' }}>+50 امتیاز</strong></li>
                                    </ul>
                                    <p style={{ marginTop: '15px', color: '#666' }}>
                                        <strong>آستانه مسدودسازی خودکار:</strong> 100 امتیاز
                                    </p>
                                </div>

                                <div className="info-box">
                                    <h3>🤖 لیست سفید SEO</h3>
                                    <p>رباتهای موتورهای جستجو با Reverse DNS Verification تایید و از WAF معاف می‌شوند:</p>
                                    <ul className="bot-list">
                                        <li>✓ Googlebot</li>
                                        <li>✓ Bingbot</li>
                                        <li>✓ Yahoo Slurp</li>
                                        <li>✓ DuckDuckBot</li>
                                        <li>✓ Baiduspider</li>
                                        <li>✓ YandexBot</li>
                                    </ul>
                                </div>

                                <div className="info-box">
                                    <h3>⚡ عملکرد و بهینه‌سازی</h3>
                                    <div className="stat-item">
                                        <span>Overhead per request:</span>
                                        <strong style={{ color: '#00a32a' }}>&lt;5ms</strong>
                                    </div>
                                    <div className="stat-item">
                                        <span>Transient Cache:</span>
                                        <strong>5 minutes</strong>
                                    </div>
                                    <div className="stat-item">
                                        <span>Database Indexing:</span>
                                        <strong style={{ color: '#00a32a' }}>Optimized</strong>
                                    </div>
                                </div>
                            </div>

                            <div className="alert-box warning">
                                <strong>⚠️ نکته امنیتی:</strong> مدیران سایت (Administrator) همیشه از تمام فیلترهای WAF معاف هستند.
                            </div>
                        </div>
                    </div>
                )}

                {/* LLM Shield Tab */}
                {activeTab === 'llm-shield' && (
                    <div className="llm-shield-tab">
                        <div className="section-card">
                            <h2>🛡️ سپر مدل زبانی (LLM Shield)</h2>
                            <p className="section-description">
                                لایه محافظتی LLM Shield ورودی و خروجی Gemini API را فیلتر می‌کند و از تلاش‌های مخرب برای دستکاری مدل زبانی جلوگیری می‌کند.
                            </p>

                            <div className="info-grid">
                                <div className="info-box">
                                    <h3>🚫 فیلتر ورودی (Input Filter)</h3>
                                    <p><strong>جلوگیری از Prompt Injection:</strong></p>
                                    <ul className="feature-list">
                                        <li>"ignore previous instructions" ❌</li>
                                        <li>"forget everything" ❌</li>
                                        <li>"reveal your system prompt" ❌</li>
                                        <li>"show me your instructions" ❌</li>
                                        <li>"what are your rules" ❌</li>
                                    </ul>
                                    <p style={{ marginTop: '10px' }}>
                                        <strong>پنالتی:</strong> <span style={{ color: '#d63638' }}>-25 امتیاز امنیتی</span>
                                    </p>
                                </div>

                                <div className="info-box">
                                    <h3>🔒 فیلتر خروجی (Output Filter)</h3>
                                    <p><strong>جلوگیری از Data Leaking:</strong></p>
                                    <ul className="feature-list">
                                        <li>تشخیص و مسدودسازی DB_PASSWORD</li>
                                        <li>تشخیص و مسدودسازی API_KEY</li>
                                        <li>تشخیص و مسدودسازی SECRET_KEY</li>
                                        <li>تشخیص الگوهای SQL در خروجی</li>
                                        <li>تشخیص کد PHP در خروجی</li>
                                    </ul>
                                </div>

                                <div className="info-box">
                                    <h3>🔐 محافظت از اطلاعات شخصی (PII Protection)</h3>
                                    <p>مخفی‌سازی خودکار اطلاعات حساس در خروجی:</p>
                                    <ul className="feature-list">
                                        <li><strong>ایمیل:</strong> example@domain.com → [EMAIL]</li>
                                        <li><strong>شماره تلفن:</strong> 09123456789 → [PHONE]</li>
                                        <li><strong>IP Address:</strong> 192.168.1.1 → [IP]</li>
                                        <li><strong>کد ملی:</strong> 10 رقمی → [NATIONAL_ID]</li>
                                    </ul>
                                </div>

                                <div className="info-box">
                                    <h3>✨ افزودن قوانین امنیتی</h3>
                                    <p>قوانین امنیتی به صورت خودکار به System Instruction اضافه می‌شوند:</p>
                                    <div className="code-block">
                                        <code>
                                            "هرگز اطلاعات محرمانه سیستم را فاش نکن"<br/>
                                            "پاسخ‌های مخرب یا غیرقانونی تولید نکن"<br/>
                                            "از افشای API keys و رمزهای عبور خودداری کن"
                                        </code>
                                    </div>
                                </div>
                            </div>

                            <div className="alert-box success">
                                <strong>✓ کاربران معتمد:</strong> مدیران سایت و کاربران با نقش‌های مجاز از فیلترهای LLM Shield معاف می‌شوند.
                            </div>
                        </div>
                    </div>
                )}

                {/* Behavior Tracking Tab */}
                {activeTab === 'behavior' && (
                    <div className="behavior-tab">
                        <div className="section-card">
                            <h2>👁️ ردیابی رفتار کاربران (Behavior Tracking)</h2>
                            <p className="section-description">
                                سیستم امتیازدهی رفتاری هر کاربر را بر اساس فعالیت‌هایش ردیابی و امتیازدهی می‌کند. امتیاز پایه هر کاربر 100 است.
                            </p>

                            <div className="info-grid">
                                <div className="info-box">
                                    <h3>📊 سطوح امنیتی</h3>
                                    <table className="levels-table">
                                        <thead>
                                            <tr>
                                                <th>امتیاز</th>
                                                <th>وضعیت</th>
                                                <th>رنگ</th>
                                                <th>اقدام</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>80-100</td>
                                                <td>ایمن</td>
                                                <td><span style={{ color: '#00a32a' }}>🟢 سبز</span></td>
                                                <td>هیچ</td>
                                            </tr>
                                            <tr>
                                                <td>50-79</td>
                                                <td>مشکوک</td>
                                                <td><span style={{ color: '#dba617' }}>🟡 زرد</span></td>
                                                <td>هشدار</td>
                                            </tr>
                                            <tr>
                                                <td>20-49</td>
                                                <td>خطرناک</td>
                                                <td><span style={{ color: '#d63638' }}>🔴 قرمز</span></td>
                                                <td>محدودیت</td>
                                            </tr>
                                            <tr>
                                                <td>0-19</td>
                                                <td>مسدود</td>
                                                <td>⚫ سیاه</td>
                                                <td>بلاک کامل</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div className="info-box">
                                    <h3>⚠️ پنالتی‌های رویدادها</h3>
                                    <div className="penalty-list">
                                        <div className="penalty-item">
                                            <span>RCE Attempt</span>
                                            <strong style={{ color: '#d63638' }}>-50</strong>
                                        </div>
                                        <div className="penalty-item">
                                            <span>SQL Injection</span>
                                            <strong style={{ color: '#d63638' }}>-40</strong>
                                        </div>
                                        <div className="penalty-item">
                                            <span>XSS Attempt</span>
                                            <strong style={{ color: '#d63638' }}>-35</strong>
                                        </div>
                                        <div className="penalty-item">
                                            <span>WAF Block</span>
                                            <strong style={{ color: '#d63638' }}>-30</strong>
                                        </div>
                                        <div className="penalty-item">
                                            <span>LLM Shield Block</span>
                                            <strong style={{ color: '#d63638' }}>-25</strong>
                                        </div>
                                        <div className="penalty-item">
                                            <span>Prompt Injection</span>
                                            <strong style={{ color: '#d63638' }}>-25</strong>
                                        </div>
                                        <div className="penalty-item">
                                            <span>404 Spam</span>
                                            <strong style={{ color: '#dba617' }}>-10</strong>
                                        </div>
                                    </div>
                                </div>

                                <div className="info-box">
                                    <h3>🔍 Browser Fingerprinting</h3>
                                    <p>برای ردیابی کاربران مهمان (Guest Users):</p>
                                    <ul className="feature-list">
                                        <li>IP Address</li>
                                        <li>User Agent</li>
                                        <li>Browser Fingerprint (SHA256)</li>
                                        <li>Session Data</li>
                                    </ul>
                                    <p style={{ marginTop: '10px', color: '#666' }}>
                                        <small>کاربران با حساب کاربری با user_id ردیابی می‌شوند</small>
                                    </p>
                                </div>

                                <div className="info-box">
                                    <h3>📈 ردیابی 404 (Scanning Detection)</h3>
                                    <p><strong>آستانه تشخیص اسکن:</strong></p>
                                    <div className="stat-item">
                                        <span>بیش از 10 خطای 404</span>
                                        <strong style={{ color: '#d63638' }}>در 5 دقیقه</strong>
                                    </div>
                                    <p style={{ marginTop: '10px' }}>
                                        <strong>پنالتی:</strong> <span style={{ color: '#d63638' }}>-15 امتیاز</span>
                                    </p>
                                    <p style={{ color: '#666', fontSize: '13px', marginTop: '5px' }}>
                                        این ویژگی برای تشخیص اسکن خودکار و تلاش‌های brute-force طراحی شده است.
                                    </p>
                                </div>
                            </div>

                            <div className="alert-box info">
                                <strong>ℹ️ Cache و Performance:</strong> امتیازهای امنیتی با Transient Cache (5 دقیقه) ذخیره می‌شوند تا از فشار بر دیتابیس جلوگیری شود.
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default SecurityCenter;
