# PR15 Implementation Details

## پیادهسازی کامل تشخیص هویت چندسطحی و شناسایی مهاجم

**نسخه**: 1.0.0  
**تاریخ**: 2025-12-26  
**وضعیت**: ✅ Complete

---

## 📋 فهرست پیادهسازی

### Feature: پیادهسازی واحد «تشخیص هویت چندسطحی» (Multi-Role Intelligence) و سیستم شناسایی خودکار نقش «مهاجم» (Intruder Detection)

هما به متادیتای کاربران وردپرس متصل می‌شود تا رفتار خود را بر اساس نقش کاربر تنظیم کند. چالش اصلی در اینجا، تشخیص «کاربر مهاجم» (Attacker/Intruder) است که هنوز لاگین نکرده اما رفتارهای مشکوک دارد.

---

## 🎯 اهداف استراتژیک

این PR چهار بخش اصلی را پیاده‌سازی می‌کند:

1. **تشخیص نقش کاربر** - شناسایی خودکار نقش (مدیر، مشتری، میهمان، مهاجم)
2. **شناسایی مهاجم** - تشخیص رفتارهای مشکوک و حملات
3. **کنترل دسترسی پویا** - محدود کردن یا گسترش قابلیت‌های چت بر اساس نقش
4. **هشدارهای امنیتی** - اطلاع‌رسانی آنی به مدیر در صورت تشخیص مهاجم

---

## 🔑 قابلیت‌های کلیدی

### الف) تفکیک رابط کاربری بر اساس نقش (Contextual UI)

- **مدیر (Administrator)**: نمایش آمارهای فروش، وضعیت سرور و میانبرهای کنترلی اطلس در چت
- **مشتری (Customer)**: نمایش وضعیت سفارشات، دکمه‌های تمدید فاکتور و پیگیری مرسوله
- **میهمان (Guest)**: تمرکز بر جذب، معرفی خدمات چاپکو و هدایت به سمت ثبت‌نام (OTP)
- **مهاجم (Intruder)**: دسترسی مسدود، نمایش هشدار امنیتی

### ب) الگوریتم تشخیص مهاجم (Intruder Detection System - IDS)

هما به صورت اتوماتیک کاربر را «مهاجم» تشخیص می‌دهد اگر:

1. تلاش برای دسترسی به آدرس‌های حساس (wp-config.php, .env, wp-admin بدون مجوز)
2. استفاده از کلمات کلیدی تزریق SQL یا Script در ورودی‌های چت
3. اسکن سریع صفحات سایت در بازه زمانی کوتاه (بیش از 20 درخواست در 60 ثانیه)
4. استفاده از User Agent مشکوک (sqlmap, nikto, nmap و...)

### ج) کنترل دسترسی پویا (ACL)

تنظیمات در افزونه برای مشخص کردن اینکه هر نقش کاربری، چه «ابزارهایی» از هما را می‌تواند ببیند:

```php
'admin' => [
    'tools' => ['analytics', 'sales_report', 'user_management', 'atlas_shortcuts', 'security_monitor'],
    'features' => ['advanced_chat', 'intervention', 'export_data', 'system_settings'],
],
'customer' => [
    'tools' => ['order_tracker', 'invoice_renewal', 'shipping_tracker', 'support_ticket'],
    'features' => ['basic_chat', 'order_history', 'account_info'],
],
'guest' => [
    'tools' => ['product_explorer', 'service_info', 'otp_registration'],
    'features' => ['basic_chat', 'lead_capture', 'guided_tour'],
],
'intruder' => [
    'tools' => [],
    'features' => ['warning_display'],
]
```

---

## 📦 ساختار فایل‌ها

### فایل‌های PHP جدید:

1. **HT_User_Role_Resolver.php** (8,727 bytes)
   - تشخیص نقش کاربر لاگین شده و میهمان
   - تولید توکن امنیتی متناسب با نقش
   - بررسی دسترسی‌ها (capabilities)

2. **HT_Intruder_Pattern_Matcher.php** (10,544 bytes)
   - لیست سیاه (Blacklist) فایل‌های حساس و الگوهای مشکوک
   - سیستم امتیازدهی (Scoring) برای جلوگیری از False Positive
   - تشخیص اسکن سریع و User Agent مخرب

3. **HT_Dynamic_Chat_Capabilities.php** (12,986 bytes)
   - نقشه دسترسی‌ها (Capabilities Map)
   - فیلتر کردن پاسخ‌های AI بر اساس نقش
   - REST API برای دریافت ابزارها و دسترسی‌ها

4. **HT_Admin_Security_Alerts.php** (13,348 bytes)
   - جدول امنیتی `wp_homa_security_log`
   - اطلاع‌رسانی real-time به مدیر
   - AJAX handlers برای مشاهده و نادیده گرفتن هشدارها

### فایل‌های React جدید:

5. **AdminTools.jsx** (2,881 bytes)
   - نمایش ابزارهای مدیریتی
   - دکمه‌های آمار، کاربران آنلاین، هشدارهای امنیتی
   - میانبر به داشبورد اطلس

