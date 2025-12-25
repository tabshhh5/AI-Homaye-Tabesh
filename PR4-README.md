# PR4 - Core Intelligence: لایه ادراک محیطی

## خلاصه تغییرات

این PR چهارم در سری توسعه افزونه همای تابش است که **لایه ادراک محیطی** (Environmental Perception Layer) را پیاده‌سازی می‌کند. این لایه به هما توانایی "دیدن"، "درک کردن" و "تعامل کردن" با محیط وبسایت را می‌دهد.

## ویژگی‌های جدید

### 1. 🗺️ موتور ایندکس معنایی (Semantic Indexer)
- نگاشت خودکار تمام المان‌های Divi، فرم‌ها و دکمه‌ها
- ذخیره‌سازی در Map با کلیدهای معنایی
- پشتیبانی از محتوای داینامیک و shortcode ها
- شناسایی هوشمند فیلدها با استفاده از label، placeholder، aria-label

### 2. 👀 مانیتور ورودی زنده (Live Input Observer)
- رصد real-time ورودی‌های کاربر
- تشخیص نیت کاربر (Intent Detection) حین تایپ
- استخراج خودکار concepts و patterns
- حفاظت از حریم خصوصی برای فیلدهای حساس
- Debouncing هوشمند برای کاهش بار

### 3. 🧭 API ناوبری فضایی (Spatial Navigation)
- Scroll هوشمند به المان‌های خاص
- Highlight کردن المان‌های target
- تاریخچه ناوبری با قابلیت بازگشت
- Center کردن المان در viewport
- Promise-based API برای chain کردن عملیات

### 4. 🎯 مدیریت تور تعاملی (Interactive Tour Manager)
- تورهای آموزشی گام‌به‌گام
- Overlay تیره با highlight روی المان target
- Tooltip های زیبا با انیمیشن
- پشتیبانی از تورهای چند مرحله‌ای
- دکمه‌های ناوبری (بعدی، قبلی، پایان)

### 5. 🌉 پل ادراکی (Perception Bridge)
- REST API endpoints برای ارتباط با backend
- آنالیز نیت با استفاده از Inference Engine
- پیشنهادات ناوبری شخصی‌سازی شده بر اساس persona
- تورهای از پیش تعریف شده برای workflow های مختلف

## فایل‌های جدید

### JavaScript:
```
assets/js/
├── homa-indexer.js              # Semantic Indexer
├── homa-input-observer.js       # Live Input Observer
├── homa-spatial-navigator.js    # Spatial Navigation API
└── homa-tour-manager.js         # Interactive Tour Manager
```

### PHP:
```
includes/
├── HT_Perception_Bridge.php     # Server-side bridge
└── HT_Core.php                  # Updated with perception bridge
```

### Documentation:
```
examples/pr4-usage-examples.php  # Usage examples
PR4-IMPLEMENTATION.md            # Technical documentation
PR4-README.md                    # This file
```

## نصب و راه‌اندازی

### 1. نیازمندی‌ها
- WordPress >= 6.0
- PHP >= 8.2
- Divi Theme (اختیاری اما توصیه می‌شود)
- PR1, PR2, PR3 باید نصب شده باشند

### 2. فعال‌سازی
پس از merge این PR، تمام ماژول‌ها به طور خودکار فعال می‌شوند.

### 3. تنظیمات (اختیاری)
در صورت نیاز می‌توانید در فایل `functions.php` تنظیمات را شخصی‌سازی کنید:

```php
add_filter('homaye_perception_config', function($config) {
    $config['enableIntentAnalysis'] = true;
    $config['enableSemanticMapping'] = true;
    $config['enableTours'] = true;
    return $config;
});
```

## استفاده سریع

### مثال 1: نمایش یک tooltip آموزشی

```javascript
startHomaTour({
    selector: '.et_pb_pricing',
    title: 'جدول قیمت',
    message: 'اینجا می‌توانید قیمت‌های مختلف چاپ را ببینید'
});
```

### مثال 2: ناوبری به یک فیلد خاص

```javascript
HomaNavigation.navigateToField('نام_کتاب');
```

### مثال 3: شروع یک تور کامل

```javascript
HomaTour.start({
    title: 'راهنمای سفارش چاپ',
    steps: [
        {
            selector: '#book_title',
            title: 'عنوان کتاب',
            message: 'نام کتاب خود را وارد کنید'
        },
        {
            selector: '#book_pages',
            title: 'تعداد صفحات',
            message: 'تعداد صفحات را مشخص کنید'
        }
    ]
});
```

### مثال 4: واکنش به input کاربر

```javascript
HomaInputObserver.onIntent((eventType, data) => {
    if (data.concepts.patterns.includes('book_related')) {
        console.log('کاربر در مورد کتاب صحبت می‌کند!');
        // انجام عملیات مناسب...
    }
});
```

## تست

### تست در Browser Console:

