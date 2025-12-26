# PR12: Post-Purchase Automation & Global Plugin Inspector

> **Final Integration** - پیادهسازی واحد خودمختار خدمات پس از فروش و ناظر کل سیستم

---

## 📌 خلاصه

این PR دو سیستم حیاتی را به همای تابش اضافه می‌کند:

1. **Post-Purchase Automation**: مدیریت کامل تجربه مشتری بعد از خرید
2. **Global Plugin Inspector**: شناسایی و استخراج هوشمند اطلاعات از افزونه‌ها

---

## 🎯 مشکل و راه‌حل

### مشکل
اکثر سایت‌ها بعد از خرید، ارتباط با مشتری را قطع می‌کنند و اطلاعات افزونه‌های نصب شده را در اختیار AI ندارند.

### راه‌حل
هما حالا می‌تواند:
- ✅ وضعیت سفارش را از دیتابیس و API پست بخواند
- ✅ به سوالات رهگیری بدون نیاز به اپراتور پاسخ دهد
- ✅ تیکت پشتیبانی از مکالمه ایجاد کند
- ✅ مشتریان قدیمی را با پیامک بازگرداند
- ✅ قابلیت‌های افزونه‌ها را شناسایی کند
- ✅ کانتکست غنی برای AI تولید کند

---

## 📦 محتویات

### Phase 1: Post-Purchase Automation

| کلاس | مسئولیت | فایل |
|------|---------|------|
| `HT_Order_Tracker` | رهگیری هوشمند سفارش | `HT_Order_Tracker.php` |
| `HT_Shipping_API_Bridge` | اتصال به پست/تیپاکس | `HT_Shipping_API_Bridge.php` |
| `HT_Support_Ticketing` | تیکتینگ بدون فرم | `HT_Support_Ticketing.php` |
| `HT_Retention_Engine` | بازگشت مشتری | `HT_Retention_Engine.php` |

### Phase 2: Global Plugin Inspector

| کلاس | مسئولیت | فایل |
|------|---------|------|
| `HT_Plugin_Scanner` | اسکن افزونه‌ها | `HT_Plugin_Scanner.php` |
| `HT_Metadata_Mining_Engine` | استخراج متادیتا | `HT_Metadata_Mining_Engine.php` |
| `HT_Hook_Observer_Service` | شنونده Hookها | `HT_Hook_Observer_Service.php` |
| `HT_Dynamic_Context_Generator` | تولید کانتکست AI | `HT_Dynamic_Context_Generator.php` |

### REST API

| Endpoint | متد | توضیح |
|----------|-----|-------|
| `/order/track` | POST | رهگیری سفارش |
| `/shipping/track` | POST | رهگیری مرسوله |
| `/support/ticket` | POST | ایجاد تیکت |
| `/support/tickets` | GET | لیست تیکت‌های کاربر |
| `/retention/analytics` | GET | آمار بازگشت |
| `/plugins/scan` | GET | اسکن افزونه‌ها |
| `/plugins/monitor` | POST | مدیریت مانیتورینگ |
| `/plugins/metadata` | GET | متادیتای افزونه‌ها |

---

## 🚀 نصب و راه‌اندازی

### پیش‌نیاز
- WordPress 6.0+
- PHP 8.2+
- WooCommerce (برای قابلیت سفارش)
- Homaye Tabesh Core

### نصب خودکار
بعد از merge، تمام قابلیت‌ها خودکار فعال می‌شوند.

### تنظیمات اختیاری
```php
// تنظیمات SMS
ht_melipayamak_username
ht_melipayamak_password
ht_melipayamak_from_number

// الگوهای پیامک
ht_melipayamak_otp_pattern
ht_melipayamak_feedback_pattern
ht_melipayamak_retention_pattern

// پیکربندی API
ht_tipax_api_key
```

---

## 💡 مثال‌های کاربردی

### 1. رهگیری خودکار سفارش

```php
$tracker = HT_Core::instance()->order_tracker;

// با شماره سفارش
$order = $tracker->get_order_status(123);

// با شماره تلفن
$orders = $tracker->get_orders_by_phone('09123456789');

// نتیجه شامل:
// - وضعیت سفارش
// - کد رهگیری
// - درصد پیشرفت
// - پیام انسانی برای AI
```

