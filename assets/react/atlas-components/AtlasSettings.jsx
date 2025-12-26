import React, { useState, useEffect } from 'react';

/**
 * Atlas Settings - Layer 5
 * پیکربندی هسته: مدیریت سطح هوش و بازه‌های اسکن (Administrator Only)
 */
const AtlasSettings = () => {
    const [settings, setSettings] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [saveMessage, setSaveMessage] = useState(null);

    useEffect(() => {
        // Security check: Only administrators can access this layer
        if (window.atlasConfig?.userRole !== 'administrator') {
            return;
        }
        fetchSettings();
    }, []);

    const fetchSettings = async () => {
        try {
            const response = await fetch(
                `${window.atlasConfig.apiUrl}/settings`,
                {
                    headers: {
                        'X-WP-Nonce': window.atlasConfig.nonce,
                    },
                }
            );
            const result = await response.json();
            if (result.success) {
                setSettings(result.data);
            }
        } catch (err) {
            console.error('Atlas Settings Error:', err);
        } finally {
            setIsLoading(false);
        }
    };

    const handleSettingChange = (key, value) => {
        setSettings(prev => ({
            ...prev,
            [key]: value,
        }));
    };

    const saveSettings = async () => {
        setIsSaving(true);
        setSaveMessage(null);
        try {
            const response = await fetch(
                `${window.atlasConfig.apiUrl}/settings`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.atlasConfig.nonce,
                    },
                    body: JSON.stringify(settings),
                }
            );
            const result = await response.json();
            if (result.success) {
                setSaveMessage({ type: 'success', text: result.message });
            } else {
                setSaveMessage({ type: 'error', text: 'خطا در ذخیره تنظیمات' });
            }
        } catch (err) {
            console.error('Atlas Save Settings Error:', err);
            setSaveMessage({ type: 'error', text: 'خطا در ارتباط با سرور' });
        } finally {
            setIsSaving(false);
        }
    };

    // Security check
    if (window.atlasConfig?.userRole !== 'administrator') {
        return (
            <div className="atlas-security-warning">
                <h2>🔒 دسترسی محدود</h2>
                <p>این بخش فقط برای مدیران (Administrator) قابل دسترسی است.</p>
            </div>
        );
    }

    if (isLoading) {
        return <div className="atlas-loading">در حال بارگذاری تنظیمات...</div>;
    }

    return (
        <div className="atlas-settings">
            <h2>⚙️ تنظیمات هسته (Core Configuration)</h2>
            <p className="description">
                مدیریت سطح هوش، بازه‌های اسکن و امنیت اطلس
            </p>

            {/* Security Notice */}
            <div className="security-notice">
                <span className="security-icon">🔒</span>
                <div className="security-content">
                    <strong>هشدار امنیتی:</strong>
                    <p>
                        این تنظیمات تأثیر مستقیم بر عملکرد کل سیستم اطلس دارند. 
                        فقط در صورت آگاهی کامل تغییر دهید.
                    </p>
                </div>
            </div>

            {/* Save Message */}
            {saveMessage && (
                <div className={`save-message ${saveMessage.type}`}>
                    {saveMessage.type === 'success' ? '✅' : '❌'} {saveMessage.text}
                </div>
            )}

            {/* Settings Form */}
            <div className="settings-form">
                {/* Auto-Index Setting */}
                <div className="setting-group">
                    <div className="setting-header">
                        <h3>🔄 Auto-Index</h3>
                        <label className="toggle-switch">
                            <input
                                type="checkbox"
                                checked={settings?.auto_index_enabled || false}
                                onChange={(e) => handleSettingChange('auto_index_enabled', e.target.checked)}
                            />
                            <span className="toggle-slider"></span>
                        </label>
                    </div>
                    <p className="setting-description">
                        فعال‌سازی اسکن خودکار سایت برای شناسایی گلوگاه‌ها و نقاط کور.
                        توجه: اسکن سنگین ممکن است بار سرور را افزایش دهد.
                    </p>
                    {settings?.auto_index_enabled && (
                        <div className="warning-box">
                            ⚠️ Auto-Index باید فقط در بازه‌های زمانی کم‌ترافیک اجرا شود.
                        </div>
                    )}
                </div>

                {/* Scan Interval Setting */}
                <div className="setting-group">
                    <h3>⏱️ بازه اسکن</h3>
                    <p className="setting-description">
                        فاصله زمانی بین هر اسکن (به ثانیه). حداقل: 300 ثانیه (5 دقیقه)
                    </p>
                    <div className="input-group">
                        <input
                            type="number"
                            value={settings?.scan_interval || 3600}
                            onChange={(e) => handleSettingChange('scan_interval', parseInt(e.target.value))}
                            min="300"
                            step="300"
                        />
                        <span className="input-unit">ثانیه</span>
                    </div>
                    <div className="interval-info">
                        معادل: {Math.round((settings?.scan_interval || 3600) / 60)} دقیقه
                    </div>
                </div>

                {/* Intelligence Level Setting */}
                <div className="setting-group">
                    <h3>🧠 سطح هوش اطلس</h3>
                    <p className="setting-description">
                        سطح پیچیدگی تحلیل‌ها و پیشنهادات اطلس
                    </p>
                    <div className="intelligence-options">
                        <label className={`intelligence-option ${settings?.intelligence_level === 'basic' ? 'selected' : ''}`}>
                            <input
                                type="radio"
                                name="intelligence_level"
                                value="basic"
                                checked={settings?.intelligence_level === 'basic'}
                                onChange={(e) => handleSettingChange('intelligence_level', e.target.value)}
                            />
                            <div className="option-content">
                                <strong>پایه (Basic)</strong>
                                <p>تحلیل‌های ساده و سریع</p>
                            </div>
                        </label>
                        <label className={`intelligence-option ${settings?.intelligence_level === 'standard' ? 'selected' : ''}`}>
                            <input
                                type="radio"
                                name="intelligence_level"
                                value="standard"
                                checked={settings?.intelligence_level === 'standard'}
                                onChange={(e) => handleSettingChange('intelligence_level', e.target.value)}
                            />
                            <div className="option-content">
                                <strong>استاندارد (Standard)</strong>
                                <p>توصیه شده برای اکثر سایت‌ها</p>
                            </div>
                        </label>
                        <label className={`intelligence-option ${settings?.intelligence_level === 'advanced' ? 'selected' : ''}`}>
                            <input
                                type="radio"
                                name="intelligence_level"
                                value="advanced"
                                checked={settings?.intelligence_level === 'advanced'}
                                onChange={(e) => handleSettingChange('intelligence_level', e.target.value)}
                            />
                            <div className="option-content">
                                <strong>پیشرفته (Advanced)</strong>
                                <p>تحلیل عمیق و پیشنهادات جامع</p>
                            </div>
                        </label>
                    </div>
                </div>

                {/* Alert Threshold Setting */}
                <div className="setting-group">
                    <h3>🚨 آستانه هشدار</h3>
                    <p className="setting-description">
                        حداقل امتیاز سلامت برای ارسال هشدار (0-100)
                    </p>
                    <div className="slider-container">
                        <input
                            type="range"
                            min="0"
                            max="100"
                            value={settings?.alert_threshold || 40}
                            onChange={(e) => handleSettingChange('alert_threshold', parseInt(e.target.value))}
                            className="threshold-slider"
                        />
                        <div className="slider-value">{settings?.alert_threshold || 40}</div>
                    </div>
                    <div className="threshold-guide">
                        <span>0 (همیشه هشدار)</span>
                        <span>100 (هیچ وقت هشدار نده)</span>
                    </div>
                </div>

                {/* Data Retention Setting */}
                <div className="setting-group">
                    <h3>🗄️ نگهداری داده</h3>
                    <p className="setting-description">
                        مدت زمان نگهداری داده‌های تحلیلی (روز)
                    </p>
                    <div className="input-group">
                        <input
                            type="number"
                            value={settings?.data_retention_days || 90}
                            onChange={(e) => handleSettingChange('data_retention_days', parseInt(e.target.value))}
                            min="7"
                            max="365"
                        />
                        <span className="input-unit">روز</span>
                    </div>
                    <div className="retention-info">
                        توصیه: حداقل 30 روز برای تحلیل‌های معتبر
                    </div>
                </div>
            </div>

            {/* Save Button */}
            <div className="settings-footer">
                <button
                    className="save-settings-button"
                    onClick={saveSettings}
                    disabled={isSaving}
                >
                    {isSaving ? '⏳ در حال ذخیره...' : '💾 ذخیره تنظیمات'}
                </button>
                <button
                    className="reset-button"
                    onClick={fetchSettings}
                    disabled={isSaving}
                >
                    🔄 بازگشت به حالت قبل
                </button>
            </div>

            {/* Performance Warning */}
            <div className="performance-warning">
                <h4>⚡ نکات کارایی (Performance)</h4>
                <ul>
                    <li>اسکن سنگین سایت می‌تواند بار سرور را افزایش دهد</li>
                    <li>Auto-Index را فقط در بازه‌های زمانی کم‌ترافیک فعال کنید</li>
                    <li>برای سایت‌های با ترافیک بالا، بازه اسکن را افزایش دهید</li>
                </ul>
            </div>

            {/* Data Accuracy Warning */}
            <div className="accuracy-warning">
                <h4>📊 دقت داده (Data Accuracy)</h4>
                <p>
                    در صورت کم بودن حجم نمونه (Sample Size)، اطلس هشدار می‌دهد که 
                    "داده‌ها برای تصمیم‌گیری قطعی کافی نیستند".
                </p>
            </div>
        </div>
    );
};

export default AtlasSettings;
