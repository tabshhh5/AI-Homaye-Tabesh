# پیادهسازی PR4: Core Intelligence - لایه ادراک محیطی

## نمای کلی

PR4 لایه ادراک محیطی (Environmental Perception Layer) را به همای تابش اضافه می‌کند. این لایه به هما قدرت "دیدن" و "درک کردن" محیط وبسایت را می‌دهد.

## معماری کلی

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (JavaScript)                    │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Semantic   │  │    Input     │  │   Spatial    │     │
│  │   Indexer    │  │   Observer   │  │  Navigator   │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│          │                 │                 │              │
│          └─────────────────┴─────────────────┘              │
│                            │                                 │
│                    ┌──────────────┐                         │
│                    │     Tour     │                         │
│                    │   Manager    │                         │
│                    └──────────────┘                         │
└─────────────────────────────────────────────────────────────┘
                            │
                            v
┌─────────────────────────────────────────────────────────────┐
│                    Backend (PHP)                            │
│                  ┌──────────────────┐                       │
│                  │ Perception Bridge│                       │
│                  └──────────────────┘                       │
│                            │                                 │
│                            v                                 │
│                  ┌──────────────────┐                       │
│                  │ Inference Engine │                       │
│                  └──────────────────┘                       │
└─────────────────────────────────────────────────────────────┘
```

## کامپوننت‌های پیاده‌سازی شده

### 1. HT_Semantic_Indexer_Engine (JavaScript)

**مسئولیت**: نگاشت معنایی تمام المان‌های Divi و فرم‌های WooCommerce

**ویژگی‌های کلیدی**:
- اسکن خودکار صفحه با Tree-Walker pattern
- شناسایی المان‌های فرم (input, textarea, select, button)
- شناسایی ماژول‌های Divi (pricing tables, CTAs, etc.)
- ذخیره‌سازی در Map با کلیدهای معنایی (semantic keys)
- MutationObserver برای محتوای داینامیک و shortcode ها
- عدم نیاز به query مجدد - همه چیز در حافظه است

**API عمومی**:
```javascript
// دسترسی به نقشه کامل
HomaIndexer.map

// جستجو بر اساس نام معنایی
HomaIndexer.findBySemanticName('نام_کتاب')

// جستجو بر اساس نوع المان
HomaIndexer.findByType('input')

// جستجو بر اساس ماژول Divi
HomaIndexer.findByDiviModule('pricing_table')

// دریافت تمام المان‌های ایندکس شده
HomaIndexer.getAll()

// بازآموزی یک المان خاص
HomaIndexer.refreshElement(element)
```

**مثال استفاده**:
```javascript
// پیدا کردن فیلد عنوان کتاب
const bookTitleField = HomaIndexer.findBySemanticName('نام_کتاب');
console.log(bookTitleField);
// Output: { element, rect, semanticName, fieldMeaning, ... }
```

### 2. HT_Live_Input_Observer (JavaScript)

**مسئولیت**: مانیتورینگ زنده ورودی‌های کاربر و تشخیص نیت (Intent Detection)

**ویژگی‌های کلیدی**:
- Debounce با تاخیر 800ms برای کاهش بار
- استخراج Concepts از متن ورودی
- تشخیص الگوها (patterns) - کتاب، چاپ، طراحی، کودک، etc.
- حفاظت از حریم خصوصی - فیلدهای حساس ignore می‌شوند
- سیستم Callback برای واکنش به intent
- ارسال خودکار به سرور برای آنالیز AI

**API عمومی**:
```javascript
// ثبت callback برای رویدادهای intent
HomaInputObserver.onIntent((eventType, data) => {
    if (eventType === 'intent_detected') {
        console.log('Field:', data.fieldName);
        console.log('Value:', data.value);
        console.log('Concepts:', data.concepts);
        console.log('Patterns:', data.concepts.patterns);
    }
});
```

**مثال استفاده**:
```javascript
HomaInputObserver.onIntent((eventType, data) => {
    if (data.concepts.patterns.includes('children_related')) {
        // کاربر در حال تایپ چیزی در مورد کودکان است
        HomaNavigation.scrollTo('[href*="children-books"]', {
            highlight: true
        });
    }
});
```

### 3. HT_Spatial_Navigation_API (JavaScript)

**مسئولیت**: ناوبری هوشمند و مدیریت فوکوس

**ویژگی‌های کلیدی**:
- Smooth scroll با offset قابل تنظیم
- Highlighting خودکار المان‌های target
- تاریخچه ناوبری (Navigation History)
- قابلیت بازگشت به المان قبلی
- Center کردن المان در viewport
- Navigate به فیلدها با نام معنایی

**API عمومی**:
```javascript
// اسکرول به المان
HomaNavigation.scrollTo(target, options)

// فوکوس روی المان (scroll + highlight)
HomaNavigation.focusElement(target, options)

// هایلایت کردن المان
HomaNavigation.highlightElement(target, options)

// ناوبری به فیلد با نام معنایی
HomaNavigation.navigateToField('نام_کتاب')

