# راهنمای پیاده‌سازی فنی PR19: سوپر پنل هما

## 🏗️ معماری کلی

سوپر پنل هما از معماری **React SPA + WordPress REST API** استفاده می‌کند:

```
┌─────────────────────────────────────────┐
│         WordPress Admin Panel           │
│  ┌───────────────────────────────────┐  │
│  │     Homa Super Console (React)    │  │
│  │  ┌──────────┐  ┌──────────┐      │  │
│  │  │  Tab 1   │  │  Tab 2   │ ...  │  │
│  │  └──────────┘  └──────────┘      │  │
│  └───────────────────────────────────┘  │
│               ↕ REST API                │
│  ┌───────────────────────────────────┐  │
│  │  HT_Console_Analytics_API (PHP)   │  │
│  │  ┌─────────┐  ┌──────────────┐   │  │
│  │  │ System  │  │ Diagnostics  │   │  │
│  │  │ Status  │  │   Engine     │   │  │
│  │  └─────────┘  └──────────────┘   │  │
│  └───────────────────────────────────┘  │
│               ↕ Database               │
│        WordPress Database Tables       │
└─────────────────────────────────────────┘
```

---

## 📁 ساختار فایل‌ها

### Frontend Components

```
assets/react/super-console-components/
│
├── SuperConsole.jsx           # Main container with tab navigation
│   ├── State: activeTab, systemStatus
│   ├── Components: Tab buttons, Status indicator
│   └── Renders active tab component dynamically
│
├── OverviewAnalytics.jsx      # Tab 1: Dashboard
│   ├── Token usage charts
│   ├── Conversion rate circle
│   └── Interest heatmap
│
├── UserIntelligence.jsx       # Tab 2: User Management
│   ├── Users list panel
│   ├── User detail panel
│   ├── 360-degree profile
│   └── Conversation history
│
├── SystemHealth.jsx           # Tab 3: Diagnostics
│   ├── Component status cards
│   ├── Issues list
│   ├── Auto-fix functionality
│   └── Recommendations
│
├── BrainGrowth.jsx            # Tab 4: Knowledge Management
│   ├── Knowledge stats
│   ├── Facts list with filters
│   ├── Fact editor modal
│   └── Verification system
│
└── SuperSettings.jsx          # Tab 5: Configuration
    ├── Section navigation
    ├── 6 configuration sections
    ├── Form controls
    └── Save functionality
```

### Backend Classes

```php
includes/
│
├── HT_System_Diagnostics.php
│   ├── check_system_integrity()
│   ├── test_gemini_connection()
│   ├── check_tabesh_db_bridge()
│   ├── get_index_health_score()
│   ├── identify_issues()
│   └── auto_fix_issues()
│
└── HT_Console_Analytics_API.php
    ├── register_routes()
    ├── get_system_status()
    ├── get_analytics_data()
    ├── get_users_list()
    ├── get_user_details()
    ├── run_diagnostics()
    ├── get_knowledge_stats()
    ├── get_knowledge_facts()
    ├── update_fact()
    ├── delete_fact()
    ├── verify_fact()
    ├── get_settings()
    └── update_settings()
```

---

## 🔧 نصب و راه‌اندازی

### گام ۱: نصب Dependencies

```bash
cd /path/to/homaye-tabesh
npm install
```

### گام ۲: Build React Components

```bash
npm run build
```

این دستور سه فایل می‌سازد:
- `assets/build/homa-sidebar.js`
- `assets/build/atlas-dashboard.js`
- `assets/build/super-console.js` ← جدید

### گام ۳: فعال‌سازی در WordPress

کلاس‌های جدید در `HT_Core::init_services()` مقداردهی می‌شوند:

```php
// Initialize PR19
$this->system_diagnostics = new HT_System_Diagnostics();
$this->console_api = new HT_Console_Analytics_API();
```

### گام ۴: دسترسی از منوی Admin

منوی جدید در `HT_Admin::add_admin_menu()`:

