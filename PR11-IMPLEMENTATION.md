# PR11: Smart Lead Conversion & OTP Authentication Implementation

## 🎯 خلاصه

PR11 دو قابلیت حیاتی برای افزایش نرخ تبدیل (Conversion Rate) به پلاگین هما اضافه می‌کند:

1. **Smart Lead Conversion (تبدیل هوشمند لید)**: سیستم امتیازدهی و مدیریت سرنخ‌های فروش
2. **OTP Authentication (احراز هویت با کد یکبار مصرف)**: ثبت‌نام و ورود سریع بدون نیاز به فرم‌های پیچیده

## 🔥 مشکلاتی که حل می‌شوند

### قبل از PR11
- هما کاربر را راهنمایی می‌کرد اما **هیچ اطلاعات تماسی دریافت نمی‌کرد**
- اطلاعات ارزشمند چت‌ها **ضایع می‌شد** و هیچ پیگیری فروشی صورت نمی‌گرفت
- ثبت‌نام کاربران **پیچیده و زمان‌بر** بود (فرم‌های طولانی)
- تیم فروش از لیدهای داغ **بی‌خبر** می‌ماند

### بعد از PR11
- هما اطلاعات تماس را **در لحظه طلایی** (Moment of Truth) دریافت می‌کند
- هر لید **امتیازدهی می‌شود** (0-100) و وضعیت آن مشخص است (Hot/Warm/Cold)
- کاربر با **2 کلیک** (دریافت شماره موبایل + تایید OTP) ثبت‌نام می‌کند
- تیم فروش برای لیدهای Hot به صورت **Real-time** مطلع می‌شود (SMS/Email)
- سفارش پیش‌نویس **خودکار** در ووکامرس ایجاد می‌شود

---

## 📐 معماری و اجزای سیستم

### 1. Lead Scoring Algorithm (الگوریتم امتیازدهی)

**فایل:** `includes/HT_Lead_Scoring_Algorithm.php`

#### پارامترهای امتیازدهی:

1. **منبع ورودی (Source Referral)** - تا 18 امتیاز
   - Referral (معرفی): 18
   - Instagram/Telegram: 15
   - Google Ads: 12
   - Organic: 5

2. **تیراژ سفارش (Volume)** - تا 25 امتیاز
   - +10000: 25
   - 5000-10000: 20
   - 1000-5000: 15
   - 500-1000: 10
   - <500: 5

3. **نوع محصول (Product Type)** - تا 15 امتیاز
   - طلاکوب (Gold Foil): 15
   - UV Coating: 12
   - لمینت: 8
   - چاپ استاندارد: 5

4. **میزان تعامل (Engagement)** - تا 18 امتیاز
   - تعداد پیام‌های چت: تا 10 امتیاز
   - مشاهده محصولات: تا 8 امتیاز

5. **کامل بودن اطلاعات (Completeness)** - تا 30 امتیاز
   - شماره تماس: 10
   - نام: 5
   - مشخصات فنی: 8
   - بودجه: 7

6. **سرعت تصمیم‌گیری (Decision Speed)** - تا 10 امتیاز
   - <5 دقیقه: 10
   - <10 دقیقه: 7
   - <30 دقیقه: 5

**مجموع: 0-100 امتیاز**

#### وضعیت لید:
```php
80-100: Hot (داغ) - اولویت فوری ⚡
60-79:  Warm (گرم) - اولویت بالا 🔥
40-59:  Medium (متوسط) - اولویت متوسط 💼
0-39:   Cold (سرد) - اولویت پایین ❄️
```

### 2. OTP Core Engine (موتور احراز هویت)

**فایل:** `includes/Homa_OTP_Core_Engine.php`

#### فرآیند احراز هویت:

```
Stage 1: دریافت شماره موبایل
    ↓
تولید کد 6 رقمی تصادفی
    ↓
ذخیره در دیتابیس + Transient
    ↓
ارسال پیامک (ملی‌پیامک)
    ↓
Stage 2: دریافت کد از کاربر
    ↓
اعتبارسنجی کد
    ↓
بررسی User قبلی
    ↓
ساخت User جدید یا Login
    ↓
Silent Login (بدون رفرش صفحه)
```

