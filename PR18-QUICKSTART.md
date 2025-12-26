# PR18 QuickStart - راهنمای سریع

## 🚀 شروع سریع در 5 دقیقه

### 1️⃣ فعالسازی اولیه (خودکار)
پس از نصب افزونه، سیستم تابآوری به صورت خودکار فعال میشود:
- ✅ جداول دیتابیس ایجاد شده
- ✅ Cron jobs برنامهریزی شده
- ✅ کش سیستم آماده

### 2️⃣ چک کردن وضعیت سیستم

```bash
# وضعیت آفلاین/آنلاین
curl https://yoursite.com/wp-json/homaye-tabesh/v1/fallback/status

# آمار لاگها
curl https://yoursite.com/wp-json/homaye-tabesh/v1/logs/statistics

# آمار کش
curl https://yoursite.com/wp-json/homaye-tabesh/v1/cache/statistics
```

### 3️⃣ اولین پشتیبانگیری

```bash
# ایجاد Snapshot
curl -X POST https://yoursite.com/wp-json/homaye-tabesh/v1/snapshots/export \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "description": "First backup",
    "encrypt": true
  }'
```

### 4️⃣ تست حالت آفلاین

```php
// در functions.php یا plugin خودتان
$engine = new \HomayeTabesh\HT_Fallback_Engine();

// شبیهسازی خطا
$engine->record_api_result(false);
$engine->record_api_result(false);
$engine->record_api_result(false);

// چک کردن وضعیت
if ($engine->is_offline()) {
    echo "System is now in offline mode!";
}

// بازگشت به آنلاین
$engine->force_online_mode();
```

---

## 💡 سناریوهای متداول

### سناریو 1: ثبت لاگ سفارشی

```php
$logger = new \HomayeTabesh\HT_BlackBox_Logger();

$logger->log_ai_transaction([
    'log_type' => 'custom_event',
    'user_prompt' => 'User asked about pricing',
    'ai_response' => 'Provided price list',
    'latency_ms' => 500,
    'tokens_used' => 100,
    'status' => 'success',
]);
```

### سناریو 2: گرم کردن کش پس از بروزرسانی دانش

```bash
# پاکسازی کش
curl -X POST https://yoursite.com/wp-json/homaye-tabesh/v1/cache/clear \
  -H "Authorization: Bearer YOUR_TOKEN"

# گرم کردن مجدد
curl -X POST https://yoursite.com/wp-json/homaye-tabesh/v1/cache/warmup \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### سناریو 3: Export و Import دانش

```php
// Export
$exporter = new \HomayeTabesh\HT_Data_Exporter();
$result = $exporter->export_knowledge('Before migration', encrypt: true);

// Download file
$file_path = $result['file_path'];

// Upload to new site and Import
$result = $exporter->import_knowledge($uploaded_file_path, mode: 'replace');

echo "Imported: " . $result['imported_count'] . " facts";
```

### سناریو 4: فرمت ایمن اعداد برای AI

```php
$formatter = new \HomayeTabesh\HT_Numerical_Formatter();

// فرمت قیمت
$price = $formatter->format_price(250000);

// ساخت پاسخ محافظت شده
$response = $formatter->build_protected_response(
    'قیمت این محصول {price} است و {stock} عدد موجود داریم.',
    [
        'price' => $formatter->format_price(250000),
        'stock' => $formatter->format_stock(15)
    ]
);

// استفاده در Gemini
$gemini_response = $response['response'];
// "قیمت این محصول ۲۵۰,۰۰۰ تومان است و ۱۵ عدد موجود داریم."
```

### سناریو 5: اجرای عملیات سنگین در پسزمینه

```php
$processor = new \HomayeTabesh\HT_Background_Processor();

// صف کردن Job برای بهینهسازی دیتابیس
$job_id = $processor->queue_job('optimize_database', []);

// چک کردن وضعیت
$job = $processor->get_job_status($job_id);
echo "Progress: " . $job['progress'] . "%";
```

### سناریو 6: آنالیز و بهینهسازی خودکار

```php
$cleanup = new \HomayeTabesh\HT_Auto_Cleanup();

// اجرای آنالیز
$analysis = $cleanup->run_analysis();

echo "Severity: " . $analysis['severity'];
echo "Duplicates found: " . count($analysis['findings']['duplicates']);