### 2. استعلام از پست/تیپاکس

```php
$bridge = HT_Core::instance()->shipping_bridge;

// استعلام از پست
$status = $bridge->get_tracking_status('123456789', 'post');

// استعلام از تیپاکس
$status = $bridge->get_tracking_status('987654321', 'tipax');

// نتیجه شامل:
// - وضعیت فعلی
// - آخرین به‌روزرسانی
// - رویدادها
// - پیام انسانی
```

### 3. تیکتینگ هوشمند

```php
$ticketing = HT_Core::instance()->support_ticketing;

$result = $ticketing->create_ticket_from_conversation([
    'user_id' => 1,
    'message' => 'سفارشم خیلی بد بود، عصبانیم!',
    'context' => []
]);

// سیستم خودکار:
// ✓ دسته را تشخیص می‌دهد: quality_complaint
// ✓ فوریت را تعیین می‌کند: critical
// ✓ به مدیر نوتیفیکیشن می‌فرستد
// ✓ تیکت را ثبت می‌کند
```

### 4. کمپین بازگشت مشتری

```php
$engine = HT_Core::instance()->retention_engine;

// شناسایی مشتریان غیرفعال
$inactive = $engine->find_inactive_customers(30); // 30 روز

// ارسال کمپین
$result = $engine->send_retention_campaign();

// آمار
$analytics = $engine->get_retention_analytics();
```

### 5. اسکن افزونه‌ها

```php
$scanner = HT_Core::instance()->plugin_scanner;

// لیست افزونه‌های نصب شده
$plugins = $scanner->get_installed_plugins();

// افزونه‌های تحت نظر
$monitored = $scanner->get_monitored_plugins_details();

// اضافه به مانیتورینگ
$scanner->add_monitored_plugin('woocommerce/woocommerce.php');
```

### 6. استخراج متادیتا

```php
$engine = HT_Core::instance()->metadata_engine;

// استخراج از تمام افزونه‌ها
$metadata = $engine->mine_all_plugins_metadata();

// استخراج از یک افزونه خاص
$woo_data = $engine->mine_plugin_metadata('woocommerce');

// تولید دانش برای AI
$knowledge = $engine->generate_knowledge_base($metadata);
```

### 7. کانتکست پویا برای AI

```php
$generator = HT_Core::instance()->context_generator;

// کانتکست کامل
$context = $generator->generate_full_context();

// کانتکست برای سوال خاص
$context = $generator->generate_query_specific_context(
    query: "سفارشم کجاست؟",
    user_id: 1
);

// کانتکست سبک (Fast Mode)
$lightweight = $generator->generate_lightweight_context();
```

---

## 📊 فلوچارت‌ها

### Flow 1: رهگیری سفارش

```
کاربر: "سفارشم کجاست؟"
    ↓
هما تشخیص می‌دهد: order_tracking
    ↓
شناسایی کاربر (OTP اگر لازم باشد)
    ↓
Order Tracker → دیتابیس WooCommerce
    ↓
کد رهگیری دارد؟
    ↓ بله
Shipping Bridge → API پست/تیپاکس
    ↓
تولید پاسخ انسانی
    ↓
"محمد جان، سفارش #123 الان در مرحله چاپ است..."
```

### Flow 2: تیکت خودکار

```
کاربر: "سفارشم خیلی بد بود!"
    ↓
Support Ticketing → تحلیل متن
    ↓
تشخیص دسته: quality_complaint
تشخیص فوریت: critical (کلمه "بد")
    ↓
ایجاد تیکت در دیتابیس
    ↓
SMS به مدیر (فوریت بالا)
    ↓
پاسخ به کاربر
```

### Flow 3: بازگشت مشتری

```
Cron: homa_run_retention_campaign (روزانه)
    ↓
Retention Engine → جستجوی مشتریان غیرفعال
    ↓
Query: آخرین سفارش > 30 روز پیش
    ↓
تولید کد تخفیف یکتا
    ↓
SMS Provider → ارسال پیامک بازگشت
    ↓
ثبت متادیتا (جلوگیری از ارسال مجدد)
```

### Flow 4: استخراج متادیتا

