# PR18: واحد تابآوری و انتقال دانش

## 📖 خلاصه

PR18 سیستم «ضد گلوله» برای هما است که تمرکز آن بر سه محور اصلی است:
1. **شفافیت فنی**: ثبت دقیق وقایع برای عیبیابی آنی
2. **تداوم سرویس**: جلوگیری از توقف چت در زمان قطعی API
3. **قابلیت جابجایی**: امکان انتقال کل مغز هما بین وبسایتهای مختلف

---

## 🎯 اهداف کلیدی

### 1. لایه جعبه سیاه (BlackBox Logger)
ثبت تمام تراکنشهای هوش مصنوعی شامل:
- پرامپت خام و پاسخ خام مدل
- زمان تاخیر (Latency) و توکن مصرفی
- Error Tracing با وضعیت کامل متغیرها
- Masking خودکار اطلاعات حساس (GDPR)

### 2. منطق پاسخ پشتیبان (Fallback Engine)
- **Offline Persona**: سوئیچ خودکار به وضعیت آفلاین
- **Smart Collection**: جمعآوری لیدها در حالت آفلاین
- **تداوم تعامل**: پیام قول تماس بهجای قطع ارتباط

### 3. بهینهسازی دیتابیس (Query Optimizer)
- **Query Caching**: ذخیره نتایج کوئریهای سنگین (10 دقیقه)
- **Index Optimization**: بهینهسازی جداول برای جستجوی سریع
- **Cache Warmup**: پیش‌بارگذاری دادههای پرتکرار

### 4. درونریزی و برونبری (Data Exporter)
- **JSON Migration**: خروجی رمزنگاری شده از تمام دانش
- **Knowledge Merge**: وارد کردن دانش بدون پاک کردن قبلیها
- **Snapshot System**: بازگشت سریع به نسخههای قبلی

### 5. پردازش پسزمینه (Background Processor)
- **WP-Cron Integration**: پردازش عملیات سنگین
- **Chunk Processing**: جلوگیری از Timeout
- **Progress Tracking**: نمایش پیشرفت عملیات

### 6. محافظ اعداد (Numerical Formatter)
- **Anti-Hallucination**: جلوگیری از اشتباه مدل در اعداد
- **Structured Output**: فرمت ثابت برای قیمتها و موجودی
- **Persian Digits**: تبدیل خودکار به رقم فارسی

### 7. خود-بهینهسازی (Auto Cleanup)
- **Duplicate Detection**: شناسایی فکتهای تکراری
- **Stale Facts**: تشخیص دانش منقضی (90+ روز)
- **Outdated Prices**: یافتن قیمتهای قدیمی
- **Auto-Fix**: حذف خودکار موارد ایمن

---

## 🚀 نصب و راهاندازی

### پیشنیازها
- WordPress 6.0+
- PHP 8.2+
- WooCommerce (اختیاری)

### فعالسازی خودکار
هنگام فعالسازی افزونه، تمام جداول و تنظیمات به صورت خودکار ایجاد میشوند:
- ✅ جداول دیتابیس
- ✅ ایندکسهای بهینهساز
- ✅ فایل محافظت .htaccess
- ✅ Cron Jobs

---

## 💡 استفاده سریع

### 1. مشاهده لاگها

```bash
GET /wp-json/homaye-tabesh/v1/logs
GET /wp-json/homaye-tabesh/v1/logs/statistics
```

### 2. مدیریت حالت آفلاین

```bash
# چک کردن وضعیت
GET /wp-json/homaye-tabesh/v1/fallback/status

# مشاهده لیدهای جمعآوری شده
GET /wp-json/homaye-tabesh/v1/fallback/leads

# فورس به حالت آنلاین
POST /wp-json/homaye-tabesh/v1/fallback/force-online
```

### 3. مدیریت کش

```bash
# آمار کش
GET /wp-json/homaye-tabesh/v1/cache/statistics

# پاکسازی کش
POST /wp-json/homaye-tabesh/v1/cache/clear

# گرم کردن کش
POST /wp-json/homaye-tabesh/v1/cache/warmup
```

### 4. پشتیبانگیری و بازیابی

```bash
# ایجاد Snapshot
POST /wp-json/homaye-tabesh/v1/snapshots/export
{
  "description": "Backup before update",
  "encrypt": true
}

# لیست Snapshotها
GET /wp-json/homaye-tabesh/v1/snapshots

# بازگشت به Snapshot
POST /wp-json/homaye-tabesh/v1/snapshots/{id}/restore
```

### 5. پردازش پسزمینه

```bash
# صف کردن Job
POST /wp-json/homaye-tabesh/v1/jobs/queue
{
  "job_type": "export_large",
  "job_data": {
    "description": "Monthly backup",
    "encrypt": true
  }
}

# چک کردن وضعیت Job
GET /wp-json/homaye-tabesh/v1/jobs/{id}
```

### 6. آنالیز و بهینهسازی

```bash
# اجرای آنالیز
POST /wp-json/homaye-tabesh/v1/cleanup/analyze

# مشاهده گزارشات
GET /wp-json/homaye-tabesh/v1/cleanup/reports

# حذف خودکار موارد تکراری
POST /wp-json/homaye-tabesh/v1/cleanup/{id}/auto-fix
```

---

## 🔧 استفاده در کد PHP

### BlackBox Logger

```php
$logger = new \HomayeTabesh\HT_BlackBox_Logger();

// Log successful transaction
$logger->log_ai_transaction([
    'user_prompt' => 'قیمت محصول چنده؟',
    'ai_response' => 'قیمت ۲۵۰,۰۰۰ تومان است',
    'latency_ms' => 850,
    'tokens_used' => 150,
]);

// Log error
try {
    // Operation
} catch (\Exception $e) {
    $logger->log_error($e, ['context' => $data]);
}
```

