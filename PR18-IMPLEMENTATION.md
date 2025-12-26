# PR18 Implementation Details

## 🏗️ معماری سیستم تابآوری و انتقال دانش

### نمای کلی

```
┌─────────────────────────────────────────────────────────────────────┐
│                      HT_Core (Orchestrator)                         │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                      HT_Gemini_Client                         │  │
│  │  ┌─────────────────────────────────────────────────────────┐ │  │
│  │  │  1. Check Fallback Status                              │ │  │
│  │  │  2. Log Transaction (BlackBox)                         │ │  │
│  │  │  3. Use Cached Data (Query Optimizer)                  │ │  │
│  │  │  4. Format Numbers (Numerical Formatter)               │ │  │
│  │  └─────────────────────────────────────────────────────────┘ │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌──────────────┬─────────────┬──────────────┬─────────────────┐   │
│  │  BlackBox    │  Fallback   │  Query       │  Data           │   │
│  │  Logger      │  Engine     │  Optimizer   │  Exporter       │   │
│  └──────────────┴─────────────┴──────────────┴─────────────────┘   │
│                                                                     │
│  ┌──────────────┬─────────────┬──────────────┐                     │
│  │  Background  │  Numerical  │  Auto        │                     │
│  │  Processor   │  Formatter  │  Cleanup     │                     │
│  └──────────────┴─────────────┴──────────────┘                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📦 Component 1: HT_BlackBox_Logger

### مسئولیت‌ها

1. ثبت تمام تراکنشهای AI (پرامپت، پاسخ، latency، توکن)
2. Error Tracing با ذخیره کامل وضعیت محیط
3. Masking اطلاعات حساس (GDPR Compliant)
4. پاکسازی خودکار لاگهای قدیمی (30 روز)

### ساختار داده

```php
class HT_BlackBox_Logger {
    private const LOG_RETENTION_DAYS = 30;
    private const SENSITIVE_PATTERNS = [
        'credit_card' => '/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/',
        'national_id' => '/\b\d{10}\b/',
        'phone' => '/\b09\d{9}\b/',
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        'password' => '/(password|رمز|پسورد|گذرواژه)[\s:=]+([^\s]+)/i',
    ];
}
```

### جدول دیتابیس

```sql
CREATE TABLE homa_blackbox_logs (
    id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    log_type varchar(50) NOT NULL,
    user_id bigint(20),
    user_identifier varchar(255),
    user_prompt text,
    raw_prompt text,
    ai_response text,
    raw_response text,
    latency_ms int,
    tokens_used int,
    model_name varchar(100),
    context_data longtext,
    error_message text,
    error_trace text,
    environment_state longtext,
    request_method varchar(20),
    ip_address varchar(45),
    user_agent text,
    status varchar(20) DEFAULT 'success',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    KEY (log_type),
    KEY (status),
    KEY (created_at)
);
```

### استفاده

```php
// Successful transaction logging
$logger = new HT_BlackBox_Logger();
$logger->log_ai_transaction([
    'user_prompt' => $prompt,
    'ai_response' => $response,
    'latency_ms' => $latency,
    'tokens_used' => $tokens,
    'status' => 'success',
]);

// Error logging
try {
    // Some operation
} catch (\Exception $e) {
    $logger->log_error($e, ['context' => $context_data]);
}
```

---

## 📦 Component 2: HT_Fallback_Engine

### مسئولیت‌ها

1. تشخیص خودکار قطعی API (3 خطای متوالی = حالت آفلاین)
2. ارائه Offline Persona و پیامهای پشتیبان
3. جمعآوری هوشمند لیدها در حالت آفلاین
4. نوتیفیکیشن مدیر در زمان قطعی

### فلوچارت حالت

```
┌──────────────┐
│  API Call    │
└──────┬───────┘
       │
       ├─ Success ──→ Reset Counter ──→ Online Mode
       │
       └─ Failure ──→ Increment Counter
                         │
                         ├─ Count < 3 ──→ Retry
                         │
                         └─ Count >= 3 ──→ Enter Offline Mode
                                             │
                                             ├─ Show Lead Form
                                             └─ Notify Admin
```

### جداول دیتابیس

```sql
CREATE TABLE homa_offline_leads (
    id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    full_name varchar(255) NOT NULL,
    phone varchar(20) NOT NULL,
    email varchar(255),
    user_message text,
    collected_at datetime DEFAULT CURRENT_TIMESTAMP,
    contacted tinyint(1) DEFAULT 0,
    contacted_at datetime,
    notes text,
    KEY (phone),
    KEY (contacted)
);
```

### مثال استفاده

```php
$engine = new HT_Fallback_Engine();

// Check status before API call
if ($engine->is_offline()) {
    return $engine->get_fallback_response($user_input, $context);
}

// Record API result
$success = make_api_call();
$engine->record_api_result($success);

