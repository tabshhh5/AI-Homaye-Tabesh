# PR6 README - Parallel UI با React

## 🎯 هدف این PR

پیاده‌سازی **زیرساخت Parallel UI با React** که به هما امکان می‌دهد به‌صورت همزمان در کنار سایت اصلی (Divi) فعال باشد و کاربر بتواند هم با چت تعامل کند و هم با فرم‌های سایت.

## 🆕 چه چیزی اضافه شد؟

### ۱. محیط React
- ⚛️ **React 18**: استفاده از آخرین نسخه React
- 📦 **Webpack**: سیستم build برای کامپایل React
- 🔧 **Babel**: ترنسپایل JSX به JavaScript
- 🗃️ **Zustand**: مدیریت state چت

### ۲. موتور فشردهسازی (Viewport Squeeze)
- 📏 **Flexbox Layout**: سیستم layout انعطاف‌پذیر
- 🎬 **Smooth Animations**: انیمیشن 600ms با cubic-bezier
- 📊 **70/30 Split**: سایت ۷۰٪ و سایدبار ۳۰٪
- 🔄 **Window Resize Trigger**: بازمحاسبه خودکار Divi modules

### ۳. کامپوننت‌های React
- 💬 **HomaSidebar**: کامپوننت اصلی سایدبار
- 📝 **MessageList**: نمایش پیام‌ها با streaming effect
- ⌨️ **ChatInput**: ورودی پیام با پشتیبانی RTL
- 🎯 **SmartChips**: دکمه‌های پیشنهادی بر اساس پرسونا

### ۴. ارتباط دوطرفه (Context Bridge)
- 📡 **CustomEvents**: ارتباط بین React و Vanilla JS
- 🔗 **Form Observer**: ردیابی تغییرات فرم در سایت
- 🎯 **DOM Control**: کنترل عناصر سایت از سایدبار

### ۵. طراحی UI/UX
- 🎨 **Gradient Design**: طراحی مدرن با gradient
- ✨ **Pulse Animation**: انیمیشن highlight برای عناصر
- 📱 **Responsive**: پشتیبانی کامل از موبایل
- ♿ **Accessibility**: پشتیبانی از کاربران با نیازهای خاص

### ۶. Floating Action Button (FAB)
- 🔘 **Toggle Button**: دکمه شناور برای باز/بسته کردن
- 💫 **Pulse Effect**: انیمیشن pulse برای جلب توجه
- 📍 **Smart Position**: موقعیت هوشمند در موبایل و دسکتاپ

---

## 🚀 نصب و راه‌اندازی

### مرحله ۱: نصب Dependencies

```bash
cd /path/to/homaye-tabesh
npm install
```

### مرحله ۲: Build کردن React

```bash
npm run build
```

برای development (با watch mode):

```bash
npm run dev
```

### مرحله ۳: فعال‌سازی در WordPress

افزونه را فعال کنید. اسکریپت‌ها به‌صورت خودکار بارگذاری می‌شوند.

### مرحله ۴: تست کردن

۱. صفحه‌ای از سایت را باز کنید
۲. دکمه FAB (پایین سمت چپ) را کلیک کنید
۳. سایدبار هما باید به‌صورت smooth باز شود
۴. سایت باید به ۷۰٪ فشرده شود

---

## 📖 نحوه استفاده

### باز کردن سایدبار از JavaScript

```javascript
// باز کردن سایدبار
document.dispatchEvent(new CustomEvent('homa:open-sidebar'));

// بستن سایدبار
document.dispatchEvent(new CustomEvent('homa:close-sidebar'));

// Toggle سایدبار
document.dispatchEvent(new CustomEvent('homa:toggle-sidebar'));
```

### کنترل عناصر سایت از سایدبار

```javascript
// Highlight کردن یک عنصر
window.HomaOrchestrator.executeOnSite('.et_pb_button_0', 'highlight');

// اسکرول به یک عنصر
window.HomaOrchestrator.executeOnSite('#product-selector', 'scroll');

// کلیک روی یک عنصر
window.HomaOrchestrator.executeOnSite('#submit-btn', 'click');

// Focus روی یک input
window.HomaOrchestrator.executeOnSite('#book-title', 'focus');
```

### ارسال تغییرات فرم به سایدبار

```javascript
// تغییرات فرم به‌صورت خودکار ردیابی می‌شوند
// اما می‌توانید manual هم trigger کنید:
window.dispatchEvent(new CustomEvent('homa_site_updated', {
    detail: {
        fieldId: 'book_title',
        value: 'کتاب من',
        fieldType: 'text'
    }
}));
```

### دریافت وضعیت viewport

```javascript
const state = window.HomaOrchestrator.getViewportState();
console.log(state);
// {
//   isOpen: true,
//   siteViewWidth: 1400,
//   sidebarViewWidth: 600,
//   totalWidth: 2000
// }
```

