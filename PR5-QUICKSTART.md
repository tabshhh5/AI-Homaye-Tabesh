# PR5 Quick Start Guide
## موتور عملیاتی و مداخله هوشمند

این راهنما برای شروع سریع کار با Action & Conversion Engine است.

---

## 🚀 نصب و فعال‌سازی

### گام 1: به‌روزرسانی دیتابیس
```bash
# در صورتی که افزونه قبلاً نصب بود، غیرفعال و مجدداً فعال کنید
# یا از WP-CLI استفاده کنید:
wp plugin deactivate homaye-tabesh
wp plugin activate homaye-tabesh
```

این کار جدول `wp_homaye_conversion_sessions` را ایجاد می‌کند.

### گام 2: بررسی فعال بودن ماژول‌ها
اسکریپت‌های زیر باید در frontend بارگذاری شوند:
- ✅ `homa-conversion-triggers.js`
- ✅ `homa-form-hydration.js`
- ✅ `homa-offer-display.js`

برای بررسی، در کنسول مرورگر:
```javascript
console.log(window.Homa.ConversionTriggers);
console.log(window.Homa.FormHydration);
console.log(window.Homa.OfferDisplay);
```

---

## 💡 کاربردهای سریع

### 1️⃣ همگام‌سازی فرم از چت

```javascript
// در کد چت خود، بعد از استخراج اطلاعات:
Homa.FormHydration.syncBulk({
    'book_title': 'عنوان کتاب',
    'pages': '240',
    'quantity': '500',
    'binding_type': 'Hardcover'
});

// یا تک تک:
Homa.FormHydration.syncField('book_title', 'عنوان کتاب');
```

### 2️⃣ افزودن محصول به سبد

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
console.log(data.checkout_url); // URL صفحه پرداخت
```

### 3️⃣ اعمال تخفیف

```javascript
const response = await fetch('/wp-json/homaye/v1/cart/apply-discount', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': homayePerceptionConfig.nonce
    },
    body: JSON.stringify({
        discount_type: 'percentage',
        discount_value: 20,
        reason: 'exit_intent_offer'
    })
});

const data = await response.json();
console.log('Discount applied:', data.coupon_code);
```

### 4️⃣ نمایش پیشنهاد ویژه

```javascript
Homa.OfferDisplay.showOffer('discount', {
    id: 'special_offer_' + Date.now(),
    title: '🎉 تخفیف ویژه!',
    message: 'فقط برای شما: ۲۰٪ تخفیف',
    discountPercent: 20,
    expiresIn: 600, // 10 دقیقه
    cta: 'دریافت تخفیف',
    ctaAction: () => {
        // اعمال تخفیف
        Homa.OfferDisplay.applyDiscount(20, 'special_offer');
    }
});
```

### 5️⃣ ذخیره جلسه تبدیل (PHP)

```php
$core = \HomayeTabesh\HT_Core::instance();

$session_data = [
    'form_completion' => 75,
    'cart_value' => 2500000,
    'conversion_status' => 'in_progress',
    'filled_fields' => ['book_title', 'pages', 'quantity'],
    'last_interaction' => 'price_change',
    'page_url' => '/order-form'
];

$core->memory->save_conversion_session('user_123', $session_data);
```

---

## 🎯 سناریوهای رایج

### سناریو A: کاربر میخواهد سایت را ترک کند

**خودکار است!** 
- Conversion Triggers به صورت خودکار exit intent را تشخیص می‌دهد
- Offer Display پیشنهاد تخفیف نمایش می‌دهد
- اگر کاربر قبول کند، تخفیف اعمال می‌شود

**کد سفارشی (اختیاری)**:
```javascript
// برای override رفتار پیش‌فرض:
document.addEventListener('homa:trigger', (e) => {
    if (e.detail.trigger === 'EXIT_INTENT') {
        // رفتار سفارشی خودتان
        console.log('User wants to leave!');
    }
});
```

### سناریو B: کاربر در چت اطلاعات داد

```javascript
// 1. استخراج اطلاعات از پیام کاربر
const userMessage = "من میخوام یک کتاب 240 صفحه‌ای با جلد سخت چاپ کنم";

// 2. تحلیل و استخراج (با AI یا regex)
const extracted = {
    pages: 240,
    binding_type: 'Hardcover'
};

// 3. همگام‌سازی با فرم
Homa.FormHydration.syncBulk(extracted);

// 4. اطلاع به کاربر
chatSendMessage("فهمیدم! اطلاعات رو توی فرم پر کردم.");
```

### سناریو C: کاربر چند بار قیمت را تغییر داد

**خودکار است!**
- بعد از 5 تغییر، Price Hesitation تریگر می‌شود
- پیشنهاد تخفیف نمایش داده می‌شود

**کد سفارشی (اختیاری)**:
```javascript
document.addEventListener('homa:trigger', (e) => {
    if (e.detail.trigger === 'PRICE_HESITATION') {
        // نمایش پیام مخصوص
        chatSendMessage("دیدم داری قیمت رو بررسی می‌کنی. میتونم کمکت کنم؟");
    }
});
```

### سناریو D: بازیابی سبدهای رها شده

```php
// در یک cron job روزانه:
$core = \HomayeTabesh\HT_Core::instance();

