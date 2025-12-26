# PR17 Quickstart Guide

## 🚀 شروع سریع با PR17

این راهنما به شما کمک می‌کند تا در ۵ دقیقه با قابلیت‌های PR17 آشنا شوید.

---

## 1️⃣ استفاده از Authority Manager

### ثبت اصلاحیه دستی (Manual Override)

```php
$authority = HT_Core::instance()->authority_manager;

// قیمت محصول را دستی تنظیم کنید
$authority->set_manual_override(
    'product_price_101',
    120.00,
    'تخفیف ویژه - قیمت تصحیح شده'
);

// دریافت قیمت نهایی (با بالاترین اولویت)
$final_price = $authority->get_final_fact('product_price_101');
echo "قیمت نهایی: {$final_price} تومان"; // 120.00
```

### مشاهده همه اصلاحیه‌ها

```php
$overrides = $authority->get_all_overrides(true); // فقط فعال‌ها

foreach ($overrides as $override) {
    echo "{$override['key']}: {$override['value']}\n";
    echo "دلیل: {$override['reason']}\n";
}
```

---

## 2️⃣ اجرای عملیات چندگانه با Orchestrator

### مثال ساده: ثبت سفارش با OTP

```php
$orchestrator = HT_Core::instance()->action_orchestrator;

$actions = [
    [
        'type' => 'verify_otp',
        'params' => [
            'phone' => '09123456789',
            'code' => '1234',
        ],
    ],
    [
        'type' => 'create_order',
        'params' => [
            'product_id' => 101,
            'quantity' => 1,
        ],
    ],
    [
        'type' => 'send_sms',
        'params' => [
            'template' => 'order_confirmed',
        ],
    ],
];

$result = $orchestrator->execute_actions($actions);

if ($result['success']) {
    echo "✅ " . $result['message'];
} else {
    echo "❌ خطا: " . $result['error'];
    echo "\nRollback انجام شد: " . ($result['rollback_performed'] ? 'بله' : 'خیر');
}
```

---

## 3️⃣ ثبت بازخورد کاربر

### از طریق PHP

```php
$feedback = HT_Core::instance()->feedback_system;

$result = $feedback->submit_feedback([
    'rating' => 'dislike',
    'response_text' => 'قیمت محصول 100 تومان است',
    'user_prompt' => 'قیمت چقدر است؟',
    'error_details' => 'قیمت باید 120 تومان باشد',
    'conversation_id' => 'conv_' . time(),
    'facts_used' => ['product_price_101' => 100],
]);

if ($result['success']) {
    echo "بازخورد ثبت شد";
}
```

### از طریق JavaScript (REST API)

```javascript
fetch('/wp-json/homaye-tabesh/v1/feedback', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': homaReactData.nonce,
    },
    body: JSON.stringify({
        rating: 'dislike',
        response_text: 'پاسخ هما',
        user_prompt: 'سوال کاربر',
        error_details: 'توضیح خطا',
        conversation_id: 'conv_123',
        facts_used: {},
        context_data: {},
    }),
})
.then(res => res.json())
.then(data => {
    if (data.success) {
        console.log('Feedback submitted!');
    }
});
```

---

## 4️⃣ افزودن FeedbackButtons به React

### در کامپوننت چت خود:

```jsx
import FeedbackButtons from './components/FeedbackButtons';

function ChatMessage({ message }) {
    return (
        <div className="message">
            <p>{message.text}</p>
            
            {/* Add feedback buttons for Homa responses */}
            {message.from === 'homa' && (
                <FeedbackButtons
                    conversationId={message.id}
                    responseText={message.text}
                    userPrompt={message.userPrompt}
                    factsUsed={message.factsUsed || {}}
                    contextData={message.context || {}}
                    onFeedbackSubmitted={(rating) => {
                        console.log('User rated:', rating);
                    }}
                />
            )}
        </div>
    );
}
```

---

## 5️⃣ مشاهده Review Queue در پنل ادمین

### افزودن صفحه در منوی ادمین:

```php
// In HT_Admin or custom file
add_action('admin_menu', function() {
    add_menu_page(
        'صف بازخوردها',
        'بازخوردها',
        'manage_options',
        'homa-feedback-queue',
        'render_feedback_queue_page',
        'dashicons-feedback',
        30
    );
});

function render_feedback_queue_page() {
    echo '<div id="homa-feedback-queue-root"></div>';
    
    // Enqueue React component
    wp_enqueue_script(
        'homa-feedback-queue',
        HT_PLUGIN_URL . 'assets/build/feedbackQueue.js',
        ['wp-element'],
        HT_VERSION,
        true
    );
}
```

### یا استفاده از REST API مستقیم:

```javascript
// Get pending feedback
fetch('/wp-json/homaye-tabesh/v1/feedback/queue?status=pending', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce,
    },
})
.then(res => res.json())
.then(data => {
    console.log('Pending feedback:', data.items);
    console.log('Total:', data.total);
});

// Update feedback status
fetch('/wp-json/homaye-tabesh/v1/feedback/123/status', {
    method: 'PUT',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
    },
    body: JSON.stringify({
        status: 'resolved',
        admin_notes: 'قیمت تصحیح شد',
    }),
})
.then(res => res.json())
.then(data => {
    console.log('Updated:', data.success);
});
```

---

## 6️⃣ یکپارچه‌سازی با Gemini

هما به صورت خودکار از Authority Manager و Orchestrator استفاده می‌کند:

```php
$gemini = HT_Core::instance()->brain;

// Context is automatically enhanced with authority-checked facts
$response = $gemini->generate_content(
    'قیمت این محصول چقدر است؟',
    [
        'products' => [['id' => 101, 'name' => 'محصول A']],
    ]
);

// If response includes actions, they're automatically executed
if (isset($response['actions'])) {
    // Orchestrator has already executed them
    echo $response['response']; // Final result
}
```

---

## 🎯 سناریوهای کاربردی

### سناریو 1: تصحیح قیمت اشتباه

```php
// 1. مدیر متوجه قیمت اشتباه می‌شود
$authority->set_manual_override(
    'product_price_101',
    120.00,
    'قیمت تصحیح شده از 100 به 120'
);

// 2. از این به بعد هما قیمت صحیح را می‌گوید
$price = $authority->get_final_fact('product_price_101');
// Returns: 120.00
```

### سناریو 2: سفارش با تایید موبایل

```php
// کاربر می‌گوید: "سفارش من را با موبایلم ثبت کن"

// هما Actions تولید می‌کند:
$actions = [
    ['type' => 'verify_otp', 'params' => [...]],
    ['type' => 'create_order', 'params' => [...]],
    ['type' => 'send_sms', 'params' => [...]],
];

// Orchestrator به صورت خودکار اجرا می‌کند
$result = $orchestrator->execute_actions($actions);
// Result: "شماره شما تایید شد و سفارش ثبت گردید"
```

### سناریو 3: یادگیری از خطا

```php
// 1. کاربر دیسلایک می‌زند
$feedback->submit_feedback([
    'rating' => 'dislike',
    'error_details' => 'قیمت اشتباه است',
    ...
]);

// 2. مدیر در Review Queue می‌بیند
$queue = $feedback->get_review_queue(['status' => 'pending']);

// 3. مدیر قیمت را تصحیح می‌کند
$authority->set_manual_override('product_price_101', 120.00);

// 4. وضعیت feedback را resolved می‌کند
$feedback->update_feedback_status($feedback_id, 'resolved');
```

---

## 📊 مشاهده آمار

```php
// آمار کلی بازخوردها
$stats = $feedback->get_statistics();

echo "کل بازخوردها: {$stats['total']}\n";
echo "لایک: {$stats['likes']}\n";
echo "دیسلایک: {$stats['dislikes']}\n";
echo "میزان رضایت: {$stats['satisfaction_rate']}%\n";
```

---

## 🔧 تنظیمات پیشرفته

### تغییر لیست عملیات پشتیبانی شده

```php
// In HT_Action_Orchestrator
private const SUPPORTED_ACTIONS = [
    'verify_otp',
    'create_order',
    'add_to_cart',
    'send_sms',
    'custom_action', // Add new action
];

// Implement handler
private function action_custom_action($params) {
    // Your logic
    return ['success' => true, 'message' => '...'];
}
```

### افزودن Rollback برای اکشن سفارشی

```php
private function perform_rollback(array $result): void
{
    $rollback_data = $result['rollback_data'] ?? [];

    // Add your rollback logic
    if (isset($rollback_data['custom_id'])) {
        // Undo custom action
    }
}
```

---

## ❓ سوالات متداول

**Q: آیا می‌توانم اولویت سطوح را تغییر دهم؟**
A: خیر، اولویت ثابت است (1 > 2 > 3 > 4) و به دلایل امنیتی قابل تغییر نیست.

**Q: Rollback چگونه کار می‌کند؟**
A: در صورت خطا در هر مرحله، تمام اکشن‌های قبلی به ترتیب معکوس برگشت می‌خورند.

**Q: آیا کاربران مهمان می‌توانند بازخورد ثبت کنند؟**
A: بله، ولی فقط اگر امتیاز امنیتی آن‌ها بالاتر از 50 باشد (PR16).

**Q: چند نوع وضعیت برای بازخورد وجود دارد؟**
A: چهار نوع: `pending`, `reviewed`, `resolved`, `dismissed`

---

## 📚 منابع بیشتر

- [PR17-README.md](./PR17-README.md) - مستندات کامل
- [PR17-IMPLEMENTATION.md](./PR17-IMPLEMENTATION.md) - جزئیات پیاده‌سازی
- [examples/pr17-usage-examples.php](./examples/pr17-usage-examples.php) - مثال‌های عملی

---

**موفق باشید!** 🎉