---

## 🔧 API Reference

### JavaScript APIs

#### HomaOrchestrator

```javascript
// Initialize (called automatically)
window.HomaOrchestrator.init();

// Open/Close/Toggle
window.HomaOrchestrator.openSidebar();
window.HomaOrchestrator.closeSidebar();
window.HomaOrchestrator.toggleSidebar();

// Execute action on site element
window.HomaOrchestrator.executeOnSite(selector, action);
// Actions: 'highlight', 'scroll', 'click', 'focus'

// Get viewport state
window.HomaOrchestrator.getViewportState();

// Recalculate Divi modules
window.HomaOrchestrator.recalculateDiviModules();
```

#### React Store (Zustand)

```javascript
// از داخل React components
import { useHomaStore } from '../store/homaStore';

const { messages, addMessage, userPersona } = useHomaStore();

// اضافه کردن پیام
addMessage({
    id: Date.now(),
    type: 'user',
    content: 'سلام',
    timestamp: new Date()
});

// تنظیم پرسونا
setUserPersona('نویسنده');
```

### REST APIs

#### POST `/wp-json/homaye/v1/ai/chat`

ارسال پیام به هما و دریافت پاسخ

**Request:**
```json
{
  "message": "می‌خواهم کتابم را چاپ کنم",
  "persona": "نویسنده",
  "context": {
    "page": "/order-form",
    "formData": {
      "book_title": "ققنوس",
      "pages": "240"
    }
  }
}
```

**Response:**
```json
{
  "success": true,
  "response": "حتماً! برای چاپ کتاب ۲۴۰ صفحه‌ای، چاپ دیجیتال پیشنهاد می‌کنم.",
  "actions": [
    {
      "type": "highlight",
      "selector": ".digital-print-option"
    }
  ],
  "persona": "نویسنده"
}
```

#### GET `/wp-json/homaye/v1/sidebar/state`

دریافت وضعیت سایدبار

**Response:**
```json
{
  "success": true,
  "state": {
    "persona": "نویسنده",
    "chat_enabled": true,
    "features": {
      "form_sync": true,
      "smart_chips": true,
      "dom_control": true
    }
  }
}
```

### PHP APIs

```php
$core = \HomayeTabesh\HT_Core::instance();
$parallel_ui = $core->parallel_ui;

// دریافت وضعیت (از داخل REST handler)
// توسط خود کلاس مدیریت می‌شود
```

---

## 🎨 ساختار فایل‌ها

```
homaye-tabesh/
├── assets/
│   ├── build/
│   │   ├── homa-sidebar.js         # React bundle (compiled)
│   │   └── homa-sidebar.js.LICENSE.txt
│   ├── css/
│   │   └── homa-parallel-ui.css    # Main styles
│   ├── js/
│   │   ├── homa-orchestrator.js    # Viewport manager
│   │   └── homa-fab.js             # Floating button
│   └── react/
│       ├── components/
│       │   ├── HomaSidebar.jsx     # Main component
│       │   ├── MessageList.jsx     # Messages display
│       │   ├── ChatInput.jsx       # Input field
│       │   └── SmartChips.jsx      # Action chips
│       ├── store/
│       │   └── homaStore.js        # Zustand store
│       ├── styles/
│       │   └── parallel-ui.css     # React styles
│       └── index.js                # Entry point
├── includes/
│   ├── HT_Core.php                 # Updated with parallel_ui
│   └── HT_Parallel_UI.php          # Manager class
├── package.json                    # npm config
├── webpack.config.js               # Build config
└── .babelrc                        # Babel config
```

---

## 🎬 نحوه کار

### 1. ساختار DOM

وقتی صفحه لود می‌شود:

```html
<body class="homa-parallel-ui-enabled">
    <div id="homa-global-wrapper">
        <div id="homa-site-view">
            <!-- محتوای Divi اینجا منتقل می‌شود -->
        </div>
        <div id="homa-sidebar-view">
            <!-- React App اینجا render می‌شود -->
        </div>
    </div>
    <button class="homa-fab">...</button>
</body>
```

### 2. جریان باز شدن سایدبار

1. کاربر روی FAB کلیک می‌کند
2. Event `homa:toggle-sidebar` trigger می‌شود
3. `HomaOrchestrator.openSidebar()` فراخوانی می‌شود
4. کلاس `homa-open` به body اضافه می‌شود
5. CSS transition شروع می‌شود (600ms)
6. پس از 650ms، `window.resize` event trigger می‌شود
7. Divi modules دوباره محاسبه می‌شوند

### 3. جریان ارسال پیام

1. کاربر پیامی می‌نویسد و ارسال می‌کند
2. پیام به state اضافه می‌شود (Zustand)
3. درخواست POST به `/ai/chat` ارسال می‌شود
4. پاسخ AI دریافت می‌شود
5. پیام با streaming effect نمایش داده می‌شود
6. Actions (اگر وجود داشته باشد) اجرا می‌شوند

