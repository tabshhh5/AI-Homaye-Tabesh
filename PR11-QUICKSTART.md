# PR11 QuickStart: Smart Lead Conversion & OTP 🚀

راهنمای سریع راه‌اندازی سیستم تبدیل لید هوشمند و احراز هویت OTP

---

## ⏱️ زمان راه‌اندازی: 10 دقیقه

---

## گام 1: Build Assets (2 دقیقه)

```bash
cd /path/to/homaye-tabesh-plugin
npm install
npm run build
```

✅ **بررسی:** فایل‌های زیر باید ساخته شوند:
- `assets/build/homa-sidebar.js`
- `assets/build/atlas-dashboard.js`

---

## گام 2: فعال‌سازی دیتابیس (1 دقیقه)

```bash
# اگر پلاگین از قبل فعال است:
wp plugin deactivate homaye-tabesh
wp plugin activate homaye-tabesh
```

یا از پنل مدیریت وردپرس:
**پلاگین‌ها → غیرفعال → فعال‌سازی مجدد**

✅ **بررسی:** جداول زیر باید ایجاد شده باشند:
```sql
SHOW TABLES LIKE '%homa_leads%';
SHOW TABLES LIKE '%homa_otp%';
```

---

## گام 3: دریافت اطلاعات ملی‌پیامک (3 دقیقه)

### 3.1 ورود به پنل ملی‌پیامک
1. به https://ippanel.com وارد شوید
2. نام کاربری و رمز عبور خود را یادداشت کنید

### 3.2 دریافت شماره فرستنده
از منوی **خدمات → شماره‌های من**

مثال: `+981000...`

### 3.3 ایجاد الگوی OTP

از منوی **الگوهای پیامک → ایجاد الگو**

**الگوی پیشنهادی برای OTP:**
```
کد تایید هما:
{verification-code}

این کد 2 دقیقه اعتبار دارد.
```

✅ کد الگو را یادداشت کنید (مثلاً: `pattern_12345`)

### 3.4 ایجاد الگوی اطلاع‌رسانی لید

**الگوی پیشنهادی:**
```
هما: لید جدید
نام: {customer-name}
امتیاز: {lead-score}
تماس: {contact-info}
```

✅ کد الگو را یادداشت کنید

---

## گام 4: تنظیمات در Atlas (2 دقیقه)

1. وارد **داشبورد وردپرس → اطلس → تنظیمات** شوید

2. پایین بیایید به بخش **📱 تنظیمات ملی‌پیامک**

3. فیلدها را پر کنید:

```
نام کاربری ملی‌پیامک: [username از گام 3.1]
رمز عبور: [password از گام 3.1]
شماره فرستنده: [از گام 3.2]
کد الگوی OTP: [از گام 3.3]
کد الگوی اطلاع‌رسانی لید: [از گام 3.4]
شماره موبایل مدیر: 09123456789
```

4. تنظیمات اطلاع‌رسانی:
   - ✅ فعال‌سازی اطلاع‌رسانی لید
   - حداقل امتیاز: **70** (پیش‌فرض)

5. روی **💾 ذخیره تنظیمات** کلیک کنید

---

## گام 5: تست OTP (2 دقیقه)

### 5.1 تست از Postman

```bash
POST http://yoursite.com/wp-json/homa/v1/otp/send
Content-Type: application/json

{
  "phone_number": "09123456789"
}
```

✅ **پاسخ موفق:**
```json
{
  "success": true,
  "message": "کد تایید به شماره شما ارسال شد",
  "expires_in": 120
}
```

### 5.2 دریافت کد از دیتابیس

```sql
SELECT otp_code, expires_at 
FROM wp_homa_otp 
WHERE phone_number = '09123456789' 
ORDER BY created_at DESC 
LIMIT 1;
```

### 5.3 تایید کد

```bash
POST http://yoursite.com/wp-json/homa/v1/otp/verify
Content-Type: application/json

{
  "phone_number": "09123456789",
  "otp_code": "123456"
}
```

✅ **پاسخ موفق:**
```json
{
  "success": true,
  "action": "register",
  "user_id": 45,
  "message": "حساب کاربری شما ایجاد و وارد شدید"
}
```

---

## گام 6: تست Lead Conversion (2 دقیقه)

### 6.1 ایجاد لید

```bash
POST http://yoursite.com/wp-json/homa/v1/leads
Content-Type: application/json

{
  "user_id_or_token": "test_user_123",
  "contact_name": "علی احمدی",
  "contact_info": "09123456789",
  "source_referral": "instagram",
  "volume": 5000,
  "product_type": "gold_foil",
  "engagement": {
    "message_count": 12,
    "viewed_products": 5,
    "viewed_invoices": 2
  },
  "requirements_summary": {
    "volume": 5000,
    "paper_type": "گلاسه 150 گرم",
    "print_type": "چاپ افست"
  }
}
```