### Fallback Engine

```php
$engine = new \HomayeTabesh\HT_Fallback_Engine();

// Check if offline
if ($engine->is_offline()) {
    return $engine->get_fallback_response($user_input);
}

// Record API result
$success = make_api_call();
$engine->record_api_result($success);
```

### Query Optimizer

```php
$optimizer = new \HomayeTabesh\HT_Query_Optimizer();

// Get cached knowledge
$facts = $optimizer->get_cached_knowledge([
    'is_active' => 1,
    'limit' => 100
]);

// Search with cache
$results = $optimizer->search_cached_knowledge('قیمت محصول');
```

### Data Exporter

```php
$exporter = new \HomayeTabesh\HT_Data_Exporter();

// Export
$result = $exporter->export_knowledge('Manual backup', encrypt: true);

// Import
$result = $exporter->import_knowledge($file_path, mode: 'merge');

// Restore snapshot
$result = $exporter->restore_snapshot($snapshot_id);
```

### Background Processor

```php
$processor = new \HomayeTabesh\HT_Background_Processor();

// Queue job
$job_id = $processor->queue_job('optimize_database', []);

// Get status
$job = $processor->get_job_status($job_id);
```

### Numerical Formatter

```php
$formatter = new \HomayeTabesh\HT_Numerical_Formatter();

// Format price
$price = $formatter->format_price(250000);
// ['raw_value' => 250000, 'formatted' => '۲۵۰,۰۰۰ تومان']

// Get safe product data
$product = $formatter->get_safe_product_data($product_id);
```

### Auto Cleanup

```php
$cleanup = new \HomayeTabesh\HT_Auto_Cleanup();

// Run analysis
$result = $cleanup->run_analysis();

// Auto-fix issues
$result = $cleanup->auto_fix($report_id);
```

---

## 📊 Cron Jobs

سیستم به صورت خودکار این Cron Jobها را اجرا میکند:

| Job | زمانبندی | توضیحات |
|-----|---------|---------|
| `ht_blackbox_cleanup` | روزانه | پاکسازی لاگهای قدیمیتر از 30 روز |
| `ht_cache_warmup` | ساعتی | گرم کردن کش با دادههای پرتکرار |
| `ht_process_background_jobs` | On-demand | پردازش Jobهای صف |
| `ht_auto_cleanup_analysis` | هفتگی | آنالیز و شناسایی مشکلات |

---

## 🔒 امنیت

### GDPR Compliance
- ✅ Masking خودکار کارت اعتباری
- ✅ Masking کد ملی
- ✅ Masking شماره تلفن و ایمیل
- ✅ Masking پسوردها

### محافظت از Exports
- ✅ فایلها در پوشه محافظت شده
- ✅ .htaccess برای deny from all
- ✅ رمزنگاری AES-256-CBC
- ✅ دسترسی فقط برای مدیران

---

## 📈 مانیتورینگ

### Dashboard Widgets
در پنل مدیریت WordPress میتوانید ببینید:
- 📊 آمار لاگها (موفق/خطا)
- ⚡ میانگین Latency
- 🎯 وضعیت Fallback
- 💾 حجم کش
- 📦 تعداد Snapshots
- 🔄 Jobهای در حال اجرا

### لاگهای Debug

```php
// Enable debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// View logs
tail -f wp-content/debug.log | grep "Homa:"
```

---

## ⚠️ نکات مهم

### Storage Management
- لاگها بعد از 30 روز پاک میشوند
- Snapshots خودکار فقط 10 عدد آخر نگه داشته میشود
- کش به صورت خودکار Expire میشود

### Performance
- کش به صورت پیشفرض 10 دقیقه اعتبار دارد
- Background Jobs حداکثر 20 ثانیه در هر Cycle
- Chunk size برای پردازش: 50 item

### Fallback Threshold
- 3 خطای متوالی = حالت آفلاین
- 5 دقیقه Transient برای وضعیت
- نوتیفیکیشن ایمیل برای مدیر

---

## 🐛 عیبیابی

### مشکل: حالت آفلاین فعال نمیشود
```bash
# چک کردن خطاهای API
GET /wp-json/homaye-tabesh/v1/logs?status=error

# فورس به آفلاین
POST /wp-json/homaye-tabesh/v1/fallback/force-offline
```

### مشکل: کش کار نمیکند
```bash
# پاکسازی کش
POST /wp-json/homaye-tabesh/v1/cache/clear

# گرم کردن مجدد
POST /wp-json/homaye-tabesh/v1/cache/warmup

# چک کردن آمار
GET /wp-json/homaye-tabesh/v1/cache/statistics
```

### مشکل: Job اجرا نمیشود
```bash
# چک کردن وضعیت
GET /wp-json/homaye-tabesh/v1/jobs/{id}

# فورس اجرای Cron
wp cron event run ht_process_background_jobs
```

---

## 📚 مستندات بیشتر

- [PR18-IMPLEMENTATION.md](./PR18-IMPLEMENTATION.md) - جزئیات فنی کامل
- [PR18-QUICKSTART.md](./PR18-QUICKSTART.md) - شروع سریع
- [PR18-SUMMARY.md](./PR18-SUMMARY.md) - خلاصه تغییرات

---

## 🤝 مشارکت

برای گزارش باگ یا پیشنهاد ویژگی جدید، یک Issue در GitHub ایجاد کنید.

---

**نسخه**: 1.0.0  
**آخرین بروزرسانی**: 2025-12-26
