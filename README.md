# همای تابش (Homaye Tabesh) - AI Assistant for WordPress

افزونه وردپرس همای تابش، هاب هوشمند هماهنگی، تصمیم‌گیری و راهنمایی تمام فرآیندهای کاربران وبسایت است.

## نمای کلی

همای تابش یک افزونه هوشمند وردپرس است که با استفاده از Gemini 2.5 Flash API و ردیابی رفتار کاربران، تجربه شخصی‌سازی شده‌ای را برای بازدیدکنندگان وبسایت فراهم می‌کند. این افزونه به ویژه برای وبسایت‌های تجاری و فروشگاه‌های آنلاین طراحی شده است.

## ویژگی‌های اصلی

### 1. معماری ماژولار (Modular Architecture)
- ساختار PSR-4 برای بارگذاری خودکار کلاس‌ها
- کلاس Singleton اصلی (`HT_Core`) برای مدیریت تمام زیرسیستم‌ها
- معماری قابل توسعه و نگهداری آسان

### 2. موتور استنتاج Gemini 2.5 Flash
- پشتیبانی از Structured JSON Outputs
- تزریق Context برای محصولات WooCommerce
- مکانیزم Fallback در صورت خطا
- پشتیبانی از System Instructions سفارشی

### 3. سیستم Telemetry اختصاصی
- ردیابی رفتار کاربران روی المان‌های Divi
- REST API Gateway: `/wp-json/homaye/v1/telemetry`
- پشتیبانی از Batch Processing برای کاهش درخواست‌های HTTP
- سازگاری کامل با Divi Visual Builder

### 4. موتور پرسونا و امتیازدهی
- شناسایی خودکار پرسونای کاربر (نویسنده، کسب‌وکار، طراح، دانشجو)
- Lead Scoring بر اساس رفتار
- ذخیره‌سازی در دیتابیس WordPress
- تداوم Session در طول بازدید

### 5. پایگاه دانش (Knowledge Base)
- قوانین بیزینس در فایل‌های JSON
- تبدیل خودکار به System Instructions
- قابل تنظیم و سفارشی‌سازی بدون تغییر کد

## نیازمندی‌های سیستم

- PHP >= 8.2
- WordPress >= 6.0
- Composer
- (اختیاری) Divi Theme برای بهترین تجربه
- (اختیاری) WooCommerce برای Context محصولات

## نصب

### برای کاربران نهایی (نصب ساده)

1. **دانلود نسخه آماده**: از بخش [Releases](https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh/releases) آخرین نسخه را دانلود کنید
2. **آپلود به وردپرس**: فایل ZIP را از طریق پنل مدیریت وردپرس (افزونه‌ها > افزودن) آپلود کنید
3. **فعال‌سازی**: افزونه را فعال کنید
4. **تنظیمات**: کلید API گوگل Gemini را در تنظیمات وارد کنید

> **نکته مهم**: نسخه Release شامل تمام فایل‌های لازم است و نیازی به نصب Composer ندارد.

### برای توسعه‌دهندگان (نصب از سورس)

اگر می‌خواهید روی کد کار کنید یا از آخرین نسخه development استفاده کنید:

1. مخزن را کلون کنید:
   ```bash
   git clone https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh.git homaye-tabesh
   cd homaye-tabesh
   ```

