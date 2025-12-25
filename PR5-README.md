# PR5 README - Action & Conversion Engine

## 🎯 هدف این PR

پیاده‌سازی **موتور عملیاتی و مداخله هوشمند** (Action & Conversion Engine) که به هما توانایی "عمل کردن" در لحظات حساس سفر کاربر را می‌دهد.

## 🆕 چه چیزی اضافه شد؟

### ۱. تشخیص رفتار و مداخله (Behavioral Intervention)
- 🚪 **Exit Intent**: تشخیص قصد خروج با ردیابی سرعت ماوس
- 📊 **Scroll Depth**: ردیابی عمق اسکرول (25%, 50%, 75%, 90%)
- ⏱️ **Field Hesitation**: تشخیص توقف روی فیلد (60 ثانیه)
- 💰 **Price Changes**: شمارش تغییرات قیمت (5+ تغییر)
- 📝 **Form Completion**: محاسبه درصد تکمیل فرم

### ۲. خودکارسازی فرم (Form Hydration)
- 🔍 جستجوی هوشمند فیلدها
- ✍️ پر کردن خودکار از چت
- 🔄 تریگر محاسبات قیمت
- 🎨 پشتیبانی از تمام form frameworks

### ۳. مدیریت سبد خرید (Cart Manager)
- ➕ افزودن سریع به سبد
- 🎁 اعمال تخفیف اتوماتیک
- 💳 هدایت مستقیم به checkout
- 📦 ذخیره metadata سفارشی

### ۴. نمایش پیشنهادات (Offer Display)
- ⚡ پیشنهادهای تخفیف با تایمر
- 💬 پیشنهادهای کمک
- 🛒 دکمه "پرداخت با هما"
- 🎨 انیمیشن‌های زیبا

### ۵. ذخیره جلسه (Session Persistence)
- 💾 ذخیره تمام انتخابات کاربر
- 🔄 بازیابی سبدهای رها شده
- 📊 تحلیل مسیر تبدیل
- 🎯 پیگیری در admin panel

---

## 🚀 نصب و راه‌اندازی

### مرحله ۱: غیرفعال و فعال کردن مجدد افزونه

```bash
wp plugin deactivate homaye-tabesh
wp plugin activate homaye-tabesh
```

این کار جدول `wp_homaye_conversion_sessions` را ایجاد می‌کند.

### مرحله ۲: بررسی بارگذاری اسکریپت‌ها

در کنسول مرورگر:

```javascript
console.log(window.Homa.ConversionTriggers);  // ✅ باید object نمایش دهد
console.log(window.Homa.FormHydration);       // ✅ باید object نمایش دهد
console.log(window.Homa.OfferDisplay);        // ✅ باید object نمایش دهد
```

---

## 📖 استفاده

### مثال ۱: همگام‌سازی فرم از چت

```javascript
// کاربر در چت می‌گوید: "کتاب من 240 صفحه دارد"
Homa.FormHydration.syncField('pages', '240');

// یا همگام‌سازی چند فیلد:
Homa.FormHydration.syncBulk({
    'book_title': 'ققنوس',
    'pages': '240',
    'quantity': '500'
});
```

### مثال ۲: افزودن به سبد خرید

```javascript
const response = await fetch('/wp-json/homaye/v1/cart/add', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': homayePerceptionConfig.nonce
    },
    body: JSON.stringify({
        product_id: 405,
        quantity: 1,
        homa_config: {
            book_title: 'ققنوس',
            pages: '240'
        }
    })
});

const data = await response.json();
if (data.success) {
    console.log('افزوده شد! URL پرداخت:', data.checkout_url);
}
```

### مثال ۳: نمایش پیشنهاد تخفیف

```javascript
Homa.OfferDisplay.showOffer('discount', {
    id: 'special_' + Date.now(),
    title: '🎉 تخفیف ویژه!',
    message: 'فقط برای شما: ۲۰٪ تخفیف',
    discountPercent: 20,
    expiresIn: 600, // 10 دقیقه
    cta: 'دریافت تخفیف',
    ctaAction: () => {
        Homa.OfferDisplay.applyDiscount(20, 'special_offer');
    }
});
```

