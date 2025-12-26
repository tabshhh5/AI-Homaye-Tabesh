# PR12 Implementation Guide

## پیاده‌سازی واحد خودمختار خدمات پس از فروش و ناظر کل

**Pull Request #12 - Final Integration**

---

## 📋 فهرست مطالب

1. [معرفی](#معرفی)
2. [معماری سیستم](#معماری-سیستم)
3. [مؤلفه‌های اصلی](#مؤلفههای-اصلی)
4. [نصب و پیکربندی](#نصب-و-پیکربندی)
5. [راهنمای استفاده](#راهنمای-استفاده)
6. [API Reference](#api-reference)
7. [تست و اعتبارسنجی](#تست-و-اعتبارسنجی)

---

## معرفی

PR12 دو سیستم حیاتی و یکپارچه را به افزونه همای تابش اضافه می‌کند:

### 1️⃣ Post-Purchase Automation (خودکارسازی پس از خرید)

سیستمی جامع برای مدیریت تجربه مشتری بعد از خرید:

```
مشتری خرید می‌کند
    ↓
هما به سفارش دسترسی پیدا می‌کند
    ↓
وضعیت را از پست/تیپاکس استعلام می‌دهد
    ↓
پاسخ به مشتری: "محمد جان، سفارشت الان در مرحله چاپه!"
    ↓
48 ساعت بعد از تحویل → پیامک نظرسنجی
    ↓
30 روز بعد → پیامک بازگشت + تخفیف ویژه
```

### 2️⃣ Global Plugin Inspector (ناظر کل افزونه‌ها)

سیستم هوشمند برای شناسایی و استخراج اطلاعات از افزونه‌های نصب شده:

```
اسکن افزونه‌های فعال
    ↓
استخراج متادیتا (تنظیمات، جداول، قابلیت‌ها)
    ↓
شنود Hookها و رویدادها
    ↓
تولید کانتکست برای AI
    ↓
هما میداند: "روی محصول X الان 50% تخفیف فعاله"
```

---

## معماری سیستم

### Phase 1: Post-Purchase Automation

#### 1. HT_Order_Tracker
```php
// رهگیری سفارش با شماره سفارش
$tracker = new HT_Order_Tracker();
$order = $tracker->get_order_status(123);

// رهگیری با شماره تلفن
$orders = $tracker->get_orders_by_phone('09123456789');
```

**قابلیت‌ها:**
- ✅ دسترسی به سفارشات WooCommerce
- ✅ استخراج اطلاعات کامل سفارش
- ✅ محاسبه درصد پیشرفت
- ✅ تولید پیام انسانی برای AI
- ✅ پشتیبانی از چندین سفارش

#### 2. HT_Shipping_API_Bridge
```php
// استعلام از پست
$bridge = new HT_Shipping_API_Bridge();
$status = $bridge->get_tracking_status('123456789', 'post');

// استعلام از تیپاکس
$status = $bridge->get_tracking_status('987654321', 'tipax');
```

**قابلیت‌ها:**
- ✅ اتصال به API پست ایران
- ✅ اتصال به API تیپاکس
- ✅ کش هوشمند (15 دقیقه)
- ✅ Fallback به داده شبیه‌سازی شده
- ✅ مدیریت Timeout و خطاها

#### 3. HT_Support_Ticketing
```php
// ایجاد تیکت از مکالمه
$ticketing = new HT_Support_Ticketing();
$result = $ticketing->create_ticket_from_conversation([
    'user_id' => 1,
    'message' => 'سفارشم مشکل داره و عصبانیم!',
    'context' => []
]);

// نتیجه:
// - دسته: شکایت از کیفیت
// - فوریت: بحرانی (Critical)
// - تیکت #123 ثبت شد
```

**قابلیت‌ها:**
- ✅ تشخیص خودکار دسته (6 دسته)
- ✅ تشخیص فوریت (4 سطح)
- ✅ تحلیل احساسات (Sentiment Analysis)
- ✅ نوتیفیکیشن به ادمین
- ✅ بدون نیاز به فرم

**دسته‌بندی‌های تیکت:**
- `quality_complaint`: شکایت از کیفیت
- `technical_issue`: مشکل فنی
- `shipping_inquiry`: استعلام ارسال
- `order_modification`: تغییر سفارش
- `refund_request`: درخواست بازگشت وجه
- `general_inquiry`: سوال عمومی

**سطوح فوریت:**
- `critical`: بحرانی (کلمات عصبانیت)
- `high`: فوری (کلمات فوریت)
- `medium`: متوسط (پیش‌فرض)
- `low`: عادی

#### 4. HT_Retention_Engine
```php
// زمان‌بندی نظرسنجی
$engine = new HT_Retention_Engine();
$engine->schedule_feedback_sms(order_id: 123);

// کمپین بازگشت مشتری
$result = $engine->send_retention_campaign();

// آمار بازگشت
$analytics = $engine->get_retention_analytics();
```

**قابلیت‌ها:**
- ✅ نظرسنجی خودکار (48 ساعت بعد)
- ✅ شناسایی مشتریان غیرفعال (30 روز)
- ✅ ارسال پیامک بازگشت
- ✅ تولید کد تخفیف یکتا
- ✅ آمار و گزارش‌دهی

**Cron Jobs:**
```php
// هر روز یکبار
wp-cron: homa_run_retention_campaign

// هنگام تکمیل سفارش
woocommerce_order_status_completed → schedule_feedback_sms
```

---

### Phase 2: Global Plugin Inspector

#### 1. HT_Plugin_Scanner
```php
// لیست افزونه‌های نصب شده
$scanner = new HT_Plugin_Scanner();
$plugins = $scanner->get_installed_plugins();

// افزونه‌های تحت نظر
$monitored = $scanner->get_monitored_plugins_details();

// اضافه کردن به مانیتورینگ
$scanner->add_monitored_plugin('woocommerce/woocommerce.php');
```

**قابلیت‌ها:**
- ✅ اسکن تمام افزونه‌های نصب شده
- ✅ شناسایی افزونه‌های فعال
- ✅ انتخاب افزونه‌های هدف
- ✅ تشخیص WooCommerce و Tabesh
- ✅ رابط کاربری در Atlas

#### 2. HT_Metadata_Mining_Engine
```php
// استخراج متادیتای تمام افزونه‌ها
$engine = new HT_Metadata_Mining_Engine();
$metadata = $engine->mine_all_plugins_metadata();

// استخراج متادیتای یک افزونه خاص
$woo_metadata = $engine->mine_plugin_metadata('woocommerce');

// تولید دانش برای AI
$knowledge = $engine->generate_knowledge_base($metadata);
```

**قابلیت‌ها:**
- ✅ استخراج تنظیمات از `wp_options`
- ✅ شناسایی جداول دیتابیس
- ✅ استخراج فکت‌های WooCommerce
- ✅ استخراج فکت‌های Tabesh
- ✅ کش هوشمند (6 ساعت)
- ✅ به‌روزرسانی خودکار

**نمونه خروجی:**
```json
{
  "woocommerce": {
    "facts": {
      "order_statuses": ["تکمیل شده", "در حال پردازش"],
      "payment_methods": ["bacs", "cheque", "cod"],
      "currency": "IRR",
      "products_count": 150
    }
  }
}
```

#### 3. HT_Hook_Observer_Service
```php
// شروع مانیتورینگ Hookها
$observer = new HT_Hook_Observer_Service();
$observer->init_observers();

// دریافت رویدادهای اخیر
$events = $observer->get_recent_events(10);

// رویدادهای یک Hook خاص
$woo_events = $observer->get_hook_events('woocommerce_order_status_changed');
```

**قابلیت‌ها:**
- ✅ شنود 15+ Hook مهم
- ✅ ثبت خودکار رویدادها
- ✅ مدیریت تغییر وضعیت سفارش
- ✅ پشتیبانی از Hookهای Tabesh
- ✅ به‌روزرسانی دانش هما

**Hookهای تحت نظر:**
```php
// WooCommerce
- woocommerce_order_status_changed
- woocommerce_new_order
- woocommerce_payment_complete

// Tabesh
- tabesh_order_approved
- tabesh_design_completed

// WordPress
- wp_login
- user_register
```

#### 4. HT_Dynamic_Context_Generator
```php
// تولید کانتکست کامل
$generator = new HT_Dynamic_Context_Generator();
$context = $generator->generate_full_context();

// کانتکست برای سوال خاص
$context = $generator->generate_query_specific_context(
    query: "سفارشم کجاست؟",
    user_id: 1
);

// کانتکست سبک (برای چت سریع)
$lightweight = $generator->generate_lightweight_context();
```

**قابلیت‌ها:**
- ✅ ترکیب اطلاعات از تمام منابع
- ✅ کانتکست WooCommerce
- ✅ کانتکست Tabesh
- ✅ رویدادهای اخیر
- ✅ اطلاعات کاربر
- ✅ بهینه‌سازی برای Gemini

**نمونه خروجی:**
```
=== بستر سیستم و قابلیت‌های فعال ===

افزونه‌های فعال و تحت نظارت:
✓ WooCommerce (8.5.2)
✓ WordPress SEO (20.9)

=== WOOCOMMERCE ===
قابلیت‌ها و تنظیمات:
- order_statuses: تکمیل شده, در حال پردازش
- payment_methods: bacs, cod
- currency: IRR
- products_count: 150

رویدادهای اخیر سیستم:
- woocommerce_order_status_changed در 2024-01-15 12:30:00
- woocommerce_payment_complete در 2024-01-15 12:29:45

=== اطلاعات کاربر فعلی ===
نام: محمد رضایی
سفارشات اخیر:
  - سفارش #123: تکمیل شده
  - سفارش #124: در حال پردازش
```

---

## نصب و پیکربندی

### گام 1: تنظیمات اولیه

افزونه بعد از فعال‌سازی به طور خودکار تمام کلاس‌ها را Initialize می‌کند.

### گام 2: تنظیم SMS Provider

در تنظیمات وردپرس، اطلاعات ملی‌پیامک را وارد کنید:

```php
// وردپرس › تنظیمات › همای تابش › SMS
ht_melipayamak_username: username
ht_melipayamak_password: password
ht_melipayamak_from_number: 50002710000000
ht_melipayamak_otp_pattern: کد الگوی OTP
ht_melipayamak_feedback_pattern: کد الگوی نظرسنجی
ht_melipayamak_retention_pattern: کد الگوی بازگشت
```

### گام 3: تنظیم Cron Jobs

تمام Cron Jobها خودکار تنظیم می‌شوند:

```php
// بررسی Cron Jobs
wp_next_scheduled('homa_run_retention_campaign')
wp_next_scheduled('homa_refresh_plugin_metadata')
wp_next_scheduled('homa_cleanup_hook_events')
```

### گام 4: انتخاب افزونه‌های مانیتور

از طریق Atlas Control Center:

```
Atlas › Plugin Inspector › Select Plugins
```

یا از طریق کد:

```php
$scanner = HT_Core::instance()->plugin_scanner;
$scanner->add_monitored_plugin('woocommerce/woocommerce.php');
$scanner->add_monitored_plugin('tabesh-order-system/tabesh.php');
```

---

## راهنمای استفاده

### استفاده در مکالمه با هما

#### سناریو 1: رهگیری سفارش
```
کاربر: سفارشم کجاست؟

هما:
1. شناسایی سوال → رهگیری سفارش
2. چک کردن OTP (در صورت نیاز)
3. استعلام از Order Tracker
4. استعلام از Shipping Bridge
5. پاسخ: "محمد جان، سفارش #123 الان در مرحله چاپ است و 
         کد رهگیری ندارد. بزودی ارسال می‌شود."
```

#### سناریو 2: شکایت از کیفیت
```
کاربر: سفارشم خیلی بد بود، عصبانیم!

هما:
1. تشخیص احساس → عصبانیت
2. ایجاد تیکت خودکار
   - دسته: quality_complaint
   - فوریت: critical
3. نوتیفیکیشن به مدیر
4. پاسخ: "متاسفم محمد جان! تیکت شما با اولویت بحرانی 
         ثبت شد. تیم ما حداکثر ظرف 30 دقیقه بررسی می‌کنند."
```

#### سناریو 3: سوال درباره تخفیف
```
کاربر: روی فاکتور تخفیف داری؟

هما:
1. دسترسی به Context Generator
2. استخراج اطلاعات از WooCommerce Metadata
3. چک کردن تخفیف‌های فعال
4. پاسخ: "بله محمد جان! الان روی فاکتورها 20% تخفیف فعاله. 
         همین الان سفارش بده!"
```

### استفاده از API

#### Track Order
```javascript
fetch('/wp-json/homaye-tabesh/v1/order/track', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    order_id: 123
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

#### Track Shipping
```javascript
fetch('/wp-json/homaye-tabesh/v1/shipping/track', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    tracking_code: '123456789',
    service: 'post'
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

#### Create Ticket
```javascript
fetch('/wp-json/homaye-tabesh/v1/support/ticket', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    message: 'سفارشم مشکل داره',
    context: {source: 'chat'}
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

---

## API Reference

### POST `/homaye-tabesh/v1/order/track`

رهگیری سفارش با شماره سفارش یا تلفن.

**Request Body:**
```json
{
  "order_id": 123,
  // OR
  "phone": "09123456789"
}
```

**Response:**
```json
{
  "success": true,
  "order_id": 123,
  "status": "processing",
  "status_label": "در حال پردازش",
  "tracking_code": "هنوز صادر نشده",
  "customer_name": "محمد رضایی",
  "progress_percentage": 30,
  "human_message": "محمد جان، سفارش 123 در حال پردازش است..."
}
```

### POST `/homaye-tabesh/v1/shipping/track`

استعلام وضعیت رهگیری از پست یا تیپاکس.

**Request Body:**
```json
{
  "tracking_code": "123456789",
  "service": "post"
}
```

**Response:**
```json
{
  "success": true,
  "service": "post",
  "tracking_code": "123456789",
  "status": "in_transit",
  "status_label": "در حال ارسال",
  "last_update": "2024-01-15 12:30:00",
  "human_message": "مرسوله با کد 123456789 در وضعیت «در حال ارسال» است."
}
```

### POST `/homaye-tabesh/v1/support/ticket`

ایجاد تیکت پشتیبانی از مکالمه.

**Request Body:**
```json
{
  "message": "سفارشم مشکل داره و عصبانیم!",
  "context": {}
}
```

**Response:**
```json
{
  "success": true,
  "ticket_id": 456,
  "category": "quality_complaint",
  "category_label": "شکایت از کیفیت",
  "urgency": "critical",
  "urgency_label": "بحرانی",
  "message": "تیکت پشتیبانی با موفقیت ثبت شد..."
}
```

### GET `/homaye-tabesh/v1/retention/analytics`

دریافت آمار کمپین بازگشت مشتری. (Admin Only)

**Response:**
```json
{
  "success": true,
  "inactive_customers": 15,
  "retention_sms_sent": 120,
  "feedback_sms_sent": 350,
  "estimated_return_rate": "45%",
  "last_campaign_run": "2024-01-15 08:00:00"
}
```

### GET `/homaye-tabesh/v1/plugins/scan`

اسکن افزونه‌های نصب شده. (Admin Only)

**Response:**
```json
{
  "success": true,
  "plugins": [
    {
      "path": "woocommerce/woocommerce.php",
      "name": "WooCommerce",
      "version": "8.5.2",
      "is_active": true,
      "is_monitored": true
    }
  ]
}
```

### GET `/homaye-tabesh/v1/plugins/metadata`

دریافت متادیتای افزونه‌ها. (Admin Only)

**Query Params:**
- `refresh`: true/false (بازنشانی کش)

**Response:**
```json
{
  "success": true,
  "metadata": {
    "woocommerce": {
      "facts": {
        "order_statuses": ["completed", "processing"],
        "currency": "IRR"
      }
    }
  },
  "knowledge_base": "دانش استخراج شده از افزونه‌ها:\n..."
}
```

---

## تست و اعتبارسنجی

### روش 1: استفاده از فایل HTML

فایل `validate-pr12.html` را در مرورگر باز کنید:

```
http://yoursite.com/wp-content/plugins/homaye-tabesh/validate-pr12.html
```

### روش 2: تست از طریق کد PHP

```php
// تست Order Tracker
$tracker = HT_Core::instance()->order_tracker;
$result = $tracker->get_order_status(123);
var_dump($result);

// تست Ticketing
$ticketing = HT_Core::instance()->support_ticketing;
$ticket = $ticketing->create_ticket_from_conversation([
    'user_id' => 1,
    'message' => 'تست تیکت فوری',
    'context' => []
]);
var_dump($ticket);

// تست Plugin Scanner
$scanner = HT_Core::instance()->plugin_scanner;
$plugins = $scanner->get_installed_plugins();
var_dump($plugins);
```

### روش 3: تست REST API با cURL

```bash
# Track Order
curl -X POST http://yoursite.com/wp-json/homaye-tabesh/v1/order/track \
  -H "Content-Type: application/json" \
  -d '{"order_id": 123}'

# Track Shipping
curl -X POST http://yoursite.com/wp-json/homaye-tabesh/v1/shipping/track \
  -H "Content-Type: application/json" \
  -d '{"tracking_code": "123456789", "service": "post"}'

# Create Ticket
curl -X POST http://yoursite.com/wp-json/homaye-tabesh/v1/support/ticket \
  -H "Content-Type: application/json" \
  -d '{"message": "تست تیکت"}'
```

---

## ملاحظات امنیتی

### 1. احراز هویت
- رهگیری سفارش نیاز به OTP دارد (در نسخه بعدی)
- API های Admin فقط برای مدیران

### 2. حذف داده‌های حساس
```php
// در Plugin Scanner
if ($this->is_sensitive_option($option_name)) {
    continue; // Skip passwords, API keys, etc.
}
```

### 3. Rate Limiting
- API استعلام پست/تیپاکس: Cache 15 دقیقه
- SMS بازگشت: حداکثر 50 پیامک در هر اجرا

---

## Performance Optimization

### 1. Caching Strategy
```php
// Metadata: 6 ساعت
set_transient('ht_plugin_metadata_cache', $metadata, 6 * HOUR_IN_SECONDS);

// Shipping: 15 دقیقه
set_transient('ht_shipping_tracking_' . $code, $result, 15 * MINUTE_IN_SECONDS);
```

### 2. Database Optimization
- Index روی جداول جدید
- Cleanup رویدادهای قدیمی (>30 روز)

### 3. Cron Optimization
- Retention: روزانه
- Metadata Refresh: هر 12 ساعت
- Events Cleanup: هفتگی

---

## Troubleshooting

### مشکل: API پست/تیپاکس جواب نمیده
**راه‌حل:** سیستم به صورت خودکار به داده شبیه‌سازی شده سوئیچ می‌کند.

### مشکل: تیکت ایجاد نمی‌شود
**راه‌حل:** بررسی وجود جدول `wp_homa_support_tickets`

### مشکل: Cron اجرا نمی‌شود
**راه‌حل:**
```php
wp_cron(); // اجرای دستی
```

---

## آینده و توسعه

### نسخه‌های بعدی:
- [ ] اتصال واقعی به API پست
- [ ] پنل مدیریت تیکت‌ها در Atlas
- [ ] گزارش‌های تفصیلی بازگشت مشتری
- [ ] پشتیبانی از افزونه‌های بیشتر

---

**نسخه:** 1.0.0  
**تاریخ:** 2024-01-15  
**نویسنده:** Tabshhh4