// بازگشت به المان قبلی
HomaNavigation.navigateBack()

// Center کردن المان
HomaNavigation.centerElement(target)

// دریافت تاریخچه ناوبری
HomaNavigation.getNavigationHistory()
```

**مثال استفاده**:
```javascript
// اسکرول به جدول قیمت و هایلایت کردن آن
HomaNavigation.focusElement('.et_pb_pricing', {
    offset: 100,
    duration: 800,
    highlight: true
}).then(() => {
    console.log('User focused on pricing table');
});

// ناوبری به فیلد تیراژ
HomaNavigation.navigateToField('تیراژ');
```

### 4. Interactive_Tour_Overlay (JavaScript)

**مسئولیت**: تورهای آموزشی تعاملی با overlay و tooltip

**ویژگی‌های کلیدی**:
- Overlay تیره برای تمرکز روی المان target
- Highlight box با انیمیشن pulse
- Tooltip با محتوای قابل سفارشی‌سازی
- پشتیبانی از تورهای چند مرحله‌ای
- دکمه‌های ناوبری (بعدی، قبلی، پایان)
- Z-index بالا برای نمایش روی همه المان‌ها
- Auto-scroll به المان target

**API عمومی**:
```javascript
// شروع تور چند مرحله‌ای
HomaTour.start(tourConfig)

// رفتن به مرحله بعدی
HomaTour.next()

// بازگشت به مرحله قبلی
HomaTour.previous()

// رفتن به مرحله خاص
HomaTour.goToStep(stepIndex)

// پایان تور
HomaTour.end()

// چک کردن فعال بودن تور
HomaTour.isActive()

// نمایش یک مرحله منفرد (بدون تور کامل)
startHomaTour(stepConfig)
```

**مثال استفاده**:
```javascript
// تور کامل برای سفارش چاپ کتاب
HomaTour.start({
    title: 'راهنمای سفارش چاپ کتاب',
    steps: [
        {
            selector: '#book_title',
            title: 'عنوان کتاب',
            message: 'ابتدا نام کتاب خود را در این فیلد وارد کنید'
        },
        {
            selector: '#book_pages',
            title: 'تعداد صفحات',
            message: 'تعداد صفحات کتاب خود را مشخص کنید'
        },
        {
            selector: '.et_pb_pricing',
            title: 'جدول قیمت',
            message: 'قیمت نهایی در اینجا محاسبه می‌شود'
        }
    ]
});

// یا نمایش یک tooltip ساده
startHomaTour({
    selector: '.calculate-btn',
    title: 'محاسبه قیمت',
    message: 'با کلیک روی این دکمه، قیمت محاسبه می‌شود'
});
```

### 5. HT_Perception_Bridge (PHP)

**مسئولیت**: پل ارتباطی بین frontend و backend

**ویژگی‌های کلیدی**:
- REST API endpoints برای آنالیز intent
- REST API برای پیشنهادات ناوبری
- REST API برای دریافت تورهای از پیش تعریف شده
- Enqueue کردن اسکریپت‌های perception layer
- تزریق configuration به frontend
- اتصال به Inference Engine برای تحلیل AI

**Endpoints**:
```
POST /wp-json/homaye/v1/ai/analyze-intent
POST /wp-json/homaye/v1/navigation/suggest
GET  /wp-json/homaye/v1/tour/get-steps?workflow=book_printing
```

**مثال استفاده از PHP**:
```php
// آنالیز نیت کاربر
$response = wp_remote_post(rest_url('homaye/v1/ai/analyze-intent'), [
    'body' => json_encode([
        'field_name' => 'نام کتاب',
        'field_value' => 'رمان عاشقانه',
        'concepts' => ['keywords' => ['رمان', 'عاشقانه']]
    ])
]);

// دریافت پیشنهادات ناوبری
$response = wp_remote_post(rest_url('homaye/v1/navigation/suggest'), [
    'body' => json_encode([
        'current_location' => '/products/book-printing/'
    ])
]);
```

## ادغام با PR های قبلی

### ادغام با PR1 (Telemetry)
- Input Observer از telemetry برای ارسال event استفاده می‌کند
- Navigation history در telemetry ثبت می‌شود

### ادغام با PR2 (Persona)
- Perception Bridge از persona کاربر برای پیشنهادات استفاده می‌کند
- Intent analysis بر اساس persona شخصی‌سازی می‌شود

### ادغام با PR3 (Inference Engine)
- Perception Bridge نتایج perception را به Inference Engine می‌فرستد
- AI بر اساس perception تصمیم می‌گیرد و action صادر می‌کند
- Tour و navigation بر اساس توصیه‌های AI اجرا می‌شوند

## تنظیمات و Configuration

تمام ماژول‌ها از `window.homayeConfig` برای configuration استفاده می‌کنند:

```javascript
window.homayeConfig = {
    apiUrl: '/wp-json/homaye/v1/ai/analyze-intent',
    navigationUrl: '/wp-json/homaye/v1/navigation/suggest',
    tourUrl: '/wp-json/homaye/v1/tour/get-steps',
    nonce: 'wp_rest_nonce_here',
    enableIntentAnalysis: true,
    enableSemanticMapping: true,
    enableTours: true
};
```

## حفاظت از حریم خصوصی

فیلدهای زیر به طور خودکار از monitoring خارج می‌شوند:
- `input[type="password"]`
- `input[type="hidden"]`
- فیلدهایی با `data-homa-ignore` attribute
- فیلدهای حاوی کلمات حساس: password, credit, card, cvv, ssn, کدملی

```html
<!-- این فیلد monitor نمی‌شود -->
<input type="text" name="credit_card" data-homa-ignore>
```

## تست و اعتبارسنجی

### تست در Console:

```javascript
// 1. بررسی بارگذاری ماژول‌ها
console.log('Indexer:', typeof HomaIndexer);
console.log('Input Observer:', typeof HomaInputObserver);
console.log('Navigator:', typeof HomaSpatialNavigator);
console.log('Tour Manager:', typeof HomaTourManager);

