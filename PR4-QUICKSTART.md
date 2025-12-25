# راهنمای سریع PR4 - لایه ادراک محیطی

## نصب در 3 دقیقه ⚡

### مرحله 1: بررسی نیازمندی‌ها ✅
```bash
# PHP Version
php -v  # باید 8.2 یا بالاتر باشد

# WordPress Version
# در داشبورد وردپرس بررسی کنید: باید 6.0+ باشد
```

### مرحله 2: بررسی عملکرد 🔍
پس از فعال کردن افزونه، صفحه وبسایت خود را باز کنید و console مرورگر را چک کنید:

```javascript
// باید این لاگ‌ها را ببینید:
// ✓ Homa Indexer: Initializing semantic mapping...
// ✓ Homa Input Observer: Initializing live input monitoring...
// ✓ Homa Spatial Navigator: Initializing...
// ✓ Homa Tour Manager: Initializing...
```

### مرحله 3: تست اولیه 🧪
در console مرورگر:

```javascript
// 1. بررسی ماژول‌ها
console.log(typeof HomaIndexer);        // باید 'object' باشد
console.log(typeof HomaInputObserver);  // باید 'object' باشد
console.log(typeof HomaSpatialNavigator); // باید 'object' باشد
console.log(typeof HomaTourManager);    // باید 'object' باشد

// 2. تعداد المان‌های ایندکس شده
console.log('Indexed:', HomaIndexer.getAll().length);

// 3. تست ناوبری
HomaNavigation.scrollTo('body', { highlight: true });
```

اگر همه چیز کار کرد، آماده استفاده هستید! 🎉

---

## 5 مثال کاربردی 🚀

### 1️⃣ نمایش راهنمای سریع برای یک فیلد

```javascript
// در هر جایی از کد خود:
startHomaTour({
    selector: '#book_title',
    title: 'عنوان کتاب',
    message: 'لطفاً نام کتاب خود را دقیق وارد کنید'
});
```

**استفاده**: زمانی که می‌خواهید به کاربر کمک کنید یک فیلد خاص را پر کند.

---

### 2️⃣ راهنمای گام‌به‌گام سفارش محصول

```javascript
// تعریف تور
const bookOrderTour = {
    title: 'راهنمای سفارش چاپ کتاب',
    steps: [
        {
            selector: '#book_title',
            title: 'مرحله 1: عنوان',
            message: 'نام کتاب خود را وارد کنید'
        },
        {
            selector: '#book_pages',
            title: 'مرحله 2: تعداد صفحات',
            message: 'تعداد صفحات کتاب را مشخص کنید'
        },
        {
            selector: '#book_quantity',
            title: 'مرحله 3: تیراژ',
            message: 'تیراژ مورد نیاز را وارد کنید'
        },
        {
            selector: '.calculate-btn',
            title: 'مرحله 4: محاسبه',
            message: 'روی این دکمه کلیک کنید تا قیمت محاسبه شود'
        }
    ]
};

// شروع تور
HomaTour.start(bookOrderTour);
```

**استفاده**: برای آموزش کاربران جدید نحوه استفاده از فرم‌های پیچیده.

---

### 3️⃣ واکنش هوشمند به ورودی کاربر

```javascript
// ثبت listener
HomaInputObserver.onIntent((eventType, data) => {
    if (eventType === 'intent_detected') {
        // چک کردن الگوها
        const patterns = data.concepts.patterns;
        
        // اگر کاربر در مورد کتاب کودک صحبت می‌کند
        if (patterns.includes('children_related')) {
            // نمایش پیشنهاد
            setTimeout(() => {
                startHomaTour({
                    selector: '[href*="children"]',
                    title: '💡 پیشنهاد ویژه',
                    message: 'ما خدمات تخصصی برای چاپ کتاب کودک داریم!'
                });
            }, 1000);
        }
        
        // اگر کاربر در مورد طراحی صحبت می‌کند
        if (patterns.includes('design_related')) {
            HomaNavigation.scrollTo('[href*="design"]', {
                highlight: true
            });
        }
    }
});
```

**استفاده**: برای پیشنهاد محصولات یا خدمات مرتبط در لحظه.

---

### 4️⃣ دکمه راهنما در کنار فرم

```php
// در قالب وردپرس خود (functions.php یا template):
function add_help_button_to_form() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // افزودن دکمه راهنما
        const helpBtn = $('<button>')
            .text('❓ راهنما')
            .css({
                position: 'fixed',
                bottom: '20px',
                right: '20px',
                padding: '12px 24px',
                background: '#667eea',
                color: 'white',
                border: 'none',
                borderRadius: '25px',
                cursor: 'pointer',
                fontSize: '16px',
                boxShadow: '0 4px 15px rgba(0,0,0,0.2)',
                zIndex: 99999
            })
            .on('click', function() {
                // شروع تور راهنما
                HomaTour.start({
                    title: 'راهنمای استفاده',
                    steps: [
                        {
                            selector: '.form-field-1',
                            title: 'فیلد اول',
                            message: 'توضیحات فیلد اول...'
                        },
                        {
                            selector: '.form-field-2',
                            title: 'فیلد دوم',
                            message: 'توضیحات فیلد دوم...'
                        }
                    ]
                });
            });
        
        $('body').append(helpBtn);
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_help_button_to_form');
```

**استفاده**: افزودن دکمه راهنما به فرم‌های شما.

---

### 5️⃣ ناوبری خودکار بر اساس پرسونا