✅ **پاسخ موفق:**
```json
{
  "success": true,
  "lead_id": 1,
  "lead_score": 85,
  "lead_status": "hot",
  "message": "لید با موفقیت ثبت شد"
}
```

### 6.2 بررسی اطلاع‌رسانی

چون امتیاز 85 است (بالاتر از 70)، باید:
- ✅ پیامک به شماره مدیر ارسال شود
- ✅ ایمیل به ادمین ارسال شود

**بررسی لاگ:**
```bash
tail -f /path/to/wordpress/wp-content/debug.log | grep "Homa"
```

---

## گام 7: ادغام با UI (در صورت نیاز)

### 7.1 استفاده از React Components

```jsx
import LeadCaptureForm from './components/LeadCaptureForm';
import { PhoneNumberInput, OTPInput } from './components/OTPInput';
import { homaLeadAPI } from './services/homaLeadAPI';

// مثال: Lead Capture
<LeadCaptureForm 
  onSubmit={async (data) => {
    const result = await homaLeadAPI.createLead(data);
    console.log('Lead created:', result);
  }}
/>

// مثال: OTP Flow
// Stage 1
<PhoneNumberInput 
  onSubmit={async (phone) => {
    await homaLeadAPI.sendOTP(phone);
    setStage('otp');
  }}
/>

// Stage 2
<OTPInput
  phoneNumber={phone}
  onComplete={async (code) => {
    const result = await homaLeadAPI.verifyOTP(phone, code);
    console.log('User authenticated:', result);
  }}
  onResend={() => homaLeadAPI.sendOTP(phone)}
/>
```

---

## 🚨 عیب‌یابی سریع

### پیامک ارسال نمی‌شود

1. **بررسی لاگ:**
```bash
grep "Homa SMS" wp-content/debug.log
```

2. **چک کردن شارژ ملی‌پیامک:**
   - به پنل ملی‌پیامک بروید
   - از موجودی اطمینان حاصل کنید

3. **تست اتصال:**
```php
$sms = new \HomayeTabesh\Homa_SMS_Provider();
$result = $sms->send_otp('09123456789', '123456');
var_dump($result);
```

### کد OTP Invalid است

1. **بررسی انقضا:**
```sql
SELECT *, TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_left
FROM wp_homa_otp 
WHERE phone_number = '09123456789'
ORDER BY created_at DESC LIMIT 1;
```

2. **بررسی تعداد تلاش:**
```sql
SELECT attempts FROM wp_homa_otp 
WHERE phone_number = '09123456789' 
AND is_verified = 0
ORDER BY created_at DESC LIMIT 1;
```

### لید ایجاد می‌شود اما نوتیفیکیشن ندارد

1. **بررسی امتیاز:**
```php
$score = HT_Lead_Scoring_Algorithm::calculate_score($params);
echo "Score: $score (Threshold: " . get_option('ht_lead_hot_score_threshold') . ")";
```

2. **بررسی فعال بودن نوتیفیکیشن:**
```php
echo get_option('ht_lead_notification_enabled') ? 'Enabled' : 'Disabled';
```

### دیتابیس ایجاد نمی‌شود

```bash
wp plugin deactivate homaye-tabesh --uninstall
wp plugin activate homaye-tabesh
```

---

## ✅ Checklist نهایی

پس از اتمام راه‌اندازی، این موارد را بررسی کنید:

- [ ] Assets ساخته شده‌اند (`npm run build`)
- [ ] جداول `wp_homa_leads` و `wp_homa_otp` وجود دارند
- [ ] تنظیمات ملی‌پیامک ذخیره شده است
- [ ] تست OTP موفق است (send + verify)
- [ ] تست Lead Conversion موفق است
- [ ] پیامک و ایمیل اطلاع‌رسانی دریافت می‌شود
- [ ] UI Components در React کار می‌کنند

---

## 📚 مطالعه بیشتر

- [PR11-IMPLEMENTATION.md](./PR11-IMPLEMENTATION.md) - مستندات کامل
- [PR11-README.md](./PR11-README.md) - معرفی کلی
- [PR11-SUMMARY.md](./PR11-SUMMARY.md) - خلاصه تغییرات

---

## 💡 نکات مهم

1. **الگوهای پیامک حتماً باید تایید شده باشند** - بدون تایید، پیامک ارسال نمی‌شود
2. **Rate Limiting فعال است** - حداکثر 3 درخواست OTP در ساعت
3. **OTP بعد از 2 دقیقه منقضی می‌شود** - برای تست، سریع عمل کنید
4. **لیدهای Hot = امتیاز ≥ 70** - می‌توانید از Atlas تغییر دهید
5. **شماره موبایل باید فرمت ایرانی باشد** - 09xxxxxxxxx

---

## 🎉 Done!

حالا سیستم Smart Lead Conversion شما آماده است!

برای سوالات: [GitHub Issues](https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh/issues)