6. **OrderTracker.jsx** (5,198 bytes)
   - نمایش سفارشات مشتری
   - دکمه‌های پیگیری و تمدید فاکتور
   - ایجاد تیکت پشتیبانی

7. **SecurityWarning.jsx** (2,260 bytes)
   - نمایش هشدار امنیتی برای مهاجمین
   - دکمه تماس با پشتیبانی
   - توضیحات دلیل مسدود شدن

8. **LeadGenerator.jsx** (3,689 bytes)
   - معرفی خدمات چاپکو برای میهمانان
   - دکمه‌های کاوش، محاسبه تیراژ
   - فرم ثبت‌نام سریع با OTP

### فایل‌های CSS:

9. **homa-role-ui.css** (12,156 bytes)
   - استایل‌های Role Badge
   - طراحی Admin Tools
   - طراحی Order Tracker
   - طراحی Security Warning
   - طراحی Lead Generator
   - انیمیشن‌ها و Responsive Design

### فایل‌های مستندات و تست:

10. **validate-pr15.html** (17,277 bytes)
    - صفحه تست قابلیت‌های PR15
    - تست تشخیص نقش، ابزارهای موجود
    - شبیه‌سازی حمله و تست هشدارهای امنیتی

11. **PR15-IMPLEMENTATION.md** (این فایل)
12. **PR15-QUICKSTART.md** - راهنمای سریع
13. **PR15-README.md** - مستندات کامل
14. **PR15-SUMMARY.md** - خلاصه تغییرات

---

## 🔧 تغییرات در فایل‌های موجود

### HT_Core.php
```php
// Add new service properties
public ?HT_User_Role_Resolver $role_resolver = null;
public ?HT_Intruder_Pattern_Matcher $intruder_detector = null;
public ?HT_Dynamic_Chat_Capabilities $chat_capabilities = null;
public ?HT_Admin_Security_Alerts $security_alerts = null;

// Initialize services in init_services()
$this->role_resolver = new HT_User_Role_Resolver();
$this->intruder_detector = new HT_Intruder_Pattern_Matcher();
$this->chat_capabilities = new HT_Dynamic_Chat_Capabilities();
$this->security_alerts = new HT_Admin_Security_Alerts();

// Register REST endpoints
add_action('rest_api_init', [$this->chat_capabilities, 'register_endpoints']);
add_action('rest_api_init', [$this->security_alerts, 'register_endpoints']);

// Schedule security log cleanup
if (!wp_next_scheduled('homa_cleanup_security_logs')) {
    wp_schedule_event(time(), 'weekly', 'homa_cleanup_security_logs');
}
```

### HT_AI_Controller.php
```php
// Get user role context
$role_resolver = HT_Core::instance()->role_resolver;
$user_role_context = $role_resolver->get_homa_user_context();

// Check if user is blocked (intruder)
if (isset($user_role_context['blocked']) && $user_role_context['blocked']) {
    return new \WP_REST_Response([
        'success' => false,
        'response' => 'دسترسی شما محدود شده است.',
        'blocked' => true,
    ], 403);
}

// Add role context to user context
$user_context['user_role_context'] = $user_role_context;

// Filter response based on capabilities
$chat_capabilities = HT_Core::instance()->chat_capabilities;
$result = $chat_capabilities->filter_ai_response($result, $user_role_context);
```

### HT_Parallel_UI.php
```php
// Enqueue role-based UI CSS
wp_enqueue_style(
    'homa-role-ui',
    HT_PLUGIN_URL . 'assets/css/homa-role-ui.css',
    ['homa-parallel-ui'],
    HT_VERSION
);
```

### HomaSidebar.jsx
```jsx
// Import role-based components
import AdminTools from './AdminTools';
import OrderTracker from './OrderTracker';
import SecurityWarning from './SecurityWarning';
import LeadGenerator from './LeadGenerator';

// Add state for role context
const [userRoleContext, setUserRoleContext] = useState(null);

// Fetch user role context on mount
const fetchUserRoleContext = async () => {
    const response = await fetch('/wp-json/homaye-tabesh/v1/capabilities/context');
    const data = await response.json();
    if (data.success) {
        setUserRoleContext(data.context);
    }
};

// Render role-based tools
const renderRoleBasedTools = () => {
    switch (userRoleContext.role) {
        case 'admin': return <AdminTools userContext={userRoleContext} />;
        case 'customer': return <OrderTracker userContext={userRoleContext} />;
        case 'intruder': return <SecurityWarning />;
        case 'guest':
        default: return <LeadGenerator userContext={userRoleContext} />;
    }
};
```

---

## 🔌 REST API Endpoints

### 1. دریافت متناسب نقش کاربر
```
GET /wp-json/homaye-tabesh/v1/capabilities/context
```

**پاسخ:**
```json
{
  "success": true,
  "context": {
    "role": "customer",
    "identity": "علی احمدی",
    "user_id": 5,
    "capabilities": ["view_orders", "track_shipments", "use_chat"],
    "security_token": "..."
  },
  "tools": [...],
  "features": [...],
  "welcome_message": "سلام علی عزیز! ...",
  "suggested_actions": [...]
}
```