```javascript
// وقتی کاربر وارد صفحه می‌شود
jQuery(document).ready(function($) {
    // دریافت پرسونا از سرور
    fetch('/wp-json/homaye/v1/navigation/suggest', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': homayePerceptionConfig.nonce
        },
        body: JSON.stringify({
            current_location: window.location.pathname
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.suggestions && data.suggestions.length > 0) {
            const topSuggestion = data.suggestions[0];
            
            // نمایش tooltip برای پیشنهاد
            setTimeout(() => {
                startHomaTour({
                    selector: topSuggestion.selector,
                    title: 'پیشنهاد برای شما',
                    message: topSuggestion.label
                });
            }, 3000);
        }
    });
});
```

**استفاده**: راهنمایی شخصی‌سازی شده بر اساس پرسونای کاربر.

---

## سناریوهای واقعی 🎬

### سناریو 1: فروشگاه چاپ کتاب

**هدف**: راهنمایی کاربران برای تکمیل فرم سفارش

```javascript
// در صفحه محصول
if (window.location.href.includes('/book-printing/')) {
    // نمایش راهنما بعد از 5 ثانیه
    setTimeout(() => {
        HomaTour.start({
            title: 'نحوه سفارش چاپ کتاب',
            steps: [
                {
                    selector: '[name="book_title"]',
                    message: 'عنوان کتاب خود را وارد کنید'
                },
                {
                    selector: '[name="book_pages"]',
                    message: 'تعداد صفحات کتاب (شامل فهرست و مقدمه)'
                },
                {
                    selector: '.paper-type-selector',
                    message: 'برای کتاب‌های معمولی: گلاسه 80 گرم'
                },
                {
                    selector: '.submit-order',
                    message: 'با کلیک، قیمت محاسبه می‌شود'
                }
            ]
        });
    }, 5000);
}
```

---

### سناریو 2: پاسخ به سوال کاربر

**هدف**: وقتی کاربر "تیراژ" را تایپ می‌کند، به بخش مربوطه هدایت شود

```javascript
HomaInputObserver.onIntent((eventType, data) => {
    if (eventType === 'intent_detected') {
        const value = data.value.toLowerCase();
        
        // اگر کاربر در مورد تیراژ می‌پرسد
        if (value.includes('تیراژ') || value.includes('tiraj')) {
            HomaNavigation.navigateToField('تیراژ').then(() => {
                startHomaTour({
                    selector: '[name="quantity"]',
                    title: 'تیراژ کتاب',
                    message: 'تیراژ بالاتر = قیمت هر نسخه کمتر! حداقل تیراژ: 50 نسخه'
                });
            });
        }
    }
});
```

---

### سناریو 3: راهنمای تعاملی برای محاسبه‌گر قیمت

```javascript
// افزودن دکمه "چطور محاسبه کنم؟"
const calcHelpBtn = $('<button>')
    .text('چطور محاسبه کنم؟')
    .insertAfter('.price-calculator')
    .on('click', function() {
        HomaTour.start({
            title: 'راهنمای محاسبه قیمت',
            steps: [
                {
                    selector: '[name="paper_type"]',
                    message: 'نوع کاغذ: تأثیر زیادی در قیمت دارد'
                },
                {
                    selector: '[name="cover_type"]',
                    message: 'جلد: گالینگور گران‌تر، شومیز ارزان‌تر'
                },
                {
                    selector: '[name="color"]',
                    message: 'رنگ: تمام رنگی 2 برابر سیاه و سفید'
                },
                {
                    selector: '.calculate-btn',
                    message: 'کلیک کنید!'
                }
            ]
        });
    });
```

---

## Tips & Tricks 💡

### ✅ نکته 1: استفاده از delay برای UX بهتر
```javascript
// بعد از 2 ثانیه tooltip نمایش بده (نه فوری)
setTimeout(() => {
    startHomaTour({...});
}, 2000);
```

### ✅ نکته 2: چک کردن وجود المان قبل از tour
```javascript
const target = document.querySelector('#my-field');
if (target) {
    startHomaTour({
        selector: '#my-field',
        message: '...'
    });
}
```

### ✅ نکته 3: حذف فیلدهای حساس از monitoring
```html
<input type="text" name="credit_card" data-homa-ignore>
```

### ✅ نکته 4: استفاده از Promise برای chain
```javascript
HomaNavigation.scrollTo('.section-1')
    .then(() => HomaNavigation.scrollTo('.section-2'))
    .then(() => HomaNavigation.scrollTo('.section-3'));
```

### ✅ نکته 5: دسترسی به داده‌های ایندکس شده
```javascript
// دریافت همه input ها
const inputs = HomaIndexer.findByType('input');
console.log(`Found ${inputs.length} input fields`);

// دریافت جداول قیمت
const pricingTables = HomaIndexer.findByDiviModule('pricing_table');
```

---

## عیب‌یابی سریع 🔧

### مشکل: Console خالی است
**راه‌حل**: Cache browser را پاک کنید (Ctrl+Shift+Del)

### مشکل: `HomaIndexer is not defined`
**راه‌حل**: صبر کنید تا صفحه کامل load شود
```javascript
jQuery(document).ready(function() {
    // کد شما اینجا
});
```

### مشکل: Tour نمایش داده نمی‌شود
**راه‌حل**: selector را چک کنید
```javascript
// بررسی کنید المان وجود دارد
console.log(document.querySelector('.your-selector'));
```

### مشکل: Intent detection کار نمی‌کند
**راه‌حل**: نیاز به اینترنت برای ارسال به AI دارید. اتصال خود را چک کنید.

---

## منابع بیشتر 📚

- [مستندات کامل PR4](PR4-IMPLEMENTATION.md)
- [مثال‌های استفاده](examples/pr4-usage-examples.php)
- [API Reference](PR4-README.md#api-reference)

---

## پشتیبانی 🤝

سوال دارید؟ Issue باز کنید یا به ما ایمیل بزنید!

**موفق باشید!** 🚀
