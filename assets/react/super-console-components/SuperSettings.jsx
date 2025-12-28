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
        { id: 'modules', name: '🔌 ماژول‌های قدیمی', description: 'فعال/غیرفعال سازی قابلیت‌ها (Legacy)' },
        { id: 'enabled_modules', name: '📦 ماژول‌های کامل', description: 'مدیریت تمام ماژول‌ها' },
        { id: 'otp', name: '📲 پنل ملی OTP', description: 'تنظیمات MeliPayamak و کد یکبار مصرف' },
        { id: 'localization', name: '🌐 بومی‌سازی', description: 'تنظیمات زبان و ترجمه' },
        { id: 'firewall', name: '🔥 فایروال پیشرفته', description: 'WAF و کنترل دسترسی IP' },
        { id: 'messages', name: '💬 پیام‌ها', description: 'شخصی‌سازی پیام‌ها' },
        { id: 'security', name: '🛡️ امنیت عمومی', description: 'کنترل دسترسی و امنیت کلی' }
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
        enabled_modules: [],
        otp: {},
        localization: {},
        firewall: {},
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

                    {activeSection === 'enabled_modules' && (
                        <div className="settings-section">
                            <h2>📦 ماژولار سازی قابلیت‌ها</h2>
                            <p className="description">فعال یا غیرفعال کردن ماژول‌های مختلف سیستم</p>
                            
                            <div className="setting-group">
                                {[
                                    { id: 'chat_widget', name: 'ویجت چت', icon: '💬' },
                                    { id: 'behavior_tracking', name: 'ردیابی رفتار', icon: '👁️' },
                                    { id: 'persona_engine', name: 'موتور پرسونا', icon: '🎭' },
                                    { id: 'knowledge_base', name: 'پایگاه دانش', icon: '📚' },
                                    { id: 'security_center', name: 'مرکز امنیت', icon: '🛡️' },
                                    { id: 'atlas_dashboard', name: 'داشبورد اطلس', icon: '🗺️' },
                                    { id: 'global_observer', name: 'ناظر کل', icon: '🔍' },
                                    { id: 'live_intervention', name: 'مداخله زنده', icon: '🎯' },
                                    { id: 'conversion_triggers', name: 'محرک‌های تبدیل', icon: '⚡' },
                                    { id: 'form_hydration', name: 'پر کردن فرم', icon: '📝' },
                                    { id: 'offer_display', name: 'نمایش پیشنهادات', icon: '💎' },
                                    { id: 'visual_guidance', name: 'راهنمای بصری', icon: '🎯' },
                                    { id: 'tour_manager', name: 'مدیریت تور', icon: '🚶' }
                                ].map(module => {
                                    const enabledModules = config.enabled_modules || [];
                                    const isEnabled = enabledModules.includes(module.id);
                                    
                                    return (
                                        <div key={module.id} className="setting-item checkbox-item">
                                            <label>
                                                <input 
                                                    type="checkbox" 
                                                    checked={isEnabled}
                                                    onChange={(e) => {
                                                        const newModules = e.target.checked 
                                                            ? [...enabledModules, module.id]
                                                            : enabledModules.filter(m => m !== module.id);
                                                        setSettings(prev => ({
                                                            ...prev,
                                                            enabled_modules: newModules
                                                        }));
                                                        setHasChanges(true);
                                                    }}
                                                />
                                                <span className="module-label">
                                                    <span className="module-icon">{module.icon}</span>
                                                    <span className="module-name">{module.name}</span>
                                                </span>
                                            </label>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {activeSection === 'otp' && (
                        <div className="settings-section">
                            <h2>📲 تنظیمات پنل ملی پیامک OTP</h2>
                            <p className="description">پیکربندی MeliPayamak برای ارسال کد یکبار مصرف</p>
                            
                            <div className="setting-group">
                                <h3>اطلاعات حساب MeliPayamak</h3>
                                <div className="setting-item">
                                    <label>نام کاربری:</label>
                                    <input 
                                        type="text" 
                                        value={config.otp?.melipayamak_username || ''}
                                        onChange={(e) => updateSetting('otp', 'melipayamak_username', e.target.value)}
                                        placeholder="username@melipayamak.com"
                                    />
                                </div>
                                <div className="setting-item">
                                    <label>رمز عبور:</label>
                                    <input 
                                        type="password" 
                                        value={config.otp?.melipayamak_password || ''}
                                        onChange={(e) => updateSetting('otp', 'melipayamak_password', e.target.value)}
                                        placeholder="********"
                                    />
                                </div>
                                <div className="setting-item">
                                    <label>شماره ارسال کننده:</label>
                                    <input 
                                        type="text" 
                                        value={config.otp?.melipayamak_from_number || ''}
                                        onChange={(e) => updateSetting('otp', 'melipayamak_from_number', e.target.value)}
                                        placeholder="50002710xxx"
                                    />
                                </div>
                            </div>

                            <div className="setting-group">
                                <h3>تنظیمات OTP</h3>
                                <div className="setting-item checkbox-item">
                                    <label>
                                        <input 
                                            type="checkbox" 
                                            checked={config.otp?.otp_enabled || false}
                                            onChange={(e) => updateSetting('otp', 'otp_enabled', e.target.checked)}
                                        />
                                        <span>فعال‌سازی OTP</span>
                                    </label>
                                </div>
                                <div className="setting-item">
                                    <label>مدت اعتبار کد (دقیقه):</label>
                                    <input 
                                        type="number" 
                                        value={config.otp?.otp_expiry_minutes || 5}
                                        onChange={(e) => updateSetting('otp', 'otp_expiry_minutes', parseInt(e.target.value))}
                                        min="1"
                                        max="30"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {activeSection === 'localization' && (
                        <div className="settings-section">
                            <h2>🌐 بومی‌سازی و زبان</h2>
                            
                            <div className="setting-group">
                                <div className="setting-item">
                                    <label>زبان پیش‌فرض:</label>
                                    <select 
                                        value={config.localization?.locale || 'fa_IR'}
                                        onChange={(e) => updateSetting('localization', 'locale', e.target.value)}
                                    >
                                        <option value="fa_IR">فارسی (Farsi)</option>
                                        <option value="ar">عربی (Arabic)</option>
                                        <option value="en_US">انگلیسی (English)</option>
                                    </select>
                                </div>
                                <div className="setting-item checkbox-item">
                                    <label>
                                        <input 
                                            type="checkbox" 
                                            checked={config.localization?.rtl_enabled !== false}
                                            onChange={(e) => updateSetting('localization', 'rtl_enabled', e.target.checked)}
                                        />
                                        <span>فعال‌سازی RTL</span>
                                    </label>
                                </div>
                                <div className="setting-item checkbox-item">
                                    <label>
                                        <input 
                                            type="checkbox" 
                                            checked={config.localization?.translation_enabled !== false}
                                            onChange={(e) => updateSetting('localization', 'translation_enabled', e.target.checked)}
                                        />
                                        <span>ترجمه خودکار به عربی</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    )}

                    {activeSection === 'firewall' && (
                        <div className="settings-section">
                            <h2>🔥 فایروال پیشرفته (WAF)</h2>
                            <p className="description">تنظیمات Web Application Firewall</p>
                            
                            <div className="setting-group">
                                <h3>تنظیمات عمومی</h3>
                                <div className="setting-item checkbox-item">
                                    <label>
                                        <input 
                                            type="checkbox" 
                                            checked={config.firewall?.waf_enabled !== false}
                                            onChange={(e) => updateSetting('firewall', 'waf_enabled', e.target.checked)}
                                        />
                                        <span>فعال‌سازی WAF</span>
                                    </label>
                                </div>
                                <div className="setting-item">
                                    <label>سطح حساسیت:</label>
                                    <select 
                                        value={config.firewall?.waf_sensitivity || 'medium'}
                                        onChange={(e) => updateSetting('firewall', 'waf_sensitivity', e.target.value)}
                                    >
                                        <option value="low">کم - سازگار با همه</option>
                                        <option value="medium">متوسط - توصیه شده</option>
                                        <option value="high">بالا - سختگیرانه</option>
                                    </select>
                                </div>
                                <div className="setting-item">
                                    <label>محدودیت درخواست (Rate Limit):</label>
                                    <input 
                                        type="number" 
                                        value={config.firewall?.waf_rate_limit || 100}
                                        onChange={(e) => updateSetting('firewall', 'waf_rate_limit', parseInt(e.target.value))}
                                        min="10"
                                        max="1000"
                                        placeholder="100"
                                    />
                                    <small>تعداد درخواست مجاز در دقیقه</small>
                                </div>
                            </div>

                            <div className="setting-group">
                                <h3>لیست سفید IP (Whitelist)</h3>
                                <div className="setting-item">
                                    <label>IP های مجاز (هر کدام در یک خط):</label>
                                    <textarea 
                                        value={(config.firewall?.waf_whitelist_ips || []).join('\n')}
                                        onChange={(e) => {
                                            const ips = e.target.value.split('\n').filter(ip => ip.trim());
                                            updateSetting('firewall', 'waf_whitelist_ips', ips);
                                        }}
                                        rows={5}
                                        placeholder="192.168.1.1&#10;10.0.0.1"
                                    />
                                </div>
                            </div>

                            <div className="setting-group">
                                <h3>لیست سیاه IP (Blacklist)</h3>
                                <div className="setting-item">
                                    <label>IP های مسدود (هر کدام در یک خط):</label>
                                    <textarea 
                                        value={(config.firewall?.waf_blacklist_ips || []).join('\n')}
                                        onChange={(e) => {
                                            const ips = e.target.value.split('\n').filter(ip => ip.trim());
                                            updateSetting('firewall', 'waf_blacklist_ips', ips);
                                        }}
                                        rows={5}
                                        placeholder="1.2.3.4&#10;5.6.7.8"
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

        </div>
    );
};

export default SuperSettings;
