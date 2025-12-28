# Chat Memory Quick Start Guide

## 🚀 راه‌اندازی سریع

### نصب و فعال‌سازی

```bash
# 1. Pull the branch
git pull origin copilot/implement-chat-memory-feature

# 2. Build React components (optional - files are already built)
npm install
npm run build

# 3. Activate plugin in WordPress
# Dashboard → Plugins → Homaye Tabesh → Deactivate → Activate
```

این کار جدول `wp_homaye_chat_memory` را در دیتابیس ایجاد می‌کند.

---

## ✅ بررسی سریع عملکرد

### 1. چک کردن جدول دیتابیس

```sql
-- در phpMyAdmin یا MySQL client
SHOW TABLES LIKE '%chat_memory%';
-- باید جدول wp_homaye_chat_memory را نمایش دهد

-- چک ساختار جدول
DESCRIBE wp_homaye_chat_memory;
```

### 2. تست در مرورگر (Browser Console)

```javascript
// باز کردن console: F12 → Console

// 1. بررسی session token
console.log('Session:', document.cookie.match(/homa_session_token=([^;]+)/)?.[1]);

// 2. بررسی API
fetch('/wp-json/homaye-tabesh/v1/chat/memory', {
  headers: { 'X-WP-Nonce': window.homayeParallelUIConfig?.nonce }
})
.then(r => r.json())
.then(d => console.log('✅ API Working:', d.success));
```

### 3. تست عملی

1. باز کردن sidebar هما
2. ارسال یک پیام: "سلام"
3. Refresh صفحه
4. **انتظار:** پیام شما همچنان موجود است

---

## 🔍 دیباگ سریع

### مشکل: پیامها ذخیره نمی‌شوند

```sql
-- بررسی تعداد پیام‌ها
SELECT COUNT(*) FROM wp_homaye_chat_memory;

-- اگر 0 بود، چک کنید:
-- 1. جدول وجود دارد؟
SHOW TABLES LIKE '%chat_memory%';

-- 2. افزونه فعال است؟
-- 3. خطایی در error_log وجود دارد؟
```

### مشکل: Session همیشه تغییر می‌کند

```javascript
// در Console:
setInterval(() => {
  const token = document.cookie.match(/homa_session_token=([^;]+)/)?.[1];
  console.log('Session:', token);
}, 2000);

// session token نباید تغییر کند
```

### مشکل: Greeting تکرار می‌شود

```javascript
// چک کنید که تاریخچه وجود دارد:
fetch('/wp-json/homaye-tabesh/v1/chat/memory')
  .then(r => r.json())
  .then(d => {
    console.log('Has history:', d.has_history);
    console.log('Message count:', d.count);
  });

// اگر has_history = true اما greeting تکرار می‌شود:
// - Cache مرورگر را پاک کنید
// - Build جدید را اجرا کنید
```

---

## 📊 گزارش‌های مفید

### تعداد پیام‌های امروز

```sql
SELECT COUNT(*) as today_messages
FROM wp_homaye_chat_memory 
WHERE DATE(created_at) = CURDATE();
```

### آخرین پیام‌ها

```sql
SELECT 
    message_type,
    LEFT(message_content, 50) as preview,
    user_role,
    created_at
FROM wp_homaye_chat_memory 
ORDER BY created_at DESC 
LIMIT 10;
```

### Session های فعال

```sql
SELECT 
    session_id,
    user_role,
    COUNT(*) as messages,
    MAX(created_at) as last_active
FROM wp_homaye_chat_memory 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY session_id, user_role;
```

---

## 🧹 پاکسازی (در صورت نیاز)

### پاک کردن تاریخچه یک session

```sql
-- پیدا کردن session_id از cookie:
-- F12 → Console → document.cookie

-- پاک کردن پیام‌ها:
DELETE FROM wp_homaye_chat_memory 
WHERE session_id = 'YOUR_SESSION_ID';
```

### پاک کردن پیام‌های قدیمی