### 2. دریافت ابزارهای موجود
```
GET /wp-json/homaye-tabesh/v1/capabilities/tools
```

### 3. دریافت قابلیت‌های موجود
```
GET /wp-json/homaye-tabesh/v1/capabilities/features
```

### 4. دریافت هشدارهای امنیتی (فقط مدیر)
```
GET /wp-json/homaye-tabesh/v1/security/alerts?limit=20&undismissed_only=true
```

### 5. دریافت آمار امنیتی (فقط مدیر)
```
GET /wp-json/homaye-tabesh/v1/security/statistics?period=today
```

---

## 🗄️ ساختار دیتابیس

### جدول `wp_homa_security_log`

```sql
CREATE TABLE wp_homa_security_log (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type varchar(50) NOT NULL,
    ip_address varchar(45) NOT NULL,
    user_agent text,
    request_uri text,
    detection_reason text,
    severity varchar(20) DEFAULT 'medium',
    dismissed tinyint(1) DEFAULT 0,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY event_type (event_type),
    KEY ip_address (ip_address),
    KEY dismissed (dismissed),
    KEY created_at (created_at)
);
```

---

## 🧪 تست و اعتبارسنجی

### 1. تست ادمین
- با اکانت مدیریت وارد شوید
- چک کنید آیا هما گزینه‌های مدیریتی اطلس را نشان می‌دهد؟
- آیا می‌توانید هشدارهای امنیتی را ببینید؟

### 2. تست مشتری
- با اکانت مشتری وارد شوید
- آیا می‌توانید سفارشات خود را ببینید؟
- دکمه‌های پیگیری و تمدید کار می‌کنند؟

### 3. تست میهمان
- در حالت لاگ‌اوت
- آیا پیام خوش‌آمدگویی و دکمه ثبت‌نام نمایش داده می‌شود؟

### 4. تست مهاجم
- سعی کنید به `/wp-config.php` دسترسی پیدا کنید
- یا کد مشکوک در چت وارد کنید: `<script>alert()</script>`
- آیا هما دسترسی شما را محدود می‌کند؟

### 5. تست گزارش
- در داشبورد اطلس بررسی کنید
- آیا لیست کاربران آنلاین به تفکیک نقش نمایش داده می‌شود؟

---

## ⚠️ ریسک‌ها و ملاحظات

### False Positive
- **مشکل**: ممکن است یک کاربر عادی به اشتباه مهاجم تشخیص داده شود
- **راهکار**: استفاده از سیستم امتیازدهی (Scoring) با آستانه 100 امتیاز
- **پیشگیری**: افزودن IP به whitelist برای کاربران خاص

### Performance
- **مشکل**: چک کردن مداوم نقش نباید باعث لگ چت شود
- **راهکار**: ذخیره اطلاعات نقش در Transient و استفاده از Cache
- **بهینه‌سازی**: فقط یکبار در هر سشن نقش را بررسی می‌کنیم

---

## 📊 آمار پیاده‌سازی

- **تعداد فایل‌های جدید**: 14 فایل
- **تعداد خطوط کد PHP**: ~45,000 bytes
- **تعداد خطوط کد React**: ~14,000 bytes
- **تعداد خطوط CSS**: ~12,000 bytes
- **تعداد REST API Endpoints**: 5 endpoint جدید
- **جداول دیتابیس**: 1 جدول جدید

---

## 🚀 نحوه استفاده

### برای توسعه‌دهندگان:

```php
// دریافت نقش کاربر جاری
$role_resolver = HT_Core::instance()->role_resolver;
$context = $role_resolver->get_homa_user_context();

// بررسی دسترسی
if ($role_resolver->user_has_capability('view_analytics', $context)) {
    // نمایش آمار
}

// شناسایی مهاجم
$intruder_detector = HT_Core::instance()->intruder_detector;
if ($intruder_detector->is_suspicious_behavior()) {
    // ثبت رویداد امنیتی
}
```

### برای مدیران:

1. به داشبورد اطلس بروید
2. بخش "هشدارهای امنیتی" را مشاهده کنید
3. می‌توانید هشدارها را نادیده بگیرید یا IP را به whitelist اضافه کنید

---

## 📚 منابع و مراجع

- [WordPress User Roles](https://wordpress.org/support/article/roles-and-capabilities/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Intrusion Detection System](https://en.wikipedia.org/wiki/Intrusion_detection_system)

---

## 👥 مشارکت‌کنندگان

- **توسعه‌دهنده اصلی**: Tabshhh4
- **تاریخ شروع**: 2025-12-26
- **تاریخ اتمام**: 2025-12-26

---

## 📝 یادداشت‌های نسخه

**نسخه 1.0.0** (2025-12-26)
- افزودن تشخیص خودکار نقش کاربر
- پیاده‌سازی سیستم شناسایی مهاجم
- کنترل دسترسی پویا در چت
- هشدارهای امنیتی به مدیر
- رابط کاربری متناسب با نقش

---

**آخرین بروزرسانی**: 2025-12-26  
**وضعیت**: Production Ready ✅
