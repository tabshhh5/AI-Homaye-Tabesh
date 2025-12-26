# PR10: Visual Guidance Unit Implementation

## موتور تعامل بصری (Interactive Execution Engine)

### خلاصه
PR10 سیستم هدایت بصری پیشرفته را به هما اضافه می‌کند. این ویژگی به هما اجازه می‌دهد که کاربران را به صورت فیزیکی در صفحه راهنمایی کند - نه فقط با کلمات، بلکه با هایلایت کردن، اسکرول، و نمایش تولتیپ‌های تعاملی.

### مشکلی که حل می‌شود
قبل از PR10، هما فقط می‌توانست پاسخ‌های متنی بدهد. اگر کاربر می‌پرسید "چطوری سفارش بدم؟"، هما می‌گفت "روی دکمه ثبت سفارش کلیک کنید" - اما کاربر باید خودش دکمه را پیدا می‌کرد. این یک Friction در قیف فروش بود.

حالا هما می‌تواند:
- دکمه را هایلایت کند
- صفحه را به دکمه اسکرول کند
- یک تولتیپ راهنما روی دکمه نشان دهد
- محصولات مکمل را در ویجت اکسپلور پیشنهاد دهد
- پیام‌های زنده از ادمین دریافت و نمایش دهد

## معماری

### اجزای اصلی

#### 1. HT_DOM_Action_Controller (PHP)
کنترلر مرکزی سمت سرور که:
- REST API endpoints را ثبت می‌کند
- دستورات بصری را مدیریت می‌کند
- تاریخچه اکشن‌ها را ذخیره می‌کند

**فایل:** `includes/HT_DOM_Action_Controller.php`

**Endpoints:**
- `POST /wp-json/homaye/v1/visual/action` - اجرای یک اکشن بصری
- `GET /wp-json/homaye/v1/visual/history` - دریافت تاریخچه اکشن‌ها

#### 2. HomaVisualGuidance (JavaScript)
موتور سمت کلاینت که اکشن‌های بصری را اجرا می‌کند:
- هایلایت المان‌ها با افکت Glow
- اسکرول هوشمند به المان‌ها
- نمایش تولتیپ‌های تعاملی
- مدیریت چندین افکت همزمان

**فایل:** `assets/js/homa-visual-guidance.js`

**متدها:**
```javascript
window.HomaVisualGuidance.executeAction({
    command: 'HIGHLIGHT',
    target_selector: '.checkout-button',
    duration: 5000
});
```

#### 3. Visual Effects (CSS)
استایل‌های پیشرفته برای افکت‌های بصری:
- `homa-glow-effect` - افکت نور پالس‌دار
- `homa-pulse-effect` - افکت تپش
- `homa-visual-tooltip` - تولتیپ‌های زیبا با انیمیشن
- پشتیبانی کامل از RTL، Mobile، و Accessibility

**فایل:** `assets/css/homa-visual-effects.css`

#### 4. ExploreWidget (React)
کامپوننت React برای نمایش پیشنهادات شخصی‌سازی شده:
- نمایش محصولات مکمل بر اساس علایق
- فیلتر بر اساس دسته‌بندی
- انیمیشن‌های زیبا (Instagram-like)
- اتصال مستقیم به Vault (PR7)

**فایل:** `assets/react/components/ExploreWidget.jsx`

#### 5. AI Visual Command Parser (Gemini Integration)
توسعه HT_Gemini_Client برای تولید دستورات بصری:
- `generate_with_visual_commands()` - تولید پاسخ با دستورات بصری
- `extract_visual_commands()` - استخراج دستورات از متن
- `clean_visual_commands()` - پاکسازی متن از دستورات

**فایل:** `includes/HT_Gemini_Client.php`

**فرمت دستورات:**
```
ACTION: HIGHLIGHT[.checkout-button]
ACTION: SCROLL_TO[#order-form]
ACTION: TOOLTIP[.price, این قیمت شامل تخفیف است]
```

#### 6. Admin Live Intervention
سیستم پیام‌رسانی Real-time بین ادمین و کاربر:
- پنل ادمین برای ارسال پیام به کاربران فعال
- Long Polling برای دریافت پیام‌های زنده
- اجرای همزمان دستورات بصری
- Browser Notifications

**فایل‌ها:**
- `includes/HT_Admin_Intervention.php`
- `assets/js/homa-intervention-admin.js`
- `assets/js/homa-intervention-listener.js`

## نصب و راه‌اندازی

### 1. Build Assets
```bash
npm install
npm run build
```

### 2. فعال‌سازی در WordPress
افزونه به صورت خودکار تمام اجزای PR10 را فعال می‌کند:
- DOM Action Controller
- Visual Guidance Engine
- Explore Widget
- Admin Intervention Bridge

### 3. تنظیمات
هیچ تنظیم خاصی لازم نیست. همه چیز out-of-the-box کار می‌کند.

## استفاده

### برای توسعه‌دهندگان

#### اجرای دستی دستورات بصری
```javascript
// Highlight
window.Homa.emit('visual:action', {
    command: 'HIGHLIGHT',
    target_selector: '.my-button',
    duration: 5000
});

// Tooltip
window.Homa.emit('visual:action', {
    command: 'SHOW_TOOLTIP',
    target_selector: '.help-icon',
    message: 'اینجا کلیک کنید',
    duration: 10000
});

// Scroll
window.Homa.emit('visual:action', {
    command: 'SCROLL_TO',
    target_selector: '#contact-form'
});
```