2. وابستگی‌ها را نصب کنید:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm install && npm run build
   ```

3. افزونه را از پنل مدیریت وردپرس فعال کنید
4. کلید API گوگل Gemini را در تنظیمات وارد کنید

> **توجه**: بدون اجرای `composer install`، افزونه از autoloader داخلی استفاده می‌کند که برای محیط production مناسب است.

## ساختار پروژه

```
homaye-tabesh/
├── homaye-tabesh.php          # فایل اصلی افزونه
├── includes/                   # کلاس‌های اصلی
│   ├── HT_Core.php            # هسته مرکزی
│   ├── HT_Gemini_Client.php   # کلاینت Gemini API
│   ├── HT_Telemetry.php       # سیستم ردیابی
│   ├── HT_Persona_Manager.php # مدیریت پرسونا
│   ├── HT_Knowledge_Base.php  # پایگاه دانش
│   ├── HT_Activator.php       # فعال‌سازی
│   └── HT_Deactivator.php     # غیرفعال‌سازی
├── assets/                     # فایل‌های استاتیک
│   ├── js/
│   │   └── tracker.js         # اسکریپت ردیابی
│   └── css/
│       └── admin.css          # استایل پنل مدیریت
├── knowledge-base/             # فایل‌های JSON
│   ├── personas.json          # قوانین پرسونا
│   ├── products.json          # اطلاعات محصولات
│   └── responses.json         # قوانین پاسخ‌دهی
└── composer.json              # وابستگی‌ها
```

## استفاده

### تنظیم API Key

در پنل مدیریت وردپرس:
```php
update_option('ht_gemini_api_key', 'YOUR_GEMINI_API_KEY');
```

### استفاده از موتور AI

```php
$core = \HomayeTabesh\HT_Core::instance();

// ارسال پرامپت به Gemini
$response = $core->brain->generate_content(
    'محصول مناسب برای چاپ کتاب چیست؟',
    [
        'products' => $core->brain->get_woocommerce_context(),
        'persona' => $core->memory->get_dominant_persona('user_123'),
    ]
);
```

### ردیابی رفتار سفارشی

در HTML:
```html
<div class="my-element" data-homaye-track="hover,click">
    محتوای قابل ردیابی
</div>
```

### دریافت امتیاز پرسونا

```php
$user_id = 'user_123';
$persona = $core->memory->get_dominant_persona($user_id);

echo "پرسونا: " . $persona['type'];
echo "امتیاز: " . $persona['score'];
echo "اطمینان: " . $persona['confidence'] . "%";
```

## REST API Endpoints

### POST `/wp-json/homaye/v1/telemetry`
ارسال یک رویداد ردیابی:
```json
{
  "event_type": "click",
  "element_class": "et_pb_button",
  "element_data": {
    "text": "خرید محصول"
  }
}
```

### POST `/wp-json/homaye/v1/telemetry/batch`
ارسال چند رویداد به صورت دسته‌جمعی:
```json
{
  "events": [
    {"event_type": "hover", "element_class": "price"},
    {"event_type": "click", "element_class": "add_to_cart"}
  ]
}
```

## سازگاری با Divi

افزونه به صورت خودکار المان‌های زیر را ردیابی می‌کند:
- `.et_pb_module` - تمام ماژول‌های Divi
- `.et_pb_pricing` - جداول قیمت
- `.et_pb_button` - دکمه‌ها
- `.et_pb_cta` - Call to Action
- `.et_pb_shop` - فروشگاه WooCommerce

## توسعه و سفارشی‌سازی

### افزودن پرسونای جدید

در `knowledge-base/personas.json`:
```json
{
  "new_persona": {
    "indicators": ["نشانه 1", "نشانه 2"],
    "recommendations": ["توصیه 1", "توصیه 2"],
    "threshold": 60
  }
}
```

### تغییر قوانین پاسخ‌دهی

در `knowledge-base/responses.json`:
```json
{
  "tone": "لحن دلخواه",
  "guidelines": ["دستورالعمل 1", "دستورالعمل 2"]
}
```

## امنیت

- استفاده از Strict Types در PHP 8.2
- اعتبارسنجی ورودی‌ها
- استفاده از Nonce برای REST API
- عدم ذخیره اطلاعات حساس در کد
- Sanitization تمام داده‌های کاربر

## مجوز

GPL v3 or later

## توسعه‌دهنده

Tabshhh4

## پشتیبانی

برای گزارش مشکلات یا پیشنهادات، به [Issues](https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh/issues) مراجعه کنید.