#### امنیت:
- **انقضا:** کد بعد از 2 دقیقه منقضی می‌شود
- **تعداد تلاش:** حداکثر 5 بار
- **Rate Limiting:** حداکثر 3 درخواست در ساعت
- **Cron Job:** پاکسازی خودکار کدهای منقضی

### 3. MeliPayamak SMS Provider

**فایل:** `includes/Homa_SMS_Provider.php`

این کلاس مسئول ارتباط با API ملی‌پیامک است و از **Pattern-based SMS** استفاده می‌کند.

#### چرا الگو (Pattern)?
- جلوگیری از **بلک‌لیست شدن** توسط مخابرات
- سرعت بیشتر در ارسال
- متن استانداردشده و حرفه‌ای

#### متدهای کلیدی:
```php
send_otp($phone_number, $otp_code)           // ارسال کد تایید
send_lead_notification($admin_phone, $data)  // اطلاع‌رسانی لید Hot
send_simple_sms($to, $message)               // فالبک بدون الگو
```

### 4. WooCommerce Draft Bridge

**فایل:** `includes/HT_WooCommerce_Draft_Bridge.php`

تبدیل داده‌های چت به سفارش پیش‌نویس در ووکامرس.

#### ساختار داده ورودی:
```php
$chat_data = [
    'user_id' => 123,
    'contact_name' => 'علی احمدی',
    'contact_info' => '09123456789',
    'requirements' => [
        'volume' => 5000,
        'paper_type' => 'گلاسه 150 گرم',
        'print_type' => 'چاپ افست',
        'coating' => 'سلفون براق',
    ],
    'lead_score' => 85,
    'source_referral' => 'instagram',
    'products' => [
        ['id' => 45, 'quantity' => 1],
    ],
];
```

#### خروجی:
- یک سفارش با وضعیت **Pending** در ووکامرس
- یادداشت سفارش شامل تمام مشخصات درخواستی
- متاداده: `_homa_generated`, `_homa_lead_score`, `_homa_requirements`

### 5. Sales Notification Service

**فایل:** `includes/HT_Sales_Notification_Service.php`

سیستم اطلاع‌رسانی چندکاناله برای لیدهای Hot (امتیاز ≥ 70).

#### کانال‌های اطلاع‌رسانی:
1. **SMS**: پیامک به شماره مدیر
2. **Email**: ایمیل با فرمت HTML زیبا
3. **Dashboard**: نوتیفیکیشن در داشبورد Atlas

#### نمونه ایمیل:
```
موضوع: 🔥 لید جدید با اولویت بالا - هما

امتیاز لید: 85/100
وضعیت: hot
نام: علی احمدی
تماس: 09123456789
منبع: instagram

مشخصات درخواست:
• تیراژ: 5000
• نوع کاغذ: گلاسه 150 گرم
• نوع چاپ: چاپ افست

[مشاهده سفارش پیش‌نویس]
```

---

## 🎨 React Components

### 1. LeadCaptureForm

**فایل:** `assets/react/components/LeadCaptureForm.jsx`

فرم دریافت اطلاعات تماس در چت.

#### Props:
```jsx
<LeadCaptureForm
    onSubmit={(data) => {/* ذخیره لید */}}
    onSkip={() => {/* رد کردن */}}
    initialData={{ contact_name: '', contact_info: '' }}
/>
```

#### Features:
- اعتبارسنجی Real-time
- Validation شماره موبایل ایران
- Loading state
- Error handling
- Mobile-optimized

### 2. OTPInput

**فایل:** `assets/react/components/OTPInput.jsx`

کامپوننت ورود کد تایید 6 رقمی.

#### Props:
```jsx
<OTPInput
    onComplete={(code) => {/* تایید کد */}}
    onResend={() => {/* ارسال مجدد */}}
    phoneNumber="09123456789"
    expiresIn={120}  // ثانیه
/>
```

#### Features:
- Auto-focus و Auto-submit
- شمارش معکوس
- Paste support
- Keyboard navigation (Arrow keys)
- انیمیشن خطا

### 3. PhoneNumberInput

**فایل:** `assets/react/components/OTPInput.jsx` (export شده)

Stage 1 - دریافت شماره موبایل.

#### Props:
```jsx
<PhoneNumberInput
    onSubmit={(phone) => {/* ارسال OTP */}}
    initialPhone=""
/>
```