```php
add_submenu_page(
    'homaye-tabesh',
    __('سوپر پنل هما', 'homaye-tabesh'),
    __('🎛️ سوپر پنل', 'homaye-tabesh'),
    'manage_options',
    'homaye-tabesh-super-console',
    [$this, 'render_super_console_page']
);
```

---

## 🎨 طراحی Component‌ها

### SuperConsole (Main Container)

```jsx
const SuperConsole = () => {
    const [activeTab, setActiveTab] = useState('overview');
    const [systemStatus, setSystemStatus] = useState(null);

    // Load system status on mount
    useEffect(() => {
        loadSystemStatus();
    }, []);

    const loadSystemStatus = async () => {
        const response = await fetch(
            window.homaConsoleConfig.apiUrl + '/system/status'
        );
        const data = await response.json();
        setSystemStatus(data.data);
    };

    // Render active tab dynamically
    const ActiveComponent = tabs.find(t => t.id === activeTab)?.component;
    
    return (
        <div className="homa-super-console" dir="rtl">
            {/* Header */}
            {/* Tab Navigation */}
            {/* Active Tab Content */}
            <ActiveComponent onRefresh={loadSystemStatus} />
        </div>
    );
};
```

### OverviewAnalytics (Tab 1)

```jsx
const OverviewAnalytics = ({ onRefresh }) => {
    const [analytics, setAnalytics] = useState(null);
    const [timeRange, setTimeRange] = useState('7days');

    useEffect(() => {
        loadAnalytics();
    }, [timeRange]);

    const loadAnalytics = async () => {
        const response = await fetch(
            `${window.homaConsoleConfig.apiUrl}/analytics?range=${timeRange}`
        );
        const data = await response.json();
        setAnalytics(data.data);
    };

    return (
        <div className="overview-analytics">
            {/* Time Range Selector */}
            {/* Metrics Grid */}
            {/* Heatmap */}
        </div>
    );
};
```

### SystemHealth (Tab 3 with Diagnostics)

```jsx
const SystemHealth = () => {
    const [diagnostics, setDiagnostics] = useState(null);
    const [fixing, setFixing] = useState(false);

    const runDiagnostics = async () => {
        const response = await fetch(
            `${window.homaConsoleConfig.apiUrl}/diagnostics`
        );
        const data = await response.json();
        setDiagnostics(data.data);
    };

    const runAutoFix = async () => {
        setFixing(true);
        const response = await fetch(
            `${window.homaConsoleConfig.apiUrl}/diagnostics/fix`,
            { method: 'POST' }
        );
        const data = await response.json();
        // Show results
        runDiagnostics(); // Refresh
        setFixing(false);
    };

    return (
        <div className="system-health">
            {/* Action Bar with Fix All button */}
            {/* Components Grid */}
            {/* Issues List */}
        </div>
    );
};
```

---

## 🔌 REST API Endpoints

### System Status

```php
register_rest_route('homaye/v1/console', '/system/status', [
    'methods' => 'GET',
    'callback' => [$this, 'get_system_status'],
    'permission_callback' => [$this, 'check_admin_permission']
]);
```

**Response:**
```json
{
    "success": true,
    "data": {
        "overall_health": "healthy",
        "last_check": "2024-12-26 17:30:00"
    }
}
```

### Analytics Data

```php
register_rest_route('homaye/v1/console', '/analytics', [
    'methods' => 'GET',
    'callback' => [$this, 'get_analytics_data']
]);
```

**Parameters:**
- `range`: "24hours" | "7days" | "30days"

**Response:**
```json
{
    "success": true,
    "data": {
        "token_usage": {
            "total": 45000,
            "by_section": {
                "chat": 30000,
                "translation": 10000,
                "index": 5000
            }
        },
        "leads": {
            "total": 120,
            "conversion_rate": 15.5
        },
        "interests": [
            {"topic": "محصولات", "count": 45},
            {"topic": "قیمت", "count": 38}
        ]
    }
}
```

### Run Diagnostics