```sql
-- پیام‌های بیش از 7 روز قدیمی
DELETE FROM wp_homaye_chat_memory 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- بررسی تعداد پیام‌های باقیمانده
SELECT COUNT(*) FROM wp_homaye_chat_memory;
```

---

## 🎯 تست سناریوهای مهم

### تست 1: مهمان جدید

```
1. حالت Incognito باز کنید
2. به سایت بروید
3. Sidebar را باز کنید
4. پیام "سلام" را ببینید (greeting)
5. یک پیام بفرستید
6. صفحه را refresh کنید
7. ✅ پیام‌ها باید باشند، greeting تکرار نشود
```

### تست 2: ادمین

```
1. لاگین کنید
2. چند پیام بفرستید
3. لاگ اوت کنید
4. دوباره لاگین کنید
5. ✅ تمام پیام‌ها باید موجود باشند
```

### تست 3: چند Tab

```
1. یک Tab باز کنید و پیام بفرستید
2. Tab جدید باز کنید (همان سایت)
3. Sidebar را باز کنید
4. ✅ پیامها باید در هر دو tab موجود باشند
```

---

## 🔧 API Testing با curl

```bash
# دریافت Nonce
# Dashboard → Console → window.homayeParallelUIConfig.nonce

# تست GET
curl -X GET "https://your-site.com/wp-json/homaye-tabesh/v1/chat/memory" \
  -H "X-WP-Nonce: YOUR_NONCE"

# تست POST
curl -X POST "https://your-site.com/wp-json/homaye-tabesh/v1/chat/memory" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{"message_type":"user","message_content":"تست","ai_metadata":{}}'

# پاک کردن
curl -X POST "https://your-site.com/wp-json/homaye-tabesh/v1/chat/memory/clear" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

---

## 📁 فایل‌های مهم

```
includes/
  ├── HT_Activator.php           → ایجاد جدول
  ├── HT_Vault_Manager.php       → مدیریت حافظه
  ├── HT_Vault_REST_API.php      → API endpoints
  └── HT_Parallel_UI.php         → ذخیره خودکار

assets/react/components/
  └── HomaSidebar.jsx            → بارگذاری تاریخچه

Documentation/
  ├── CHAT-MEMORY-IMPLEMENTATION.md     → مستندات کامل
  └── PR-CHAT-MEMORY-SUMMARY-FA.md      → خلاصه فارسی
```

---

## 🆘 پشتیبانی

**خطاها در کجا چک شوند:**

1. **PHP Errors:**
   - `wp-content/debug.log` (اگر WP_DEBUG فعال باشد)
   - Error logs سرور

2. **JavaScript Errors:**
   - F12 → Console در مرورگر

3. **Database Errors:**
   - phpMyAdmin → SQL tab → اجرای query
   - یا از adminer.php استفاده کنید

**رایج‌ترین مشکلات:**

| مشکل | راه حل |
|------|--------|
| جدول وجود ندارد | افزونه را deactivate/activate کنید |
| Session تغییر می‌کند | Cookie settings را چک کنید |
| API کار نمی‌کند | Nonce را چک کنید |
| پیام ذخیره نمی‌شود | Error log را بررسی کنید |

---

## ✅ Checklist آماده‌سازی Production

- [ ] جدول database ایجاد شده
- [ ] npm install و build اجرا شده
- [ ] تست با مهمان انجام شد
- [ ] تست با کاربر لاگین شده انجام شد
- [ ] Session cookie به درستی set می‌شود
- [ ] پیامها بعد از refresh باقی می‌مانند
- [ ] Greeting فقط یک بار نمایش داده می‌شود
- [ ] API endpoints پاسخ صحیح می‌دهند
- [ ] Error log ها پاک هستند

---

**تاریخ:** 1403/10/08  
**وضعیت:** ✅ آماده استفاده

برای جزئیات بیشتر: `CHAT-MEMORY-IMPLEMENTATION.md`