---

## 🔌 REST API Endpoints

تمام Endpoints در namespace `homa/v1` قرار دارند.

### Authentication & OTP

#### `POST /otp/send`
ارسال کد OTP به شماره موبایل.

**Request:**
```json
{
  "phone_number": "09123456789",
  "session_token": "optional_token"
}
```

**Response:**
```json
{
  "success": true,
  "message": "کد تایید به شماره شما ارسال شد",
  "expires_in": 120
}
```

#### `POST /otp/verify`
تایید کد و ثبت‌نام/لاگین خودکار.

**Request:**
```json
{
  "phone_number": "09123456789",
  "otp_code": "123456",
  "user_data": {
    "first_name": "علی",
    "last_name": "احمدی"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "حساب کاربری شما ایجاد و وارد شدید",
  "action": "register",
  "user_id": 45
}
```

### Lead Management

#### `POST /leads`
ایجاد لید جدید.

**Request:**
```json
{
  "user_id_or_token": "user_123",
  "contact_name": "علی احمدی",
  "contact_info": "09123456789",
  "requirements_summary": {
    "volume": 5000,
    "product_type": "gold_foil"
  },
  "source_referral": "instagram",
  "volume": 5000,
  "product_type": "gold_foil",
  "engagement": {
    "message_count": 12,
    "viewed_products": 5
  }
}
```

**Response:**
```json
{
  "success": true,
  "lead_id": 78,
  "lead_score": 85,
  "lead_status": "hot",
  "message": "لید با موفقیت ثبت شد"
}
```

#### `GET /leads`
لیست تمام لیدها (فقط برای Admin).

**Query Params:**
- `per_page`: تعداد در هر صفحه (پیش‌فرض: 20)
- `page`: شماره صفحه
- `status`: فیلتر بر اساس وضعیت (hot/warm/medium/cold)

#### `POST /leads/{id}/draft-order`
ایجاد سفارش پیش‌نویس برای لید.

**Request:**
```json
{
  "products": [
    {"id": 45, "quantity": 1}
  ]
}
```

**Response:**
```json
{
  "success": true,
  "order_id": 234,
  "message": "سفارش پیش‌نویس با موفقیت ایجاد شد"
}
```

#### `POST /leads/calculate-score`
محاسبه امتیاز لید (بدون ذخیره).

**Request:**
```json
{
  "source_referral": "instagram",
  "volume": 5000,
  "product_type": "gold_foil",
  "engagement": {
    "message_count": 12
  }
}
```

**Response:**
```json
{
  "score": 85,
  "status": "hot",
  "needs_notification": true
}
```

---

## ⚙️ تنظیمات (Settings)

تنظیمات در بخش **Atlas → Settings** قابل دسترسی است.

### تنظیمات ملی‌پیامک:
- `ht_melipayamak_username`: نام کاربری
- `ht_melipayamak_password`: رمز عبور
- `ht_melipayamak_from_number`: شماره فرستنده
- `ht_melipayamak_otp_pattern`: کد الگوی OTP
- `ht_melipayamak_lead_notification_pattern`: کد الگوی اطلاع‌رسانی

### تنظیمات اطلاع‌رسانی:
- `ht_admin_phone_number`: شماره موبایل مدیر
- `ht_lead_notification_enabled`: فعال/غیرفعال اطلاع‌رسانی
- `ht_lead_hot_score_threshold`: حداقل امتیاز برای اطلاع‌رسانی (پیش‌فرض: 70)

---

## 🗄️ ساختار دیتابیس

### جدول `wp_homa_leads`

```sql
CREATE TABLE wp_homa_leads (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT DEFAULT NULL,
    user_id_or_token VARCHAR(100) NOT NULL,
    lead_score INT DEFAULT 0,
    lead_status VARCHAR(50) DEFAULT 'new',
    requirements_summary JSON DEFAULT NULL,
    contact_info VARCHAR(100),
    contact_name VARCHAR(100),
    source_referral VARCHAR(50) DEFAULT 'organic',
    draft_order_id BIGINT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY (user_id),
    KEY (lead_score),
    KEY (lead_status)
);
```

### جدول `wp_homa_otp`