```javascript
// بررسی بارگذاری ماژول‌ها
console.log(HomaIndexer, HomaInputObserver, HomaSpatialNavigator, HomaTourManager);

// مشاهده المان‌های ایندکس شده
console.log('Total indexed:', HomaIndexer.getAll().length);

// تست ناوبری
HomaNavigation.scrollTo('.et_pb_pricing', { highlight: true });

// تست تور
startHomaTour({
    selector: '#book_title',
    title: 'تست',
    message: 'این یک تست است'
});
```

## API Reference

### HomaIndexer

```javascript
HomaIndexer.map                          // نقشه کامل المان‌ها
HomaIndexer.findBySemanticName(name)     // جستجو با نام معنایی
HomaIndexer.findByType(type)             // جستجو با نوع المان
HomaIndexer.findByDiviModule(module)     // جستجو با ماژول Divi
HomaIndexer.getAll()                     // دریافت همه المان‌ها
```

### HomaInputObserver

```javascript
HomaInputObserver.onIntent(callback)     // ثبت callback
HomaInputObserver.getBufferData(id)      // دریافت buffer data
HomaInputObserver.clearBuffer()          // پاک کردن buffer
```

### HomaNavigation

```javascript
HomaNavigation.scrollTo(target, options)
HomaNavigation.focusElement(target, options)
HomaNavigation.highlightElement(target, options)
HomaNavigation.navigateToField(fieldName)
HomaNavigation.navigateBack()
HomaNavigation.centerElement(target)
HomaNavigation.getNavigationHistory()
```

### HomaTour

```javascript
HomaTour.start(tourConfig)
HomaTour.next()
HomaTour.previous()
HomaTour.goToStep(index)
HomaTour.end()
HomaTour.isActive()
```

## REST API Endpoints

### آنالیز نیت کاربر
```
POST /wp-json/homaye/v1/ai/analyze-intent
Body: {
    field_name: string,
    field_value: string,
    concepts: object,
    is_final: boolean
}
```

### پیشنهادات ناوبری
```
POST /wp-json/homaye/v1/navigation/suggest
Body: {
    current_location: string,
    user_context: object
}
```

### دریافت تور
```
GET /wp-json/homaye/v1/tour/get-steps?workflow=book_printing
```

## حفاظت از حریم خصوصی

فیلدهای زیر به طور خودکار از monitoring خارج می‌شوند:
- `input[type="password"]`
- `input[type="hidden"]`
- فیلدهایی با attribute `data-homa-ignore`
- فیلدهای حاوی کلمات حساس (credit card, password, etc.)

```html
<!-- این فیلد monitor نمی‌شود -->
<input type="text" name="sensitive_field" data-homa-ignore>
```

## ادغام با PRهای قبلی

### PR1 (Telemetry)
- Input Observer events را به telemetry می‌فرستد
- Navigation history ثبت می‌شود

### PR2 (Persona)
- پیشنهادات بر اساس persona شخصی‌سازی می‌شوند
- Intent analysis با persona هماهنگ است

### PR3 (Inference Engine)
- Perception data به AI فرستاده می‌شود
- AI بر اساس perception تصمیم می‌گیرد
- Actions توسط Tour و Navigation اجرا می‌شوند

## Performance و Optimization

### Debouncing
Input Observer از debouncing 800ms استفاده می‌کند.

### Memory Management
از WeakSet برای جلوگیری از memory leak استفاده می‌شود.

### Lazy Loading
اسکریپت‌ها فقط در frontend load می‌شوند.

### Mutation Observer
فقط در صورت تغییرات DOM rescan انجام می‌شود.

## محدودیت‌ها

1. نیاز به مرورگرهای مدرن (Chrome 60+, Firefox 55+, Safari 10+)
2. محتوای داینامیک با تاخیر ممکن است چند بار rescan شود
3. Z-index بالا ممکن است با برخی المان‌ها conflict داشته باشد
4. Intent analysis نیاز به اتصال به سرور دارد

## Troubleshooting

### مشکل: ماژول‌ها load نمی‌شوند
```javascript
// در console بررسی کنید:
console.log(typeof HomaIndexer); // باید 'object' باشد
```
اگر `undefined` است، cache browser را پاک کنید.

### مشکل: Tour نمایش داده نمی‌شود
```javascript
// بررسی کنید المان target وجود دارد:
document.querySelector('.your-selector');
```

### مشکل: Intent detection کار نمی‌کند
```javascript
// بررسی کنید nonce تنظیم شده است:
console.log(homayePerceptionConfig.nonce);
```

## مشارکت

برای گزارش باگ یا درخواست ویژگی جدید، issue باز کنید.

## لایسنس

GPL v3 or later

## نویسندگان

- Tabshhh4 (@tabshhh4-sketch)
- GitHub Copilot

## تشکر

از تیم Divi و WooCommerce برای ابزارهای عالیشان تشکر می‌کنیم.