### 4. جریان همگام‌سازی فرم

1. کاربر فیلدی در سایت تغییر می‌دهد
2. `Form Observer` تغییر را detect می‌کند
3. Event `homa_site_updated` trigger می‌شود
4. React listener رویداد را می‌گیرد
5. هما می‌تواند واکنش نشان دهد

---

## 📱 رفتار در موبایل

در صفحات کوچک‌تر از ۷۶۸px:

- Layout از افقی به عمودی تغییر می‌کند
- سایت: ۶۰٪ بالا
- سایدبار: ۴۰٪ پایین (مثل bottom sheet)
- FAB به پایین منتقل می‌شود

---

## ⚡ بهینه‌سازی‌ها

✅ **External React**: React از CDN بارگذاری می‌شود (کش بهتر)  
✅ **Code Splitting**: فقط sidebar bundle بارگذاری می‌شود  
✅ **CSS-in-JS**: استایل‌ها به‌صورت مدولار  
✅ **Debouncing**: Form observer با debounce 300ms  
✅ **Will-change**: بهینه‌سازی انیمیشن  
✅ **Passive Events**: کاهش blocking در event listeners  

---

## 🔒 امنیت

✅ **Nonce Verification**: تمام REST endpoints  
✅ **Sanitization**: پاکسازی تمام ورودی‌ها  
✅ **Permission Callbacks**: بررسی دسترسی  
✅ **XSS Prevention**: استفاده از React (auto-escaping)  
✅ **Guest Tracking**: مدیریت امن مهمان‌ها  

---

## 🧪 تست کردن

### سناریوی ۱: باز/بسته کردن

1. روی FAB کلیک کنید
2. سایدبار باید smooth باز شود
3. سایت باید به چپ فشرده شود
4. روی دکمه X کلیک کنید
5. سایدبار باید بسته شود

### سناریوی ۲: تعامل همزمان

1. سایدبار را باز کنید
2. در چت تایپ کنید
3. همزمان روی فرم سمت چپ کلیک کنید
4. هر دو باید responsive باشند

### سناریوی ۳: Highlight عنصر

1. در کنسول: `window.HomaOrchestrator.executeOnSite('.et_pb_button', 'highlight')`
2. دکمه باید با انیمیشن pulse highlight شود

### سناریوی ۴: Chat History

1. چند پیام ارسال کنید
2. صفحه را refresh کنید
3. پیام‌ها باید باقی بمانند (localStorage)

---

## 🐛 عیب‌یابی

### Build موفق نمی‌شود

```bash
# پاک کردن و نصب مجدد
rm -rf node_modules package-lock.json
npm install
npm run build
```

### سایدبار نمایش داده نمی‌شود

1. F12 -> Console -> چک کنید خطایی وجود دارد؟
2. Build file را چک کنید: `ls -la assets/build/`
3. WP_DEBUG را فعال کنید و لاگ‌ها را بررسی کنید

### انیمیشن smooth نیست

1. مطمئن شوید `will-change` در CSS فعال است
2. GPU acceleration را چک کنید
3. در مرورگر دیگری تست کنید

### Divi modules درست کار نمی‌کنند

1. مطمئن شوید `window.resize` trigger می‌شود
2. `recalculateDiviModules()` را manual فراخوانی کنید
3. Divi را به آخرین نسخه آپدیت کنید

---

## 📚 مستندات بیشتر

- 📖 [مستندات کامل PR6](./PR6-IMPLEMENTATION.md) (در حال نوشتن)
- 🚀 [راهنمای سریع](./PR6-QUICKSTART.md) (در حال نوشتن)
- 💡 [مثال‌های استفاده](./examples/pr6-usage-examples.php) (در حال نوشتن)

---

## 🤝 مشارکت

اگر مشکلی پیدا کردید یا پیشنهادی دارید:
1. Issue ایجاد کنید
2. PR ارسال کنید
3. در Discussion شرکت کنید

---

## 📝 Changelog

### نسخه 1.0.0 (PR6) - 2025-12-25

**اضافه شده:**
- ✅ محیط React با Webpack و Babel
- ✅ موتور فشردهسازی viewport
- ✅ کامپوننت‌های React (Sidebar, Messages, Input, Chips)
- ✅ Orchestrator برای مدیریت layout
- ✅ Context Bridge برای ارتباط دوطرفه
- ✅ Floating Action Button (FAB)
- ✅ REST API endpoints
- ✅ Chat history با localStorage
- ✅ Smart chips بر اساس پرسونا
- ✅ Streaming text effect

**بهبود یافته:**
- ✅ سازگاری با Divi
- ✅ Performance انیمیشن‌ها
- ✅ Responsive design

---

**ساخته شده با ❤️ و ⚛️ React**