```
Cron: homa_refresh_plugin_metadata (12 ساعت)
    ↓
Plugin Scanner → لیست افزونه‌های تحت نظر
    ↓
Metadata Engine → برای هر افزونه:
    ↓
1. استخراج Options (wp_options)
2. اسکن Tables (wp_*)
3. استخراج Facts (خاص افزونه)
    ↓
ذخیره در Cache (6 ساعت)
    ↓
تولید Knowledge Base برای AI
```

---

## 🔒 امنیت

### 1. احراز هویت
- رهگیری سفارش: احتیاج به OTP
- API های Admin: فقط برای `manage_options`
- تیکت: لاگین اختیاری

### 2. حذف داده‌های حساس
```php
// Plugin Scanner خودکار فیلتر می‌کند:
- password
- api_key
- secret
- token
- private_key
```

### 3. Rate Limiting
- API پست/تیپاکس: Cache 15 دقیقه
- SMS بازگشت: حداکثر 50/run
- Metadata: Refresh هر 6 ساعت

### 4. SQL Injection Prevention
```php
$wpdb->prepare() // همه جا استفاده شده
```

---

## ⚡ Performance

### Caching Strategy
- **Metadata**: 6 ساعت (Transient)
- **Shipping**: 15 دقیقه (Transient)
- **Recent Facts**: 1 ساعت (Transient)

### Database Optimization
- Index روی تمام جداول
- Cleanup رویدادهای >30 روز
- Limit در Query ها

### Background Jobs
```php
// Cron Jobs
homa_run_retention_campaign (daily)
homa_refresh_plugin_metadata (twicedaily)
homa_cleanup_hook_events (weekly)
homa_send_feedback_sms (single_event +48h)
```

---

## 📈 Metrics & Analytics

### آمار بازگشت مشتری
```json
{
  "inactive_customers": 15,
  "retention_sms_sent": 120,
  "feedback_sms_sent": 350,
  "estimated_return_rate": "45%",
  "last_campaign_run": "2024-01-15 08:00:00"
}
```

### آمار تیکتینگ
- تعداد تیکت‌های باز
- تعداد تیکت‌های Critical
- میانگین زمان پاسخ

### آمار افزونه‌ها
- تعداد افزونه‌های تحت نظر
- آخرین Refresh متادیتا
- تعداد Hookهای ثبت شده

---

## 🧪 تست

### تست خودکار
```bash
# باز کردن فایل HTML تست
open validate-pr12.html
```

### تست دستی
```php
// تست Order Tracker
$tracker = HT_Core::instance()->order_tracker;
$order = $tracker->get_order_status(123);
var_dump($order);

// تست Plugin Scanner
$scanner = HT_Core::instance()->plugin_scanner;
$plugins = $scanner->get_installed_plugins();
var_dump($plugins);
```

### تست API
```bash
# cURL
curl -X POST http://site.com/wp-json/homaye-tabesh/v1/order/track \
  -d '{"order_id": 123}'
```

---

## 📚 مستندات

- **Implementation Guide**: `PR12-IMPLEMENTATION.md`
- **Quick Start**: `PR12-QUICKSTART.md`
- **Validation**: `validate-pr12.html`

---

## 🔄 ارتباط با PRهای قبلی

| PR | ارتباط | چگونه |
|----|--------|-------|
| PR11 | SMS Provider | استفاده از Homa_SMS_Provider |
| PR9 | Atlas API | نمایش آمار در داشبورد |
| PR7 | Vault | ذخیره کانتکست |
| PR1-6 | Core | استفاده از HT_Core |

---

## 🎉 نتیجه

با PR12، همای تابش حالا می‌تواند:

✅ **مشتری را بعد از خرید رها نکند**  
✅ **به سوالات رهگیری خودکار پاسخ دهد**  
✅ **تیکت هوشمند ایجاد کند**  
✅ **مشتریان قدیمی را بازگرداند**  
✅ **قابلیت‌های افزونه‌ها را بشناسد**  
✅ **کانتکست غنی تری برای AI داشته باشد**

**هما حالا یک دستیار پس از فروش کامل است! 🚀**

---

**Version**: 1.0.0  
**Author**: Tabshhh4  
**Date**: 2024-01-15  
**Status**: ✅ Ready for Review
