# PR24 Quick Start Guide - راهنمای سریع استقرار

## 🚀 نحوه استقرار این PR

### گام 1: دریافت کد
```bash
git checkout copilot/fix-critical-server-errors
git pull origin copilot/fix-critical-server-errors
```

### گام 2: بررسی تغییرات
```bash
# مشاهده فایل‌های تغییر یافته
git diff main..copilot/fix-critical-server-errors --stat

# فایل‌های اصلی تغییر یافته:
# - includes/HT_Admin.php
# - includes/HT_Atlas_API.php
# - includes/HT_Activator.php
# - includes/HT_Console_Analytics_API.php
# - assets/js/homa-indexer.js
# - assets/js/homa-event-bus.js
# - assets/js/homa-input-observer.js
```

### گام 3: Merge به Main
```bash
git checkout main
git merge copilot/fix-critical-server-errors
```

### گام 4: استقرار در سرور
1. آپلود فایل‌ها به سرور
2. افزونه به صورت خودکار دیتابیس را به‌روزرسانی می‌کند
3. نیازی به فعال‌سازی مجدد نیست

### گام 5: تست عملکرد
```bash
# بررسی لاگ PHP
tail -f /var/log/php-error.log

# بررسی لاگ WordPress
tail -f wp-content/debug.log
```

## ✅ چک‌لیست پس از استقرار

### تست‌های اجباری:
- [ ] فعال‌سازی مجدد افزونه (برای اطمینان از migration)
- [ ] باز کردن داشبورد سوپر کنسول
- [ ] باز کردن مرکز کنترل اطلس
- [ ] تست Decision Simulator
- [ ] بررسی لاگ‌های PHP (نباید error باشد)
- [ ] بررسی کنسول مرورگر (نباید error باشد)

### تست‌های API:
```bash
# Test Console Analytics
curl https://yoursite.com/wp-json/homaye/v1/console/analytics

# Test System Status
curl https://yoursite.com/wp-json/homaye/v1/console/system/status

# Test Observer Status
curl https://yoursite.com/wp-json/homaye/v1/observer/status

# Test Decision Simulator
curl -X POST https://yoursite.com/wp-json/homaye/v1/atlas/decision/simulate \
  -H "Content-Type: application/json" \
  -d '{"decision_type":"test","current_value":100,"risk_level":0.5}'
```

### بررسی دیتابیس:
```sql
-- بررسی ستون‌های جدید در security_scores
DESCRIBE wp_homaye_security_scores;
-- باید user_id و threat_score را ببینید

-- بررسی ستون‌های جدید در knowledge_facts
DESCRIBE wp_homaye_knowledge_facts;
-- باید fact، category و tags را ببینید
```

## 🔧 عیب‌یابی

### مشکل: خطای 500 بعد از استقرار
**راه حل:**
1. بررسی لاگ PHP
2. فعال کردن WP_DEBUG
3. پاک کردن cache
4. فعال‌سازی مجدد افزونه

### مشکل: ستون‌های جدید در دیتابیس نیستند
**راه حل:**
```php
// در wp-admin/plugins.php افزونه را غیرفعال و دوباره فعال کنید
// یا از WP-CLI:
wp plugin deactivate homaye-tabesh
wp plugin activate homaye-tabesh
```

### مشکل: JavaScript کار نمی‌کند
**راه حل:**
1. پاک کردن cache مرورگر (Ctrl+Shift+R)
2. پاک کردن cache افزونه‌های caching
3. بررسی کنسول مرورگر برای errors

### مشکل: API ها 404 برمی‌گردانند
**راه حل:**
```php
// در WordPress Admin > Settings > Permalinks
// دکمه "Save Changes" را بزنید (فقط همین، نیازی به تغییر نیست)
```

## 📊 مشاهده نتایج

### بررسی عملکرد JavaScript:
1. باز کردن Chrome DevTools (F12)
2. رفتن به تب Console
3. باید پیام‌های زیر را ببینید:
```
[Homa Indexer] Initializing semantic mapping...
[Homa Event Bus] Registered listener for: indexer:ready
[Homa Input Observer] Mutation observer active (with debouncing)
```

### بررسی دیتابیس:
```sql
-- تعداد رکوردها
SELECT COUNT(*) FROM wp_homaye_knowledge_facts;
SELECT COUNT(*) FROM wp_homaye_security_scores;

-- تست کوئری با ستون‌های جدید
SELECT user_id, threat_score FROM wp_homaye_security_scores LIMIT 5;
SELECT fact, category, tags FROM wp_homaye_knowledge_facts LIMIT 5;
```

## 🎯 انتظارات از این PR

### چیزهایی که باید دیده شوند:
✅ هیچ PHP Fatal Error در لاگ  
✅ هیچ JavaScript error در کنسول  
✅ تمام پنل‌های admin کار می‌کنند  
✅ API ها داده برمی‌گردانند  
✅ سایت سریع‌تر لود می‌شود  
✅ Decision Simulator بدون خطا کار می‌کند  

### چیزهایی که نباید دیده شوند:
❌ خطای "number_format(): Argument #1 must be of type float"  
❌ خطای "Division by zero"  
❌ خطای "Unknown column 's.user_id'"  
❌ خطای "Unknown column 'category'"  
❌ خطای "Unknown column 'fact'"  
❌ "Listener already registered" warnings (حداقل 80% کمتر)  
❌ DOM scans مکرر و سریع  

## 🔄 Rollback (در صورت نیاز)

اگر مشکلی پیش آمد:

```bash
# بازگشت به نسخه قبلی
git checkout main
git reset --hard HEAD~6

# یا revert کردن commits
git revert 0529eef..2444a84
```

**توجه:** Rollback باعث از دست رفتن تمام فیکس‌ها می‌شود!

## 📞 پشتیبانی

در صورت بروز هر مشکلی:
1. لاگ‌های PHP را ذخیره کنید
2. Screenshot از error ها بگیرید
3. در GitHub Issues گزارش دهید

## 🎉 موفقیت‌آمیز بود؟

اگر همه چیز خوب کار کرد:
- ⭐ به repo ما ستاره بدهید
- 📝 تجربه خود را به اشتراک بگذارید
- 🐛 bug های دیگر را گزارش دهید

---

**آخرین به‌روزرسانی:** 2025-12-28  
**نسخه:** PR24  
**وضعیت:** ✅ Production Ready
