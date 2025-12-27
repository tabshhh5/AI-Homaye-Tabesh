# راهنمای سریع PR23 - اصلاحات بحرانی
## PR23 Quick Start Guide - Critical Fixes

### 🎯 خلاصه یک خطی
این PR تمام خطاهای PHP، مشکلات دیتابیس و ناسازگاری‌های CSP را برطرف کرده است.

---

## 🚀 نصب و به‌روزرسانی

### گام 1: Pull کردن آخرین تغییرات
```bash
git checkout copilot/fix-database-errors-homaye-tabesh
git pull origin copilot/fix-database-errors-homaye-tabesh
```

### گام 2: به‌روزرسانی Dependencies (اختیاری)
```bash
composer install --no-dev
npm install --production
```

### گام 3: فعال‌سازی مجدد افزونه
```bash
# در WordPress Admin Panel:
1. برو به Plugins > Installed Plugins
2. Deactivate کن همای تابش را
3. Activate کن دوباره
```

**نکته مهم:** ⚠️ سیستم self-healing خودکار دیتابیس را تعمیر می‌کند. نیازی به کار دستی نیست.

---

## ✅ بررسی سلامت افزونه

### تست 1: بررسی Database Tables
```php
<?php
// در PHP Console یا wp-cli:
global $wpdb;
$tables = [
    'homaye_security_events',
    'homaye_indexed_pages', 
    'homaye_monitored_plugins',
    'homaye_blacklist',
    'homaye_knowledge_facts',
];

foreach ($tables as $table) {
    $full_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_name'");
    echo $exists ? "✓ $table exists\n" : "✗ $table missing\n";
}
?>
```

### تست 2: بررسی API Connection
```bash
# در WordPress Admin:
1. برو به Settings > Homaye Tabesh
2. بخش "Test Gemini Connection"
3. کلیک روی "Test Connection"
```

**نتیجه موردانتظار:**
- ✅ "اتصال موفق" (Success)
- یا ❌ یکی از پیام‌های خطای واضح:
  - "کلید API نامعتبر است" (401)
  - "سهمیه تمام شده است" (429)
  - "سرویس موقتاً در دسترس نیست" (503)

### تست 3: بررسی Error Logs
```bash
# در wp-content/debug.log نباید این خطاها باشد:
grep "Undefined array key" wp-content/debug.log
grep "Cannot redefine property" wp-content/debug.log

# اگر خالی بود = موفق ✓
```

---

## 🔧 تنظیمات پیشنهادی

### 1. API Key Configuration
```
Settings > Homaye Tabesh > API Settings
└─ Gemini API Key: [YOUR_KEY_HERE]
```

### 2. Database Self-Healing (خودکار فعال است)
```
✓ بررسی خودکار هر 24 ساعت
✓ تعمیر جداول گمشده
✓ اضافه کردن ستون‌های ناقص
```

### 3. WooCommerce Integration (اختیاری)
```
اگر WooCommerce نصب نیست:
✓ افزونه کار می‌کند ولی با قابلیت محدودتر
✓ هیچ خطایی نمی‌دهد
```

---

## 🐛 عیب‌یابی (Troubleshooting)

### مشکل: "API key not configured"
**راه‌حل:**
```
1. برو به Settings > Homaye Tabesh
2. وارد کن Gemini API Key
3. ذخیره کن
```

### مشکل: "Database tables missing"
**راه‌حل:**
```
نیازی به کار دستی نیست!
1. صبر کن 5 دقیقه
2. رفرش کن صفحه admin
3. سیستم self-healing خودکار تعمیر می‌کند
```

یا اگر عجله داری:
```php
<?php
// در wp-cli یا PHP Console:
do_action('admin_init');
// سیستم repair فوری اجرا می‌شود
?>
```

### مشکل: "Quota exceeded"
**راه‌حل:**
```
این طبیعی است! Gemini API محدودیت روزانه دارد.
✓ صبر کن 24 ساعت
✓ یا upgrade کن پلن API
✓ افزونه به صورت خودکار به fallback mode می‌رود
```

### مشکل: "White screen / CSP error"
**راه‌حل:**
```
این PR این مشکل را برطرف کرده:
1. Clear کن browser cache
2. رفرش کن صفحه (Ctrl+Shift+R)
3. بررسی کن Console (F12) - نباید CSP error باشد
```

---

## 📊 مانیتورینگ

### چک لیست روزانه:
- [ ] تعداد AI requests در Atlas Dashboard
- [ ] Error log برای undefined array key
- [ ] Database table count (باید 20 جدول باشد)
- [ ] API quota remaining

### دستورات مفید:
```bash
# تعداد جداول
wp db query "SHOW TABLES LIKE 'wp_homa%'" --allow-root

# آخرین errors
tail -f wp-content/debug.log

# Database health check
wp eval "HomayeTabesh\HT_Activator::check_and_repair_database();" --allow-root
```

---

## 🎓 مستندات بیشتر

برای اطلاعات تکمیلی:
- 📖 [PR23-CRITICAL-FIXES-SUMMARY.md](PR23-CRITICAL-FIXES-SUMMARY.md) - مستندات کامل فنی
- 📖 [README.md](README.md) - راهنمای کلی افزونه
- 📖 [INSTALL.md](INSTALL.md) - راهنمای نصب

---

## ❓ سوالات متداول (FAQ)

### Q: آیا باید دیتابیس را backup کنم؟
**A:** همیشه بله! ولی این PR safe است و backward compatible.

### Q: چه نسخه PHP نیاز دارم؟
**A:** PHP 8.2 یا بالاتر (الزامی)

### Q: آیا با تم‌های دیگر (غیر Divi) کار می‌کند؟
**A:** بله، ولی برخی ویژگی‌های Divi-specific کار نمی‌کنند.

### Q: آیا WooCommerce الزامی است؟
**A:** خیر، اما بدون WooCommerce برخی features محدود می‌شوند.

### Q: چطوری بفهمم self-healing کار کرد؟
**A:** در Admin Notice می‌بینی: "سیستم خودترمیمی فعال شد. X جدول بازیابی شد."

---

## 🆘 دریافت کمک

اگر مشکلی داری:

1. **بررسی Error Log:**
   ```bash
   tail -100 wp-content/debug.log
   ```

2. **Enable Debug Mode:**
   ```php
   // در wp-config.php:
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. **باز کن Issue:**
   - برو به [GitHub Issues](https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh/issues)
   - توضیح بده مشکل را
   - بفرست error log

---

## ✨ چک لیست نهایی

قبل از production:
- [ ] ✅ API Key تنظیم شده
- [ ] ✅ Database tables check شده
- [ ] ✅ Test connection موفق بوده
- [ ] ✅ Error log تمیز است
- [ ] ✅ Backup database گرفته شده
- [ ] ✅ PHP version 8.2+ است

---

**تاریخ:** 2025-12-27  
**نسخه:** PR23  
**وضعیت:** ✅ Ready for Production  
**زمان نصب:** ~5 دقیقه
