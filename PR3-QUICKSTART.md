# راهنمای سریع PR3: موتور استنتاج هما

## نصب و راه‌اندازی

### پیش‌نیازها
- WordPress >= 6.0
- PHP >= 8.2
- Gemini API Key
- افزونه همای تابش نصب و فعال شده باشد

### مراحل راه‌اندازی

#### 1. تنظیم API Key

در پنل مدیریت وردپرس:
```
تنظیمات > همای تابش > API Key
```

یا با کد PHP:
```php
update_option('ht_gemini_api_key', 'YOUR_API_KEY_HERE');
```

#### 2. بررسی سلامت سیستم

```bash
curl http://your-site.com/wp-json/homaye/v1/ai/health
```

انتظار دارید ببینید:
```json
{
  "status": "ok",
  "api_configured": true,
  "components": {
    "inference_engine": "operational",
    "knowledge_base": "operational",
    "action_parser": "operational"
  }
}
```

## استفاده ساده

### در فرانتئند (JavaScript)

```javascript
// ارسال سوال به هما
async function askHoma(question) {
    const response = await fetch('/wp-json/homaye/v1/ai/query', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: 'guest_' + Date.now(),
            message: question
        })
    });
    
    const data = await response.json();
    console.log('Homa:', data.message);
    
    // اجرای اکشن
    if (data.action && window.HomaUIExecutor) {
        window.HomaUIExecutor.executeAction(data.action);
    }
}

// استفاده
askHoma('چگونه می‌توانم کتاب چاپ کنم؟');
```

### در Backend (PHP)

```php
$inference_engine = \HomayeTabesh\HT_Core::instance()->inference_engine;

$result = $inference_engine->generate_decision([
    'user_identifier' => 'user_123',
    'message' => 'می‌خواهم کتاب چاپ کنم',
]);

echo $result['message'];
```

### با Shortcode

در هر صفحه یا پست:
```
[homa_chat placeholder="سوال خود را بپرسید..."]
```

## مثال‌های کاربردی

### 1. چت‌بات ساده

```html
<button id="ask-homa">کمک می‌خواهم</button>

<script>
document.getElementById('ask-homa').addEventListener('click', async function() {
    const response = await fetch('/wp-json/homaye/v1/ai/query', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: 'guest_' + Date.now(),
            message: 'در این صفحه کمک نیاز دارم',
            context: {
                page: window.location.pathname
            }
        })
    });
    
    const data = await response.json();
    alert('هما: ' + data.message);
});
</script>
```

### 2. پیشنهاد محصول

```php
function show_product_suggestion() {
    if (!is_product()) return;
    
    $engine = \HomayeTabesh\HT_Core::instance()->inference_engine;
    $result = $engine->get_context_suggestion('guest_' . time(), []);
    
    if ($result['success']) {
        echo '<div class="homa-suggestion">' . $result['message'] . '</div>';
    }
}
add_action('woocommerce_after_single_product_summary', 'show_product_suggestion');
```

### 3. نمایش اکشن UI

```javascript
// مثال: اجرای اکشن‌های مختلف

// هایلایت کردن المان
window.HomaUIExecutor.executeAction({
    type: 'highlight_element',
    target: '.pricing-table'
});

// نمایش tooltip
window.HomaUIExecutor.executeAction({
    type: 'show_tooltip',
    target: '.calculator',
    message: 'از این ابزار برای محاسبه قیمت استفاده کنید'
});

// باز کردن مدال
window.HomaUIExecutor.executeAction({
    type: 'open_modal',
    data: {
        title: 'پیشنهاد ویژه',
        content: '<p>تخفیف 20% برای سفارش‌های بالای 100 نسخه</p>'
    }
});

// اسکرول به المان
window.HomaUIExecutor.executeAction({
    type: 'scroll_to',
    target: '#pricing-section'
});
```

## دیباگ و عیب‌یابی

### فعال کردن لاگ‌ها