// Collect lead in offline mode
$lead_id = $engine->save_lead([
    'full_name' => 'علی احمدی',
    'phone' => '09123456789',
    'message' => 'علاقه‌مند به خرید محصول X',
]);
```

---

## 📦 Component 3: HT_Query_Optimizer

### مسئولیت‌ها

1. کش کردن کوئریهای سنگین با WP_Transient
2. بهینهسازی ایندکسهای دیتابیس
3. Cache Warmup برای دادههای پرتکرار
4. گزارش آماری حجم کش

### استراتژی کشینگ

```php
// Default cache expiry: 10 minutes
// Hot facts cache: 30 minutes
// Product data cache: 5 minutes
// Order data cache: 2 minutes
```

### مثال استفاده

```php
$optimizer = new HT_Query_Optimizer();

// Get cached knowledge
$facts = $optimizer->get_cached_knowledge([
    'is_active' => 1,
    'limit' => 100
], 600); // 10 minutes

// Get hot facts (frequently accessed)
$hot_facts = $optimizer->get_hot_facts();

// Search with cache
$results = $optimizer->search_cached_knowledge('قیمت محصول', 10);

// Clear caches
$optimizer->clear_all_caches();

// Warmup cache
$optimizer->warmup_cache();

// Add indexes
$optimizer->add_indexes();
```

---

## 📦 Component 4: HT_Data_Exporter

### مسئولیت‌ها

1. Export کامل دانش به JSON (با یا بدون رمزنگاری)
2. Import دانش با دو حالت: Merge یا Replace
3. سیستم Snapshot خودکار قبل از Import
4. مدیریت Snapshots و بازگشت به نسخههای قبلی

### ساختار Export JSON

```json
{
  "homa_version": "1.0.0",
  "export_date": "2025-12-26 16:00:00",
  "site_url": "https://example.com",
  "facts_count": 150,
  "knowledge_base": [
    {
      "fact_key": "product_price_101",
      "fact_value": "250000",
      "category": "prices",
      "is_active": 1
    }
  ],
  "authority_overrides": [...],
  "firewall_settings": {...},
  "plugin_settings": {...},
  "export_metadata": {
    "wp_version": "6.4",
    "php_version": "8.2",
    "description": "Manual export"
  }
}
```

### مثال استفاده

```php
$exporter = new HT_Data_Exporter();

// Export knowledge
$result = $exporter->export_knowledge('Backup before update', encrypt: true);
// Returns: ['success' => true, 'snapshot_id' => 123, 'file_path' => '...']

// Import knowledge
$result = $exporter->import_knowledge($file_path, mode: 'merge');
// Returns: ['success' => true, 'imported_count' => 50, 'skipped_count' => 10]

// Create auto-snapshot
$snapshot = $exporter->create_auto_snapshot('Before import');

// Restore snapshot
$result = $exporter->restore_snapshot($snapshot_id);

// Get all snapshots
$snapshots = $exporter->get_snapshots(['is_auto' => false]);
```

---

## 📦 Component 5: HT_Background_Processor

### مسئولیت‌ها

1. پردازش عملیات سنگین در پسزمینه
2. Chunk Processing برای جلوگیری از Timeout
3. Progress Tracking برای هر Job
4. مدیریت صف Jobs با WP-Cron

### انواع Jobs

```php
// Supported job types:
'index_knowledge'    => Index/reindex knowledge base
'export_large'       => Export large datasets
'optimize_database'  => Optimize tables and add indexes
'cleanup_logs'       => Clean old logs
```

### مثال استفاده

```php
$processor = new HT_Background_Processor();

// Queue a job
$job_id = $processor->queue_job('export_large', [
    'description' => 'Monthly backup',
    'encrypt' => true
]);

// Get job status
$job = $processor->get_job_status($job_id);
// Returns: ['status' => 'processing', 'progress' => 45, 'total_items' => 100]

// Get all jobs
$jobs = $processor->get_jobs(['status' => 'pending']);

// Cancel a job
$processor->cancel_job($job_id);
```

---

## 📦 Component 6: HT_Numerical_Formatter

### مسئولیت‌ها

1. فرمت ثابت برای اعداد (قیمت، موجودی، شماره سفارش)
2. جلوگیری از Hallucination مدل AI در اعداد
3. تبدیل خودکار به رقم فارسی
4. ارائه داده ساختاریافته به AI

### مثال استفاده

```php
$formatter = new HT_Numerical_Formatter();

// Format price
$price = $formatter->format_price(250000, 'IRR');
// Returns: ['raw_value' => 250000, 'formatted' => '۲۵۰,۰۰۰ تومان']

// Format stock
$stock = $formatter->format_stock(5);
// Returns: ['raw_value' => 5, 'formatted' => '۵ عدد', 'status' => 'low_stock']

// Format order number
$order = $formatter->format_order_number(123);
// Returns: ['raw_value' => 123, 'formatted' => '#000123']

// Get safe product data
$product = $formatter->get_safe_product_data(101);