// دریافت جلسه‌های رها شده در 24 ساعت گذشته
$abandoned = $core->memory->get_abandoned_sessions(24);

foreach ($abandoned as $session) {
    $user_id = $session['user_identifier'];
    $form_completion = $session['form_completion'];
    
    // ارسال ایمیل بازگشت
    if ($form_completion > 50) {
        send_recovery_email($user_id, [
            'discount' => 15,
            'message' => 'سفارش شما ناتمام مانده! با 15% تخفیف برگردید.'
        ]);
    }
}
```

---

## 🛠 تنظیمات پیشرفته

### تغییر Thresholdها

```php
// در functions.php تم خود:

// تغییر زمان idle برای hesitation
add_filter('homaye_field_idle_threshold', function($threshold) {
    return 45000; // 45 ثانیه به جای 60
});

// تغییر تعداد تغییرات قیمت
add_filter('homaye_price_change_threshold', function($threshold) {
    return 3; // 3 تغییر به جای 5
});

// تغییر نقاط scroll depth
add_filter('homaye_scroll_markers', function($markers) {
    return [20, 40, 60, 80, 100]; // نقاط جدید
});
```

### غیرفعال کردن ماژول خاص

```php
// غیرفعال کردن exit intent
add_filter('homaye_enable_exit_intent', '__return_false');

// غیرفعال کردن price hesitation
add_filter('homaye_enable_price_tracking', '__return_false');
```

### سفارشی‌سازی پیشنهادها

```javascript
// override پیشنهاد exit intent
document.addEventListener('homa:trigger', (e) => {
    if (e.detail.trigger === 'EXIT_INTENT') {
        e.preventDefault(); // جلوگیری از پیشنهاد پیش‌فرض
        
        // پیشنهاد سفارشی
        Homa.OfferDisplay.showOffer('discount', {
            id: 'custom_exit_' + Date.now(),
            title: 'پیشنهاد ویژه شما!',
            message: 'تخفیف ۲۵٪ برای خرید امروز',
            discountPercent: 25,
            expiresIn: 900, // 15 دقیقه
            cta: 'استفاده می‌کنم',
            ctaAction: () => {
                Homa.OfferDisplay.applyDiscount(25, 'custom_exit');
            }
        });
    }
});
```

---

## 🔍 دیباگ و عیب‌یابی

### چک کردن وضعیت ماژول‌ها

```javascript
// در کنسول مرورگر:
console.log('Triggers:', window.Homa.ConversionTriggers);
console.log('Hydration:', window.Homa.FormHydration);
console.log('Offers:', window.Homa.OfferDisplay);
console.log('Config:', window.homayePerceptionConfig);
```

### مشاهده Triggersها

```javascript
// Listen به همه triggerها
document.addEventListener('homa:trigger', (e) => {
    console.log('Trigger fired:', e.detail);
});
```

### تست Form Sync

```javascript
// پیدا کردن فیلد
const field = Homa.FormHydration.findField('book_title');
console.log('Found field:', field);

// Sync کردن
Homa.FormHydration.syncField('book_title', 'Test Value');

// چک pending syncs
console.log('Pending:', Homa.FormHydration.getPendingSync());
```

### بررسی Cart API

```javascript
// وضعیت سبد
fetch('/wp-json/homaye/v1/cart/status')
    .then(r => r.json())
    .then(data => console.log('Cart:', data));
```

### مشاهده لاگ‌های PHP

```php
// فعال کردن لاگ در wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// در کد:
error_log('Homa: Conversion session saved - ' . $user_id);
```

---

## 📋 Checklist راه‌اندازی

- [ ] افزونه فعال است
- [ ] جدول `conversion_sessions` ساخته شده
- [ ] اسکریپت‌ها در frontend بارگذاری می‌شوند
- [ ] `window.Homa` در کنسول قابل دسترسی است
- [ ] WooCommerce فعال است (برای Cart API)
- [ ] REST API کار می‌کند
- [ ] Nonce به درستی set شده

---

## 🎓 منابع بیشتر

- [مستندات کامل PR5](./PR5-IMPLEMENTATION.md)
- [مثال‌های استفاده](./examples/pr5-usage-examples.php)
- [API Reference](./PR5-IMPLEMENTATION.md#api-عمومی)

---

## 💬 پشتیبانی

اگر مشکلی پیش آمد:
1. لاگ‌های مرورگر را بررسی کنید
2. لاگ‌های وردپرس را چک کنید (`wp-content/debug.log`)
3. مطمئن شوید همه پیش‌نیازها نصب هستند
4. مثال‌های استفاده را امتحان کنید

---

**نکته مهم**: این ماژول کاملاً خودکار است. فقط با فعال کردن افزونه، همه ویژگی‌ها شروع به کار می‌کنند!