```php
register_rest_route('homaye/v1/console', '/diagnostics', [
    'methods' => 'GET',
    'callback' => [$this, 'run_diagnostics']
]);
```

**Response:**
```json
{
    "success": true,
    "data": {
        "gemini_api": {
            "status": "healthy",
            "connection": "Connected",
            "response_time": "150ms"
        },
        "tabesh_database": {
            "status": "healthy",
            "facts_count": 234
        },
        "issues": [],
        "overall_health": "healthy"
    }
}
```

---

## 🔒 امنیت و کنترل دسترسی

### Permission Callback

```php
public function check_admin_permission(): bool
{
    return current_user_can('manage_options');
}
```

### Nonce Verification

همه درخواست‌ها باید شامل `X-WP-Nonce` header باشند:

```javascript
headers: {
    'X-WP-Nonce': window.homaConsoleConfig.nonce
}
```

### Data Sanitization

```php
// Sanitize input
$fact = sanitize_text_field($body['fact']);
$category = sanitize_text_field($body['category']);

// Validate
if (empty($fact)) {
    return new \WP_REST_Response([
        'success' => false,
        'message' => 'فکت نمی‌تواند خالی باشد'
    ], 400);
}
```

---

## 🎯 System Diagnostics Engine

### بررسی سلامت Gemini API

```php
private function test_gemini_connection(): array
{
    $start_time = microtime(true);
    
    try {
        $core = HT_Core::instance();
        $response = $core->brain->generate_response('سلام', [
            'context' => 'health_check',
            'max_tokens' => 10
        ]);

        $response_time = round((microtime(true) - $start_time) * 1000, 2);

        if ($response) {
            return [
                'status' => 'healthy',
                'connection' => 'Connected',
                'response_time' => $response_time . 'ms',
                'model' => 'gemini-2.5-flash'
            ];
        }
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'connection' => 'Failed',
            'message' => $e->getMessage()
        ];
    }
}
```

### عیبیاب خودکار (Auto Fix)

```php
public function auto_fix_issues(): array
{
    $fixed = [];
    $failed = [];

    try {
        // Fix 1: Index missing pages
        $core = HT_Core::instance();
        if ($core->knowledge) {
            $result = $core->knowledge->index_all_pages();
            if ($result) {
                $fixed[] = 'صفحات با موفقیت ایندکس شدند';
            }
        }

        // Fix 2: Cleanup old data
        global $wpdb;
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}homaye_security_events 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        if ($deleted !== false) {
            $fixed[] = 'داده‌های قدیمی پاکسازی شدند';
        }
    } catch (\Exception $e) {
        $failed[] = 'خطای عمومی: ' . $e->getMessage();
    }

    return [
        'success' => count($failed) === 0,
        'fixed' => $fixed,
        'failed' => $failed
    ];
}
```

---

## 🧠 Knowledge Fine-Tuner

### ویرایش فکت

```jsx
const handleSaveFact = async () => {
    const response = await fetch(
        `${window.homaConsoleConfig.apiUrl}/knowledge/facts/${selectedFact.id}`,
        {
            method: 'PUT',
            headers: {
                'X-WP-Nonce': window.homaConsoleConfig.nonce,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(selectedFact)
        }
    );
    
    const data = await response.json();
    if (data.success) {
        alert('✅ فکت با موفقیت ویرایش شد');
        loadFacts(); // Refresh
    }
};
```

### Backend Update

```php
public function update_fact(\WP_REST_Request $request): \WP_REST_Response
{
    global $wpdb;
    
    $fact_id = $request->get_param('id');
    $body = $request->get_json_params();

    $updated = $wpdb->update(
        $wpdb->prefix . 'homaye_knowledge_facts',
        [
            'fact' => sanitize_text_field($body['fact']),
            'category' => sanitize_text_field($body['category']),
            'source' => sanitize_text_field($body['source'] ?? ''),
            'tags' => json_encode($body['tags'] ?? [])
        ],
        ['id' => $fact_id]
    );

    return new \WP_REST_Response([
        'success' => $updated !== false
    ]);
}
```