// Build protected response
$response = $formatter->build_protected_response(
    'قیمت محصول {price} و موجودی {stock} است.',
    ['price' => $price, 'stock' => $stock]
);
```

---

## 📦 Component 7: HT_Auto_Cleanup

### مسئولیت‌ها

1. شناسایی فکتهای تکراری
2. تشخیص فکتهای منقضی (قیمتهای قدیمی)
3. یافتن فکتهای غیرفعال یا کم استفاده
4. ارائه گزارش و پیشنهادات بهینهسازی

### فرآیند آنالیز

```
┌──────────────────┐
│  Run Analysis    │
└────────┬─────────┘
         │
         ├─→ Find Duplicates
         ├─→ Find Stale Facts (90+ days)
         ├─→ Find Outdated Prices
         ├─→ Check DB Size
         │
         ├─→ Generate Recommendations
         └─→ Save Report
```

### مثال استفاده

```php
$cleanup = new HT_Auto_Cleanup();

// Run full analysis
$result = $cleanup->run_analysis();
/*
Returns:
[
    'report_id' => 123,
    'findings' => [
        'duplicates' => [...],
        'stale' => [...],
        'outdated_prices' => [...],
    ],
    'recommendations' => [...],
    'severity' => 'medium'
]
*/

// Auto-fix safe issues
$result = $cleanup->auto_fix($report_id);

// Get reports
$reports = $cleanup->get_reports(['status' => 'pending']);
```

---

## 🔗 یکپارچهسازی با Gemini Client

### افزودن Logging

```php
// In HT_Gemini_Client::generate_content()
$start_time = microtime(true);

// ... API call ...

$latency_ms = (int) ((microtime(true) - $start_time) * 1000);

$logger = new HT_BlackBox_Logger();
$logger->log_ai_transaction([
    'user_prompt' => $prompt,
    'ai_response' => $response,
    'latency_ms' => $latency_ms,
    'tokens_used' => $tokens,
]);
```

### افزودن Fallback

```php
// Check offline status
$fallback_engine = new HT_Fallback_Engine();
if ($fallback_engine->is_offline()) {
    return $fallback_engine->get_fallback_response($prompt, $context);
}

// Record result
try {
    $response = make_api_call();
    $fallback_engine->record_api_result(true);
} catch (\Exception $e) {
    $fallback_engine->record_api_result(false);
}
```

---

## 🌐 REST API Endpoints

### Logs
- `GET /wp-json/homaye-tabesh/v1/logs` - Get logs with filters
- `GET /wp-json/homaye-tabesh/v1/logs/statistics` - Get log statistics

### Fallback
- `GET /wp-json/homaye-tabesh/v1/fallback/status` - Get offline status
- `GET /wp-json/homaye-tabesh/v1/fallback/leads` - Get offline leads
- `POST /wp-json/homaye-tabesh/v1/fallback/force-online` - Force online mode
- `POST /wp-json/homaye-tabesh/v1/offline/collect-lead` - Collect lead (public)

### Cache
- `GET /wp-json/homaye-tabesh/v1/cache/statistics` - Get cache statistics
- `POST /wp-json/homaye-tabesh/v1/cache/clear` - Clear all caches
- `POST /wp-json/homaye-tabesh/v1/cache/warmup` - Warmup cache

### Snapshots
- `GET /wp-json/homaye-tabesh/v1/snapshots` - List snapshots
- `POST /wp-json/homaye-tabesh/v1/snapshots/export` - Export knowledge
- `POST /wp-json/homaye-tabesh/v1/snapshots/{id}/restore` - Restore snapshot
- `DELETE /wp-json/homaye-tabesh/v1/snapshots/{id}` - Delete snapshot

### Background Jobs
- `GET /wp-json/homaye-tabesh/v1/jobs` - List jobs
- `GET /wp-json/homaye-tabesh/v1/jobs/{id}` - Get job status
- `POST /wp-json/homaye-tabesh/v1/jobs/queue` - Queue new job
- `POST /wp-json/homaye-tabesh/v1/jobs/{id}/cancel` - Cancel job

### Cleanup
- `POST /wp-json/homaye-tabesh/v1/cleanup/analyze` - Run analysis
- `GET /wp-json/homaye-tabesh/v1/cleanup/reports` - Get reports
- `POST /wp-json/homaye-tabesh/v1/cleanup/{id}/auto-fix` - Auto-fix issues

---

## 🔒 امنیت

1. **Masking داده‌های حساس**: کارت اعتباری، کد ملی، شماره تلفن، ایمیل، پسورد
2. **محافظت از فایلهای Export**: .htaccess برای جلوگیری از دسترسی مستقیم
3. **رمزنگاری Exports**: استفاده از AES-256-CBC با کلید WordPress Salt
4. **دسترسی Admin فقط**: تمام endpoints مدیریتی نیاز به دسترسی admin دارند

---

## 📊 Monitoring & Logs

### WP-Cron Jobs

```php
// Scheduled tasks
ht_blackbox_cleanup          => Daily log cleanup
ht_cache_warmup              => Hourly cache warmup
ht_process_background_jobs   => On-demand job processing
ht_auto_cleanup_analysis     => Weekly cleanup analysis
```

### Debug Logging

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Homa: Entered offline mode');
    error_log('Homa: Cache warmed up');
}
```

---

**End of Implementation Document**