```sql
CREATE TABLE wp_homa_otp (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    phone_number VARCHAR(20) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    session_token VARCHAR(100),
    attempts INT DEFAULT 0,
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    KEY (phone_number),
    KEY (expires_at)
);
```

---

## 🚀 نصب و راه‌اندازی

### 1. فعال‌سازی دیتابیس

دیتابیس به صورت خودکار در زمان Activation پلاگین ایجاد می‌شود:

```bash
# اگر پلاگین از قبل فعال است، دستی تریگر کنید:
wp plugin deactivate homaye-tabesh
wp plugin activate homaye-tabesh
```

### 2. تنظیمات ملی‌پیامک

1. وارد **Atlas → Settings** شوید
2. بخش **تنظیمات ملی‌پیامک** را پیدا کنید
3. اطلاعات زیر را وارد کنید:
   - نام کاربری و رمز عبور پنل ملی‌پیامک
   - شماره فرستنده
   - کد الگوهای پیامک (از پنل ملی‌پیامک دریافت کنید)
   - شماره موبایل مدیر
4. روی **ذخیره تنظیمات** کلیک کنید

### 3. Build Assets (React)

```bash
cd /path/to/homaye-tabesh
npm install
npm run build
```

### 4. تست OTP

```bash
# از Postman یا cURL:
curl -X POST http://yoursite.com/wp-json/homa/v1/otp/send \
  -H "Content-Type: application/json" \
  -d '{"phone_number":"09123456789"}'
```

---

## 🧪 تست‌ها و اعتبارسنجی

### تست امتیازدهی

```php
// تست در WordPress
$score = HT_Lead_Scoring_Algorithm::calculate_score([
    'source_referral' => 'instagram',
    'volume' => 5000,
    'product_type' => 'gold_foil',
    'engagement' => ['message_count' => 10],
    'contact_info' => '09123456789',
]);

echo "Lead Score: $score"; // Should be ~85
```

### تست OTP Flow

1. دریافت شماره موبایل
2. کد OTP را از دیتابیس بردارید:
```sql
SELECT otp_code FROM wp_homa_otp 
WHERE phone_number = '09123456789' 
ORDER BY created_at DESC LIMIT 1;
```
3. کد را وارد کنید
4. بررسی کنید User ساخته شده و لاگین شده باشد

### تست Draft Order

1. لید با امتیاز بالا ایجاد کنید
2. Endpoint `/leads/{id}/draft-order` را فراخوانی کنید
3. بررسی کنید سفارش در **ووکامرس → سفارش‌ها** ظاهر شده باشد

---

## ⚠️ ریسک‌ها و ملاحظات

### امنیت
- ✅ Rate Limiting برای OTP پیاده‌سازی شده
- ✅ Validation شماره موبایل ایران
- ✅ محدودیت تعداد تلاش
- ⚠️ پسوردهای ملی‌پیامک به صورت plain text ذخیره می‌شوند (باید Encrypt شوند)

### کارایی
- ✅ استفاده از Transient برای cache کردن OTP
- ✅ Cron Job برای پاکسازی خودکار
- ⚠️ فراخوانی SOAP هر بار ممکن است کند باشد (پیشنهاد: استفاده از Queue)

### تجربه کاربری
- ✅ Mobile-First Design
- ✅ انیمیشن‌ها و Feedback بصری
- ⚠️ اگر پیامک نرسد، کاربر گیر می‌کند (نیاز به fallback)

---

## 📊 متریک‌های موفقیت

- **Conversion Rate**: افزایش از X% به Y%
- **Lead Capture Rate**: درصد کاربرانی که اطلاعات تماس می‌دهند
- **OTP Success Rate**: درصد موفقیت تایید OTP
- **Hot Lead Response Time**: زمان پاسخ‌دهی به لیدهای داغ

---

## 🔜 توسعه‌های آینده

- [ ] افزودن Telegram Bot برای اطلاع‌رسانی
- [ ] Dashboard تحلیلی برای Lead Pipeline
- [ ] A/B Testing برای فرم‌های Lead Capture
- [ ] یکپارچه‌سازی با CRMهای محبوب (HubSpot, Salesforce)
- [ ] پشتیبانی از چند زبان در پیامک‌ها

---

## 📞 پشتیبانی

برای سوالات فنی یا گزارش باگ:
- GitHub Issues: https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh/issues
- Email: support@example.com