// حذف خودکار موارد تکراری
if ($analysis['report_id']) {
    $result = $cleanup->auto_fix($analysis['report_id']);
    echo "Actions taken: " . count($result['actions_taken']);
}
```

---

## 🔧 تنظیمات پیشرفته

### تغییر زمان نگهداری لاگها

```php
// در wp-config.php یا functions.php
add_filter('ht_blackbox_retention_days', function() {
    return 60; // نگهداری 60 روزه بهجای 30 روز
});
```

### تغییر مدت زمان کش

```php
// برای دانش خاص
$optimizer = new \HomayeTabesh\HT_Query_Optimizer();
$facts = $optimizer->get_cached_knowledge(
    ['category' => 'prices'],
    3600 // 1 ساعت بهجای 10 دقیقه
);
```

### سفارشیسازی پیام آفلاین

```php
add_filter('ht_fallback_offline_message', function($message) {
    return 'سیستم در حال بروزرسانی است. لطفاً چند دقیقه دیگر مراجعه کنید.';
});
```

### غیرفعال کردن Auto-Cleanup

```php
$cleanup = new \HomayeTabesh\HT_Auto_Cleanup();
$cleanup->unschedule_analysis();
```

---

## 📊 مانیتورینگ روزانه

### چکلیست صبحگاهی (5 دقیقه)

```bash
# 1. وضعیت سیستم
curl https://yoursite.com/wp-json/homaye-tabesh/v1/fallback/status

# 2. خطاهای شبانه
curl https://yoursite.com/wp-json/homaye-tabesh/v1/logs?status=error&limit=10

# 3. حجم کش
curl https://yoursite.com/wp-json/homaye-tabesh/v1/cache/statistics

# 4. لیدهای جدید (اگر آفلاین بوده)
curl https://yoursite.com/wp-json/homaye-tabesh/v1/fallback/leads?contacted=0

# 5. گزارشات بهینهسازی
curl https://yoursite.com/wp-json/homaye-tabesh/v1/cleanup/reports?status=pending
```

---

## 🐛 حل سریع مشکلات رایج

### مشکل: API Error رخ میدهد اما آفلاین نمیشود
```php
// چک کردن threshold
$engine = new \HomayeTabesh\HT_Fallback_Engine();
$stats = $engine->get_statistics();
echo "Failure count: " . $stats['failure_count']; // باید 3 باشد
```

### مشکل: کش کار نمیکند
```bash
# چک کردن Transient API
wp transient list | grep "ht_query_cache"

# اگر خالی بود، باید گرم کنید
curl -X POST https://yoursite.com/wp-json/homaye-tabesh/v1/cache/warmup
```

### مشکل: Job اجرا نمیشود
```bash
# لیست Cron jobs
wp cron event list

# اجرای دستی
wp cron event run ht_process_background_jobs

# چک کردن وضعیت
curl https://yoursite.com/wp-json/homaye-tabesh/v1/jobs/{job_id}
```

### مشکل: Export فایل نمیسازد
```bash
# چک کردن دسترسیها
ls -la wp-content/uploads/homa-exports/

# چک کردن space
df -h

# تست دستی
$exporter = new \HomayeTabesh\HT_Data_Exporter();
$result = $exporter->export_knowledge('Test');
var_dump($result);
```

---

## 🎓 Best Practices

### 1. Snapshot منظم
```php
// هر هفته یک Snapshot دستی
$exporter = new \HomayeTabesh\HT_Data_Exporter();
$exporter->export_knowledge('Weekly backup - ' . date('Y-m-d'), encrypt: true);
```

### 2. مانیتورینگ Latency
```php
// آمار هفتگی
$logger = new \HomayeTabesh\HT_BlackBox_Logger();
$stats = $logger->get_statistics();

if ($stats['avg_latency_ms'] > 2000) {
    // هشدار: latency بالا
    error_log('Warning: High AI latency detected');
}
```

### 3. پاکسازی ماهانه
```php
// اجرای آنالیز و پاکسازی
$cleanup = new \HomayeTabesh\HT_Auto_Cleanup();
$analysis = $cleanup->run_analysis();

if ($analysis['severity'] === 'critical') {
    // اطلاعرسانی فوری به مدیر
    wp_mail(get_option('admin_email'), 'Homa: Critical Cleanup Required', ...);
}
```

---

## 📞 دریافت کمک

- **Documentation**: [PR18-IMPLEMENTATION.md](./PR18-IMPLEMENTATION.md)
- **Full Guide**: [PR18-README.md](./PR18-README.md)
- **GitHub Issues**: https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh/issues

---

**نسخه**: 1.0.0  
**آخرین بروزرسانی**: 2025-12-26
