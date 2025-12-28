import React, { useState, useEffect } from 'react';

/**
 * Super Settings Tab - Tab 5
 * تب ۵: پیکربندی و فایروال (Settings & Shield)
 * 
 * Granular configuration matrix with nested tabs for complete control
 */
const SuperSettings = () => {
    const [activeSection, setActiveSection] = useState('core');
    const [settings, setSettings] = useState(null);
    const [hasChanges, setHasChanges] = useState(false);
    const [saving, setSaving] = useState(false);
    const [loading, setLoading] = useState(true);
    const [notification, setNotification] = useState(null);

    useEffect(() => {
        loadSettings();
    }, []);

    useEffect(() => {
        if (notification) {
            const timer = setTimeout(() => setNotification(null), 3000);
            return () => clearTimeout(timer);
        }
    }, [notification]);

    const loadSettings = async () => {
        setLoading(true);
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/settings`,
                {
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setSettings(data.data);
            }
        } catch (error) {
            console.error('Failed to load settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSaveSettings = async () => {
        setSaving(true);
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/settings`,
                {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(settings)
                }
            );
            const data = await response.json();
            if (data.success) {
                setNotification({ type: 'success', message: 'تنظیمات با موفقیت ذخیره شد' });
                setHasChanges(false);
            } else {
                setNotification({ type: 'error', message: 'خطا در ذخیره تنظیمات' });
            }
        } catch (error) {
            console.error('Failed to save settings:', error);
            setNotification({ type: 'error', message: 'خطا در ذخیره تنظیمات' });
        } finally {
            setSaving(false);
        }
    };

    const updateSetting = (section, key, value) => {
        setSettings(prev => ({
            ...prev,
            [section]: {
                ...prev[section],
                [key]: value
            }
        }));
        setHasChanges(true);
    };

    const sections = [
        { id: 'core', name: '🧠 هسته (Core)', description: 'تنظیمات مدل هوش مصنوعی و GapGPT API' },
        { id: 'visual', name: '🎨 بصری و تعاملی', description: 'ظاهر چت و رفتار تعاملی' },
        { id: 'database', name: '🗄️ دیتابیس و ایندکس', description: 'پیکربندی تابش و ایندکسگذاری' },
        { id: 'modules', name: '🔌 ماژول‌ها', description: 'فعال/غیرفعال سازی قابلیت‌ها' },
        { id: 'messages', name: '💬 بومی‌سازی', description: 'شخصی‌سازی پیام‌ها' },
        { id: 'security', name: '🛡️ امنیت', description: 'فایروال و کنترل دسترسی' }
    ];

    if (loading) {
        return (
            <div className="loading-container">
                <div className="spinner"></div>
                <p>در حال بارگذاری تنظیمات...</p>
            </div>
        );
    }

    const config = settings || {
        core: {},
        visual: {},
        database: {},
        modules: {},
        messages: {},
        security: {}
    };

    return (
        <div className="super-settings" dir="rtl">
            {/* Notification Banner */}
            {notification && (
                <div className={`notification-banner ${notification.type}`}>
                    <span>{notification.type === 'success' ? '✅' : '❌'} {notification.message}</span>
                </div>
            )}

            {/* Save Banner */}
            {hasChanges && (
                <div className="save-banner">
                    <span>⚠️ تغییراتی ذخیره نشده دارید</span>
                    <button onClick={handleSaveSettings} disabled={saving}>
                        {saving ? 'در حال ذخیره...' : '💾 ذخیره تنظیمات'}
                    </button>
                </div>
            )}

            <div className="settings-layout">
                {/* Section Navigation */}
                <div className="sections-nav">
                    {sections.map(section => (
                        <button
                            key={section.id}
                            className={`section-btn ${activeSection === section.id ? 'active' : ''}`}
                            onClick={() => setActiveSection(section.id)}
                        >
                            <div className="section-name">{section.name}</div>
                            <div className="section-desc">{section.description}</div>
                        </button>
                    ))}
                </div>

                {/* Settings Content */}
                <div className="settings-content">
                    {activeSection === 'core' && (
                        <div className="settings-section">
                            <h2>🧠 تنظیمات هسته (Core Configuration)</h2>
                            
                            <div className="setting-group">
                                <h3>GapGPT API</h3>
                                <div className="notice-box info">
                                    <p>
                                        <strong>GapGPT</strong> - دروازه یکپارچه به مدل‌های هوش مصنوعی<br/>
                                        <small>دسترسی به مدل‌های متنوع از OpenAI، Google Gemini، Anthropic Claude، DeepSeek، XAI و بیشتر</small>
                                    </p>
                                </div>
                                <div className="setting-item">
                                    <label>مدل هوش مصنوعی:</label>
                                    <select 
                                        value={config.core.model || 'gemini-2.5-flash'}
                                        onChange={(e) => updateSetting('core', 'model', e.target.value)}
                                    >
                                        <optgroup label="Google Gemini">
                                            <option value="gemini-2.5-flash">Gemini 2.5 Flash (توصیه شده)</option>
                                            <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                                            <option value="gemini-2.0-flash">Gemini 2.0 Flash</option>
                                            <option value="gemini-3-pro-preview">Gemini 3 Pro Preview</option>
                                        </optgroup>
                                        <optgroup label="OpenAI">
                                            <option value="gpt-4o">GPT-4o</option>
                                            <option value="gpt-4o-mini">GPT-4o Mini</option>
                                            <option value="o1">O1</option>
                                            <option value="o1-mini">O1 Mini</option>
                                            <option value="gpt-5">GPT-5</option>
                                        </optgroup>
                                        <optgroup label="Anthropic Claude">
                                            <option value="claude-opus-4-5-20251101">Claude Opus 4.5</option>
                                        </optgroup>
                                        <optgroup label="DeepSeek">
                                            <option value="deepseek-chat">DeepSeek Chat</option>
                                            <option value="deepseek-reasoner">DeepSeek Reasoner</option>
                                        </optgroup>
                                        <optgroup label="XAI">
                                            <option value="grok-3">Grok 3</option>
                                            <option value="grok-3-mini">Grok 3 Mini</option>
                                        </optgroup>
                                    </select>
                                    <small className="description">
                                        انتخاب مدل بر اساس نیاز به سرعت یا دقت. 
                                        <a href="https://gapgpt.app/models" target="_blank">مشاهده قیمت‌ها →</a>
                                    </small>
                                </div>
                                <div className="setting-item">
                                    <label>محدودیت توکن در هر ریکوئست:</label>
                                    <input 
                                        type="number" 
                                        value={config.core.max_tokens || 2048}
                                        onChange={(e) => updateSetting('core', 'max_tokens', parseInt(e.target.value))}
                                        min="512"
                                        max="8192"
                                    />
                                </div>
                                <div className="setting-item">
                                    <label>Temperature (خلاقیت):</label>
                                    <input 
                                        type="range" 
                                        min="0" 
                                        max="2" 
                                        step="0.1"
                                        value={config.core.temperature || 0.7}
                                        onChange={(e) => updateSetting('core', 'temperature', parseFloat(e.target.value))}
                                    />
                                    <span className="range-value">{config.core.temperature || 0.7}</span>
                                </div>
                            </div>
                        </div>
                    )}

                    {activeSection === 'visual' && (
                        <div className="settings-section">
                            <h2>🎨 تنظیمات بصری و تعاملی</h2>
                            
                            <div className="setting-group">
                                <h3>ظاهر چت</h3>
                                <div className="setting-item">
                                    <label>رنگ اصلی:</label>
                                    <input 
                                        type="color" 
                                        value={config.visual.primary_color || '#667eea'}
                                        onChange={(e) => updateSetting('visual', 'primary_color', e.target.value)}
                                    />
                                </div>
                                <div className="setting-item">
                                    <label>آیکون چت:</label>
                                    <select 
                                        value={config.visual.chat_icon || 'default'}
                                        onChange={(e) => updateSetting('visual', 'chat_icon', e.target.value)}
                                    >
                                        <option value="default">پیش‌فرض</option>
                                        <option value="robot">ربات</option>
                                        <option value="avatar">آواتار</option>
                                    </select>
                                </div>
                                <div className="setting-item">
                                    <label>سرعت اسکرول خودکار:</label>
                                    <input 
                                        type="range" 
                                        min="0" 
                                        max="1000" 
                                        step="100"
                                        value={config.visual.scroll_speed || 300}
                                        onChange={(e) => updateSetting('visual', 'scroll_speed', parseInt(e.target.value))}
                                    />
                                    <span className="range-value">{config.visual.scroll_speed || 300}ms</span>
                                </div>
                                <div className="setting-item">
                                    <label>شدت هایلایت:</label>
                                    <input 
                                        type="range" 
                                        min="0" 
                                        max="100" 
                                        step="10"
                                        value={config.visual.highlight_intensity || 50}
                                        onChange={(e) => updateSetting('visual', 'highlight_intensity', parseInt(e.target.value))}
                                    />
                                    <span className="range-value">{config.visual.highlight_intensity || 50}%</span>
                                </div>
                            </div>
                        </div>
                    )}

                    {activeSection === 'database' && (
                        <div className="settings-section">
                            <h2>🗄️ پیکربندی دیتابیس و ایندکس</h2>
                            
                            <div className="setting-group">
                                <h3>مخزن تابش</h3>
                                <div className="setting-item">
                                    <label>جداول هدف (با کاما جدا کنید):</label>
                                    <input 
                                        type="text" 
                                        value={(config.database.target_tables || []).join(', ')}
                                        onChange={(e) => updateSetting('database', 'target_tables', e.target.value.split(',').map(t => t.trim()))}
                                        placeholder="posts, products, pages"
                                    />
                                </div>
                                <div className="setting-item">
                                    <label>فواصل زمانی اسکن خودکار (دقیقه):</label>
                                    <input 
                                        type="number" 
                                        value={config.database.scan_interval || 60}
                                        onChange={(e) => updateSetting('database', 'scan_interval', parseInt(e.target.value))}
                                        min="10"
                                        max="1440"
                                    />
                                </div>
                                <div className="setting-item">
                                    <label>دسته‌های حذف شده از ایندکس:</label>
                                    <input 
                                        type="text" 
                                        value={(config.database.excluded_categories || []).join(', ')}
                                        onChange={(e) => updateSetting('database', 'excluded_categories', e.target.value.split(',').map(c => c.trim()))}
                                        placeholder="draft, private"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {activeSection === 'modules' && (
                        <div className="settings-section">
                            <h2>🔌 ماژولار سازی قابلیت‌ها</h2>
                            
                            <div className="modules-grid">
                                <div className="module-card">
                                    <h3>🛡️ فایروال (WAF)</h3>
                                    <label className="toggle-switch">
                                        <input 
                                            type="checkbox" 
                                            checked={config.modules.waf_enabled || false}
                                            onChange={(e) => updateSetting('modules', 'waf_enabled', e.target.checked)}
                                        />
                                        <span className="slider"></span>
                                    </label>
                                </div>
                                <div className="module-card">
                                    <h3>🔐 سیستم OTP</h3>
                                    <label className="toggle-switch">
                                        <input 
                                            type="checkbox" 
                                            checked={config.modules.otp_enabled || false}
                                            onChange={(e) => updateSetting('modules', 'otp_enabled', e.target.checked)}
                                        />
                                        <span className="slider"></span>
                                    </label>
                                </div>
                                <div className="module-card">
                                    <h3>🌐 ترجمه عربی</h3>
                                    <label className="toggle-switch">
                                        <input 
                                            type="checkbox" 
                                            checked={config.modules.arabic_translation || false}
                                            onChange={(e) => updateSetting('modules', 'arabic_translation', e.target.checked)}
                                        />
                                        <span className="slider"></span>
                                    </label>
                                </div>
                                <div className="module-card">
                                    <h3>📦 استعلام سفارش</h3>
                                    <label className="toggle-switch">
                                        <input 
                                            type="checkbox" 
                                            checked={config.modules.order_tracking || false}
                                            onChange={(e) => updateSetting('modules', 'order_tracking', e.target.checked)}
                                        />
                                        <span className="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    )}

                    {activeSection === 'messages' && (
                        <div className="settings-section">
                            <h2>💬 بومی‌سازی و پیام‌ها</h2>
                            
                            <div className="setting-group">
                                <h3>پیام‌های سیستم</h3>
                                <div className="setting-item">
                                    <label>خوشآمدگویی لیدها:</label>
                                    <textarea 
                                        value={config.messages.welcome_lead || ''}
                                        onChange={(e) => updateSetting('messages', 'welcome_lead', e.target.value)}
                                        rows={3}
                                        placeholder="سلام! به هما خوش آمدید..."
                                    />
                                </div>
                                <div className="setting-item">
                                    <label>هشدار فایروال:</label>
                                    <textarea 
                                        value={config.messages.firewall_warning || ''}
                                        onChange={(e) => updateSetting('messages', 'firewall_warning', e.target.value)}
                                        rows={2}
                                        placeholder="دسترسی شما محدود شده است..."
                                    />
                                </div>
                                <div className="setting-item">
                                    <label>متن پیامک OTP:</label>
                                    <textarea 
                                        value={config.messages.otp_sms || ''}
                                        onChange={(e) => updateSetting('messages', 'otp_sms', e.target.value)}
                                        rows={2}
                                        placeholder="کد تایید شما: {code}"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {activeSection === 'security' && (
                        <div className="settings-section">
                            <h2>🛡️ امنیت و فایروال</h2>
                            
                            <div className="setting-group">
                                <h3>تنظیمات فایروال</h3>
                                <div className="setting-item">
                                    <label>حساسیت:</label>
                                    <select 
                                        value={config.security.sensitivity || 'medium'}
                                        onChange={(e) => updateSetting('security', 'sensitivity', e.target.value)}
                                    >
                                        <option value="low">کم (سازگار)</option>
                                        <option value="medium">متوسط (توصیه شده)</option>
                                        <option value="high">بالا (سختگیرانه)</option>
                                    </select>
                                </div>
                                <div className="setting-item">
                                    <label>حد آستانه امتیاز امنیتی برای مسدودسازی:</label>
                                    <input 
                                        type="number" 
                                        value={config.security.block_threshold || 30}
                                        onChange={(e) => updateSetting('security', 'block_threshold', parseInt(e.target.value))}
                                        min="0"
                                        max="100"
                                    />
                                </div>
                                <div className="setting-item">
                                    <label>مدت زمان مسدودسازی IP (ساعت):</label>
                                    <input 
                                        type="number" 
                                        value={config.security.block_duration || 24}
                                        onChange={(e) => updateSetting('security', 'block_duration', parseInt(e.target.value))}
                                        min="1"
                                        max="720"
                                    />
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <style jsx>{`
                .super-settings {
                    padding: 20px;
                }

                .notification-banner {
                    position: fixed;
                    top: 80px;
                    left: 50%;
                    transform: translateX(-50%);
                    padding: 15px 30px;
                    border-radius: 8px;
                    font-weight: 600;
                    z-index: 1000;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
                    animation: slideDown 0.3s ease-out;
                }

                .notification-banner.success {
                    background: #d4edda;
                    border: 1px solid #c3e6cb;
                    color: #155724;
                }

                .notification-banner.error {
                    background: #f8d7da;
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                }

                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translate(-50%, -20px);
                    }
                    to {
                        opacity: 1;
                        transform: translate(-50%, 0);
                    }
                }

                .save-banner {
                    position: sticky;
                    top: 0;
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 8px;
                    padding: 15px 20px;
                    margin-bottom: 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    z-index: 100;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                }

                .save-banner button {
                    padding: 8px 20px;
                    background: #2ecc71;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                }

                .settings-layout {
                    display: grid;
                    grid-template-columns: 280px 1fr;
                    gap: 20px;
                }

                .sections-nav {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .section-btn {
                    padding: 15px;
                    background: white;
                    border: 2px solid #e0e0e0;
                    border-radius: 8px;
                    cursor: pointer;
                    text-align: right;
                    transition: all 0.3s;
                }

                .section-btn:hover {
                    border-color: #667eea;
                    transform: translateX(-5px);
                }

                .section-btn.active {
                    background: #667eea;
                    border-color: #667eea;
                }

                .section-btn.active .section-name {
                    color: white;
                }

                .section-btn.active .section-desc {
                    color: rgba(255, 255, 255, 0.8);
                }

                .section-name {
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 5px;
                }

                .section-desc {
                    font-size: 12px;
                    color: #666;
                }

                .settings-content {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 30px;
                }

                .settings-section h2 {
                    margin: 0 0 25px 0;
                    color: #333;
                    border-bottom: 2px solid #f0f0f0;
                    padding-bottom: 15px;
                }

                .setting-group {
                    margin-bottom: 30px;
                }

                .setting-group h3 {
                    margin: 0 0 15px 0;
                    color: #667eea;
                    font-size: 16px;
                }

                .setting-item {
                    margin-bottom: 20px;
                    display: grid;
                    grid-template-columns: 250px 1fr;
                    align-items: center;
                    gap: 20px;
                }

                .setting-item label {
                    font-weight: 600;
                    color: #333;
                }

                .setting-item input[type="text"],
                .setting-item input[type="number"],
                .setting-item select,
                .setting-item textarea {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    font-size: 14px;
                }

                .setting-item input[type="color"] {
                    width: 60px;
                    height: 40px;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                }

                .setting-item input[type="range"] {
                    flex: 1;
                }

                .range-value {
                    margin-right: 10px;
                    font-weight: 600;
                    color: #667eea;
                    min-width: 60px;
                }

                .modules-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                }

                .module-card {
                    padding: 20px;
                    background: #f9f9f9;
                    border: 1px solid #e0e0e0;
                    border-radius: 8px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .module-card h3 {
                    margin: 0;
                    font-size: 14px;
                    color: #333;
                }

                .toggle-switch {
                    position: relative;
                    display: inline-block;
                    width: 60px;
                    height: 30px;
                }

                .toggle-switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }

                .slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .4s;
                    border-radius: 30px;
                }

                .slider:before {
                    position: absolute;
                    content: "";
                    height: 22px;
                    width: 22px;
                    right: 4px;
                    bottom: 4px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }

                input:checked + .slider {
                    background-color: #2ecc71;
                }

                input:checked + .slider:before {
                    transform: translateX(-30px);
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
            `}</style>
        </div>
    );
};

export default SuperSettings;