### مثال ۴: ذخیره جلسه (PHP)

```php
$core = \HomayeTabesh\HT_Core::instance();

$session_data = [
    'form_completion' => 75,
    'cart_value' => 2500000,
    'conversion_status' => 'in_progress',
    'filled_fields' => ['book_title', 'pages'],
    'page_url' => '/order-form'
];

$core->memory->save_conversion_session('user_123', $session_data);
```

---

## 🔍 API Reference

### JavaScript APIs

#### Homa.ConversionTriggers
```javascript
// دریافت context کاربر
Homa.ConversionTriggers.getUserContext()

// بررسی نیاز به مداخله
Homa.ConversionTriggers.checkInterventionNeed(userData)
```

#### Homa.FormHydration
```javascript
// همگام‌سازی یک فیلد
Homa.FormHydration.syncField(fieldId, value, triggerRecalc)

// همگام‌سازی چند فیلد
Homa.FormHydration.syncBulk(fieldsObject)

// پیدا کردن فیلد
Homa.FormHydration.findField(identifier)

// ریست کردن فرم
Homa.FormHydration.resetForm()
```

#### Homa.OfferDisplay
```javascript
// نمایش پیشنهاد
Homa.OfferDisplay.showOffer(offerType, offerData)

// بستن پیشنهاد
Homa.OfferDisplay.dismissOffer(offerId)

// اعمال تخفیف
Homa.OfferDisplay.applyDiscount(percent, reason)

// رفتن به checkout
Homa.OfferDisplay.goToCheckout()
```

### REST APIs

#### POST `/wp-json/homaye/v1/cart/add`
افزودن محصول به سبد

**Request:**
```json
{
  "product_id": 405,
  "quantity": 1,
  "homa_config": {
    "book_title": "ققنوس",
    "pages": "240"
  }
}
```

**Response:**
```json
{
  "success": true,
  "cart_item_key": "abc123",
  "cart_total": "2,500,000 تومان",
  "checkout_url": "/checkout"
}
```

#### POST `/wp-json/homaye/v1/cart/apply-discount`
اعمال تخفیف

**Request:**
```json
{
  "discount_type": "percentage",
  "discount_value": 20,
  "reason": "exit_intent"
}
```

**Response:**
```json
{
  "success": true,
  "coupon_code": "homa_abc123",
  "cart_total": "2,000,000 تومان",
  "discount_amount": "500,000"
}
```

#### GET `/wp-json/homaye/v1/cart/status`
دریافت وضعیت سبد

**Response:**
```json
{
  "success": true,
  "status": "has_items",
  "item_count": 2,
  "total": "2,500,000 تومان",
  "items": [...]
}
```

### PHP APIs

```php
$core = \HomayeTabesh\HT_Core::instance();
$cart_manager = $core->cart_manager;
$memory = $core->memory;

// ذخیره جلسه
$memory->save_conversion_session($user_id, $session_data);

// دریافت جلسه
$session = $memory->get_conversion_session($user_id);

// تکمیل جلسه
$memory->complete_conversion_session($user_id, $order_id);

// دریافت سبدهای رها شده
$abandoned = $memory->get_abandoned_sessions(1); // 1 hour
```

---

## 🎨 رویدادهای سفارشی

### JavaScript Events

```javascript
// تریگر conversion
document.addEventListener('homa:trigger', (e) => {
    console.log('Trigger:', e.detail.trigger);
    console.log('Data:', e.detail.data);
});

// همگام‌سازی فیلد
document.dispatchEvent(new CustomEvent('homa:sync-field', {
    detail: { fieldName: 'book_title', value: 'ققنوس' }
}));

// نمایش پیشنهاد
document.dispatchEvent(new CustomEvent('homa:show-offer', {
    detail: { offerType: 'discount', offerData: {...} }
}));
```