// 2. بررسی ایندکس
console.log('Indexed elements:', HomaIndexer.getAll().length);

// 3. تست ناوبری
HomaNavigation.navigateToField('نام_کتاب');

// 4. تست تور
startHomaTour({
    selector: '#book_title',
    title: 'تست',
    message: 'این یک تست است'
});
```

### تست Functional:

1. **تست Semantic Indexer**:
   - باز کردن صفحه با فرم
   - چک کردن `HomaIndexer.map` در console
   - باید تمام فیلدها و دکمه‌ها ایندکس شده باشند

2. **تست Input Observer**:
   - تایپ در فیلد "نام کتاب"
   - صبر 800ms
   - باید در console لاگ intent_detected نمایش داده شود

3. **تست Navigation**:
   - اجرای `HomaNavigation.scrollTo('.et_pb_pricing')`
   - باید صفحه به جدول قیمت اسکرول شود
   - المان باید highlight شود

4. **تست Tour**:
   - شروع یک تور با `HomaTour.start()`
   - باید overlay تیره نمایش داده شود
   - المان target باید highlight شود
   - tooltip باید نمایش داده شود

## فایل‌های پیاده‌سازی شده

### JavaScript Files:
- `assets/js/homa-indexer.js` - Semantic Indexer
- `assets/js/homa-input-observer.js` - Live Input Observer
- `assets/js/homa-spatial-navigator.js` - Spatial Navigation API
- `assets/js/homa-tour-manager.js` - Interactive Tour Manager

### PHP Files:
- `includes/HT_Perception_Bridge.php` - Server-side bridge
- `includes/HT_Core.php` - Updated to initialize perception bridge

### Documentation:
- `examples/pr4-usage-examples.php` - Usage examples
- `PR4-IMPLEMENTATION.md` - This file

## نکات فنی مهم

### 1. MutationObserver برای محتوای داینامیک
تمام ماژول‌ها از MutationObserver استفاده می‌کنند تا محتوای داینامیک (shortcodes، AJAX، Divi Visual Builder) را شناسایی و پردازش کنند.

### 2. Debouncing برای Performance
Input Observer از debouncing استفاده می‌کند تا از ارسال درخواست‌های بیش از حد جلوگیری کند.

### 3. WeakSet برای Memory Management
برای جلوگیری از memory leak، از WeakSet برای نگهداری المان‌های observed استفاده می‌شود.

### 4. Z-Index Management
Tour overlay از z-index بسیار بالا (999990+) استفاده می‌کند تا روی تمام المان‌ها (حتی منوهای Divi) نمایش داده شود.

### 5. Promise-based Navigation
تمام متدهای ناوبری Promise برمی‌گردانند برای chain کردن عملیات.

## محدودیت‌ها و ملاحظات

1. **DOM Mutation**: در صورت تغییرات مکرر DOM،ممکن است rescan مکرر انجام شود
2. **Input Privacy**: فیلدهای حساس باید صریحاً با `data-homa-ignore` مشخص شوند
3. **Z-Index Conflicts**: در صورت استفاده از z-index بسیار بالا در سایر بخش‌ها، ممکن است tour overlay پوشانده شود
4. **Browser Compatibility**: نیاز به مرورگرهای مدرن با پشتیبانی از MutationObserver و Promise

## روندنگاری (Roadmap)

آینده‌های احتمالی:
- [ ] Voice navigation support
- [ ] Gesture-based tours
- [ ] Advanced pattern recognition with ML
- [ ] Accessibility improvements (ARIA support)
- [ ] Multi-language intent detection
- [ ] Offline tour caching

## نتیجه‌گیری

PR4 به همای تابش قدرت "دیدن" و "درک کردن" محیط را می‌دهد. این لایه ادراک محیطی پایه‌ای برای تصمیم‌گیری‌های هوشمند‌تر و تعامل بهتر با کاربر است.