در `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

لاگ‌های هما در `wp-content/debug.log` نمایش داده می‌شوند.

### بررسی پاسخ API

```javascript
// فعال کردن حالت debug
fetch('/wp-json/homaye/v1/ai/query', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        user_id: 'test_user',
        message: 'تست'
    })
})
.then(res => res.json())
.then(data => {
    console.log('Full Response:', data);
    if (data.debug) {
        console.log('Thought Process:', data.debug.thought);
    }
});
```

### مشکلات رایج

**1. API Key نامعتبر است**
```
خطا: API key not configured
راه‌حل: API Key خود را در تنظیمات وارد کنید
```

**2. پاسخ خالی است**
```
خطا: Empty response from API
راه‌حل: اتصال اینترنت و API Key را بررسی کنید
```

**3. اکشن اجرا نمی‌شود**
```
خطا: window.HomaUIExecutor is undefined
راه‌حل: مطمئن شوید که ui-executor.js بارگذاری شده است
```

## بهینه‌سازی

### کاهش هزینه API

```php
// استفاده از cache برای سوالات مشابه
function get_cached_response($user_id, $message) {
    $cache_key = 'homa_' . md5($message);
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $engine = \HomayeTabesh\HT_Core::instance()->inference_engine;
    $result = $engine->generate_decision([
        'user_identifier' => $user_id,
        'message' => $message,
    ]);
    
    // Cache برای 1 ساعت
    set_transient($cache_key, $result, HOUR_IN_SECONDS);
    
    return $result;
}
```

### محدود کردن درخواست‌ها

```php
// Rate limiting برای جلوگیری از abuse
function check_rate_limit($user_id) {
    $key = 'homa_rate_' . $user_id;
    $count = get_transient($key);
    
    if ($count !== false && $count >= 10) {
        return false; // بیش از 10 درخواست در ساعت
    }
    
    set_transient($key, ($count !== false ? $count + 1 : 1), HOUR_IN_SECONDS);
    return true;
}
```

## تست

### تست دستی با cURL

```bash
# تست ساده
curl -X POST http://localhost/wp-json/homaye/v1/ai/query \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "test_user",
    "message": "سلام"
  }'

# تست با context
curl -X POST http://localhost/wp-json/homaye/v1/ai/query \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "test_user",
    "message": "قیمت چاپ کتاب چقدر است؟",
    "context": {
      "page": "/products/book-printing"
    }
  }'

# تست پیشنهاد
curl -X POST http://localhost/wp-json/homaye/v1/ai/suggestion \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "test_user"
  }'

# تست health
curl http://localhost/wp-json/homaye/v1/ai/health
```

### تست از Console مرورگر

```javascript
// تست سریع از Console
(async function() {
    const response = await fetch('/wp-json/homaye/v1/ai/query', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: 'console_test',
            message: 'این یک تست است'
        })
    });
    const data = await response.json();
    console.log(data);
})();
```

## پشتیبانی

برای مشکلات و سوالات:
- GitHub Issues: https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh/issues
- مستندات کامل: `PR3-IMPLEMENTATION.md`
- مثال‌های کد: `examples/pr3-usage-examples.php`

## نکات امنیتی

1. **هرگز API Key را در کد frontend قرار ندهید**
2. **همیشه از HTTPS استفاده کنید**
3. **Rate limiting را فعال کنید**
4. **ورودی‌های کاربر را Sanitize کنید**
5. **از WordPress Nonce برای درخواست‌های حساس استفاده کنید**

## به‌روزرسانی

برای به‌روزرسانی به نسخه جدید:

```bash
cd wp-content/plugins/homaye-tabesh
git pull origin main
composer install --no-dev --optimize-autoloader
```

سپس در پنل مدیریت افزونه را غیرفعال و مجدداً فعال کنید.

---

**نسخه:** 1.0.0  
**آخرین به‌روزرسانی:** 2024-01-15  
**سازگار با:** WordPress 6.0+, PHP 8.2+