### Trigger Types
- `EXIT_INTENT` - قصد خروج
- `SCROLL_DEPTH` - عمق اسکرول
- `FIELD_HESITATION` - توقف روی فیلد
- `PRICE_HESITATION` - تردید در قیمت

---

## 🔒 امنیت

### تدابیر امنیتی پیاده‌سازی شده:

✅ **Nonce Verification**: تمام REST endpoints  
✅ **Permission Callbacks**: بررسی دسترسی  
✅ **Data Sanitization**: پاکسازی ورودی‌ها  
✅ **Prepared Statements**: جلوگیری از SQL injection  
✅ **XSS Prevention**: استفاده از esc_html  
✅ **Session Validation**: بررسی نشست با نوع چندگانه  
✅ **CSRF Protection**: محافظت در برابر CSRF  

---

## ⚡ Performance

### بهینه‌سازی‌های انجام شده:

✅ **Passive Event Listeners**: کاهش blocking  
✅ **Debouncing**: 150ms برای scroll، 800ms برای input  
✅ **Efficient DOM Queries**: استفاده از WeakMap و WeakSet  
✅ **Transient Caching**: کش کردن نتایج  
✅ **Lazy Initialization**: بارگذاری تنبل  

---

## 🧪 تست کردن

### سناریوی ۱: Exit Intent
1. فرم را تا 60% پر کنید
2. ماوس را سریع به سمت بالا ببرید
3. باید پیشنهاد تخفیف نمایش داده شود

### سناریوی ۲: Form Sync
1. در کنسول: `Homa.FormHydration.syncField('book_title', 'Test')`
2. فیلد باید پر شود
3. قیمت باید محاسبه شود

### سناریوی ۳: Cart
1. از Postman یا کنسول API را فراخوانی کنید
2. محصول باید به سبد اضافه شود
3. Checkout URL باید برگردد

---

## 📊 دیتابیس

### جدول: `wp_homaye_conversion_sessions`

| ستون | نوع | توضیح |
|------|-----|-------|
| id | bigint | شناسه یکتا |
| user_identifier | varchar(255) | شناسه کاربر |
| session_data | longtext | JSON داده‌های جلسه |
| form_completion | int | درصد تکمیل فرم |
| cart_value | decimal | ارزش سبد |
| conversion_status | varchar(50) | وضعیت (in_progress, completed) |
| order_id | bigint | شناسه سفارش |
| last_activity | datetime | آخرین فعالیت |
| created_at | datetime | تاریخ ایجاد |
| completed_at | datetime | تاریخ تکمیل |

---

## 📚 مستندات بیشتر

- 📖 [مستندات کامل PR5](./PR5-IMPLEMENTATION.md)
- 🚀 [راهنمای سریع](./PR5-QUICKSTART.md)
- 💡 [مثال‌های استفاده](./examples/pr5-usage-examples.php)

---

## 🤝 مشارکت

اگر مشکلی پیدا کردید یا پیشنهادی دارید:
1. Issue ایجاد کنید
2. PR ارسال کنید
3. در Discussion شرکت کنید

---

## 📝 Changelog

### نسخه 1.0.0 (PR5) - 2025-12-25

**اضافه شده:**
- ✅ موتور Conversion Triggers
- ✅ سیستم Form Hydration
- ✅ WooCommerce Cart Manager
- ✅ UI پیشنهادات داینامیک
- ✅ Session Persistence

**بهبود یافته:**
- ✅ امنیت REST endpoints
- ✅ Performance event listeners
- ✅ مدیریت AJAX forms

**رفع شده:**
- ✅ SQL injection در table check
- ✅ Session validation
- ✅ AJAX timing issues

---

## 📞 پشتیبانی

برای پشتیبانی:
- 📧 ایمیل: [support@example.com](mailto:support@example.com)
- 💬 Discussion: GitHub Discussions
- 📚 مستندات: [docs.example.com](https://docs.example.com)

---

**ساخته شده با ❤️ برای وردپرس و ووکامرس**