---

## ⚙️ Super Config Matrix

### ساختار تنظیمات

```javascript
const settings = {
    core: {
        gemini_version: 'v1beta',
        model: 'gemini-2.5-flash',
        max_tokens: 2048,
        temperature: 0.7
    },
    visual: {
        primary_color: '#667eea',
        chat_icon: 'default',
        scroll_speed: 300,
        highlight_intensity: 50
    },
    database: {
        target_tables: ['posts', 'pages'],
        scan_interval: 60,
        excluded_categories: []
    },
    modules: {
        waf_enabled: true,
        otp_enabled: true,
        arabic_translation: true,
        order_tracking: true
    },
    messages: {
        welcome_lead: '',
        firewall_warning: '',
        otp_sms: ''
    },
    security: {
        sensitivity: 'medium',
        block_threshold: 30,
        block_duration: 24
    }
};
```

### ذخیره تنظیمات

```php
public function update_settings(\WP_REST_Request $request): \WP_REST_Response
{
    $body = $request->get_json_params();

    // Update each section
    foreach ($body as $section => $values) {
        foreach ($values as $key => $value) {
            update_option('ht_' . $key, $value);
        }
    }

    return new \WP_REST_Response([
        'success' => true,
        'message' => 'تنظیمات با موفقیت ذخیره شد'
    ]);
}
```

---

## 🧪 تست و اعتبارسنجی

### تست یکپارچگی

```bash
# تست تغییر تنظیمات
1. وارد تب Settings شوید
2. مقدار Temperature را تغییر دهید
3. Save کنید
4. صفحه را رفرش کنید
5. مقدار جدید باید نمایش داده شود
```

### تست عیبیابی

```bash
# تست Auto-Fix
1. خطای عمدی ایجاد کنید (مثلاً API Key را حذف کنید)
2. به تب System Health بروید
3. باید هشدار قرمز ببینید
4. روی Fix All کلیک کنید
5. خطا باید برطرف شود
```

---

## 📊 بهینه‌سازی عملکرد

### Lazy Loading

```jsx
// Load data only when tab is active
useEffect(() => {
    if (activeTab === 'overview') {
        loadAnalytics();
    }
}, [activeTab]);
```

### Caching

```javascript
// Cache system status for 30 seconds
const CACHE_DURATION = 30000;
let cachedStatus = null;
let cacheTime = 0;

const loadSystemStatus = async () => {
    const now = Date.now();
    if (cachedStatus && (now - cacheTime) < CACHE_DURATION) {
        return cachedStatus;
    }
    
    // Fetch fresh data
    const data = await fetchSystemStatus();
    cachedStatus = data;
    cacheTime = now;
    return data;
};
```

---

## 🐛 رفع مشکلات متداول

### مشکل: سوپر پنل نمایش داده نمی‌شود

**راه حل:**
1. بررسی کنید که `super-console.js` بیلد شده باشد
2. کنسول مرورگر را چک کنید
3. مطمئن شوید React و ReactDOM لود شده‌اند

### مشکل: API Endpoints کار نمی‌کنند

**راه حل:**
1. بررسی کنید `HT_Console_Analytics_API` در `HT_Core` مقداردهی شده باشد
2. Nonce را بررسی کنید
3. دسترسی `manage_options` را چک کنید

### مشکل: دیتا نمایش داده نمی‌شود

**راه حل:**
1. جداول دیتابیس را بررسی کنید
2. بررسی کنید داده واقعی در دیتابیس وجود دارد
3. کوئری‌های SQL را در logs چک کنید

---

## 📚 منابع و مستندات

- [React Documentation](https://react.dev/)
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [PR18-IMPLEMENTATION.md](./PR18-IMPLEMENTATION.md)
- [PR16-IMPLEMENTATION.md](./PR16-IMPLEMENTATION.md)

---

**نسخه:** 1.0.0  
**آخرین بروزرسانی:** دسامبر ۲۰۲۴  
**وضعیت:** ✅ مستند شده