#### استفاده از Gemini برای دستورات بصری
```php
$gemini = new HT_Gemini_Client();

$page_elements = [
    ['label' => 'دکمه ثبت سفارش', 'selector' => '.checkout-button'],
    ['label' => 'فرم تماس', 'selector' => '#contact-form']
];

$response = $gemini->generate_with_visual_commands(
    'چطوری سفارش بدم؟',
    ['page_type' => 'shop'],
    $page_elements
);

// Response includes:
// - raw_text: پاسخ متنی
// - visual_commands: آرایه‌ای از دستورات
```

### برای ادمین‌ها

#### ارسال پیام زنده به کاربر
1. بروید به **همای تابش > 💬 مداخله زنده**
2. یک جلسه فعال را انتخاب کنید
3. پیام خود را بنویسید
4. (اختیاری) یک selector CSS برای هایلایت اضافه کنید
5. **ارسال پیام** را بزنید

پیام بدون نیاز به رفرش در چت کاربر ظاهر می‌شود.

## جدول دیتابیس

### wp_homa_admin_interventions
```sql
CREATE TABLE wp_homa_admin_interventions (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    session_id varchar(255) NOT NULL,
    user_id bigint(20) DEFAULT NULL,
    admin_id bigint(20) NOT NULL,
    message text NOT NULL,
    visual_commands longtext DEFAULT NULL,
    status varchar(20) DEFAULT 'pending',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    delivered_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY session_id (session_id),
    KEY status (status)
);
```

## Event Bus Integration

### Events Emitted
- `visual:action` - اجرای یک دستور بصری
- `visual:action_completed` - تکمیل یک دستور بصری
- `explore:recommendations_loaded` - بارگذاری پیشنهادات
- `explore:card_clicked` - کلیک روی کارت پیشنهادی
- `intervention:received` - دریافت پیام زنده از ادمین

### Events Listened
- `ai:command` - دستورات از AI
- `vault:interests_updated` - به‌روزرسانی علایق کاربر

## تست

### 1. تست هدایت بصری
```javascript
// در Console
window.Homa.emit('visual:action', {
    command: 'HIGHLIGHT',
    target_selector: 'body > div:first-child',
    duration: 5000
});
```

باید اولین div صفحه با افکت Glow هایلایت شود.

### 2. تست Explore Widget
1. سایدبار هما را باز کنید
2. اگر پیام‌ها کم باشند، ویجت اکسپلور باید نمایش داده شود
3. کلیک روی کارت‌های پیشنهادی باید به صفحه مربوطه منتقل شود

### 3. تست Admin Intervention
1. از یک مرورگر، وارد سایت شوید (به عنوان کاربر)
2. از مرورگر دیگر، به پنل ادمین بروید
3. در **مداخله زنده**، جلسه کاربر را انتخاب کنید
4. یک پیام ارسال کنید
5. پیام باید در چت کاربر (بدون رفرش) ظاهر شود

## Performance

### Optimizations
- **Lazy Loading**: ویجت اکسپلور فقط در صورت نیاز بارگذاری می‌شود
- **Throttling**: Polling هر 5 ثانیه اتفاق می‌افتد (قابل تنظیم)
- **CSS Containment**: افکت‌های بصری از `will-change` استفاده می‌کنند
- **Cleanup**: تمام افکت‌ها پس از Duration به صورت خودکار پاکسازی می‌شوند

### Mobile Considerations
- انیمیشن‌های سبک‌تر برای موبایل
- تولتیپ‌های کوچک‌تر
- پشتیبانی از `prefers-reduced-motion`

## Security

### Nonce Verification
همه REST API endpoints از nonce استفاده می‌کنند.

### Capability Checks
- Admin Intervention: نیاز به `manage_options`
- Visual Actions: برای همه کاربران در دسترس است
- History: فقط برای ادمین‌ها

### XSS Prevention
- تمام output‌ها از `esc_html()` عبور می‌کنند
- Visual commands از whitelist استفاده می‌کنند

## Browser Compatibility
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Accessibility
- تمام تولتیپ‌ها `aria-label` دارند
- پشتیبانی از `prefers-reduced-motion`
- پشتیبانی از `prefers-contrast: high`
- کیبورد ناوبری

## Known Limitations

1. **Z-Index Conflicts**: در برخی تم‌ها ممکن است افکت‌ها پشت منو قرار بگیرند
   - راه‌حل: استفاده از `!important` در CSS

2. **Long Polling**: به جای WebSocket از Long Polling استفاده می‌شود
   - دلیل: سادگی پیاده‌سازی و سازگاری بیشتر

3. **Mobile Performance**: انیمیشن‌های سنگین ممکن است در موبایل‌های قدیمی کند باشند
   - راه‌حل: استفاده از `prefers-reduced-motion`

## Future Enhancements
- [ ] WebSocket به جای Long Polling
- [ ] Voice Commands برای accessibility بیشتر
- [ ] Gesture Support برای موبایل
- [ ] A/B Testing برای افکت‌های مختلف
- [ ] Analytics برای tracking تعاملات

## Support
- GitHub Issues: https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh/issues
- Documentation: Repository README

## Credits
- Developed by: GitHub Copilot
- Designed for: Homaye Tabesh Plugin
- Version: 1.0.0 (PR10)
- Date: December 26, 2024
