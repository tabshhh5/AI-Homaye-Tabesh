# PR17: Core Orchestrator Upgrade - Authority Hierarchy & Smart Feedback

## 🎯 معرفی

**ارتقای هسته مرکزی هما** به یک واحد هماهنگ‌کننده (Orchestrator) با قابلیت‌های پیشرفته حل تضاد دانش، اجرای عملیات چندگانه و یادگیری از بازخورد کاربران.

این PR سه چالش کلیدی را حل می‌کند:
1. **Conflict Resolution** - تعیین اولویت منابع اطلاعاتی در زمان تضاد
2. **Multi-Step Operations** - اجرای زنجیره‌ای دستورات (OTP + Order + SMS)
3. **Feedback Loop** - یادگیری از اشتباهات و اصلاح دانش

---

## 🌟 ویژگی‌های کلیدی

### 1. سیستم سلسله‌مراتب اعتبار (Knowledge Authority System)

چهار سطح اولویت برای حل تضاد اطلاعات:

```
Level 1 (Highest): Manual Admin Overrides
    ↓
Level 2: Panel Settings
    ↓
Level 3: Live Data (WooCommerce, Tabesh)
    ↓
Level 4 (Lowest): General Knowledge (Gemini)
```

**مثال عملی:**
- قیمت در WooCommerce: 100 تومان (Level 3)
- قیمت در اصلاحیه مدیر: 120 تومان (Level 1)
- **نتیجه:** هما 120 تومان را اعلام می‌کند ✅

### 2. موتور هماهنگ‌سازی عملیات (Action Orchestrator)

اجرای زنجیره‌ای دستورات با قابلیت Rollback خودکار:

```json
{
  "response": "شماره شما تایید شد و سفارش ثبت گردید",
  "actions": [
    {"type": "verify_otp", "params": {"phone": "09123456789", "code": "1234"}},
    {"type": "create_order", "params": {"product_id": 101}},
    {"type": "send_sms", "params": {"template": "order_confirmed"}}
  ]
}
```

**عملیات پشتیبانی شده:**
- `verify_otp` - تایید کد یکبار مصرف
- `create_order` - ثبت سفارش WooCommerce
- `add_to_cart` - افزودن به سبد خرید
- `send_sms` - ارسال پیامک
- `save_lead` - ذخیره سرنخ
- `update_user` - بروزرسانی کاربر
- `track_event` - ثبت رویداد
- `send_notification` - ارسال اعلان

### 3. سیستم بازخورد هوشمند (Smart Feedback Loop)

**مسیر بازخورد مثبت (Like):**
```
کاربر کلیک می‌کند 👍
    ↓
ذخیره در دیتابیس
    ↓
افزایش امتیاز کیفیت پاسخ
```

**مسیر بازخورد منفی (Dislike):**
```
کاربر کلیک می‌کند 👎
    ↓
فرم توضیح خطا نمایش داده می‌شود
    ↓
ذخیره در Review Queue
    ↓
ارسال نوتیفیکیشن به مدیر
    ↓
مدیر بررسی و اصلاح می‌کند
```

---

## 📦 کامپوننت‌ها

### HT_Authority_Manager

مدیریت اولویت منابع دانش و حل تضاد.

```php
$authority_manager = HT_Core::instance()->authority_manager;

// Set manual override (Level 1)
$authority_manager->set_manual_override(
    'product_price_101',
    120.00,
    'تصحیح قیمت توسط مدیر'
);

// Get final fact (با بالاترین اعتبار)
$price = $authority_manager->get_final_fact('product_price_101');
// Returns: 120.00
```

**متدهای کلیدی:**
- `get_final_fact($key, $context)` - دریافت فکت با بالاترین اعتبار
- `set_manual_override($key, $value, $reason)` - ثبت اصلاحیه دستی
- `remove_manual_override($key)` - حذف اصلاحیه
- `get_all_overrides()` - لیست همه اصلاحیه‌ها

### HT_Action_Orchestrator

اجرای زنجیره‌ای عملیات با Rollback خودکار.

```php
$orchestrator = HT_Core::instance()->action_orchestrator;

$actions = [
    ['type' => 'verify_otp', 'params' => [...]],
    ['type' => 'create_order', 'params' => [...]],
    ['type' => 'send_sms', 'params' => [...]],
];

$result = $orchestrator->execute_actions($actions);

if ($result['success']) {
    echo $result['message'];
} else {
    echo "خطا در مرحله: " . $result['failed_at'];
    echo "Rollback انجام شد: " . $result['rollback_performed'];
}
```

**ویژگی‌های کلیدی:**
- اجرای ترتیبی (Sequential Execution)
- Rollback خودکار در صورت خطا
- Context Sharing بین اکشن‌ها
- Execution History برای دیباگ

### HT_Feedback_System

سیستم ثبت و مدیریت بازخورد کاربران.

```php
$feedback_system = HT_Core::instance()->feedback_system;

// Submit feedback
$result = $feedback_system->submit_feedback([
    'rating' => 'dislike',
    'response_text' => 'پاسخ نادرست هما',
    'error_details' => 'قیمت اشتباه است',
    'conversation_id' => 'conv_123',
    'facts_used' => ['product_price_101' => 100],
]);

// Get review queue (Admin only)
$queue = $feedback_system->get_review_queue([
    'status' => 'pending',
    'rating' => 'dislike',
], 1, 20);

// Get statistics
$stats = $feedback_system->get_statistics();
// Returns: total, likes, dislikes, satisfaction_rate
```

### HT_Feedback_REST_API

API برای تعامل با سیستم بازخورد.

**Endpoints:**

```
POST /wp-json/homaye-tabesh/v1/feedback
    - ثبت بازخورد جدید
    - Public (با محدودیت امنیتی)

GET /wp-json/homaye-tabesh/v1/feedback/queue
    - دریافت صف بررسی
    - Admin Only

GET /wp-json/homaye-tabesh/v1/feedback/{id}
    - دریافت جزئیات یک بازخورد
    - Admin Only

PUT /wp-json/homaye-tabesh/v1/feedback/{id}/status
    - بروزرسانی وضعیت بازخورد
    - Admin Only

GET /wp-json/homaye-tabesh/v1/feedback/statistics
    - دریافت آمار بازخوردها
    - Admin Only
```

---

## 🎨 کامپوننت‌های React

### FeedbackButtons

دکمه‌های لایک/دیسلایک برای هر پاسخ هما.

```jsx
import FeedbackButtons from './components/FeedbackButtons';

<FeedbackButtons
    conversationId="conv_123"
    responseText="قیمت این محصول 120 تومان است"
    userPrompt="قیمت چقدر است؟"
    factsUsed={{'product_price_101': 120}}
    contextData={{product_id: 101}}
    onFeedbackSubmitted={(rating, result) => {
        console.log('Feedback submitted:', rating);
    }}
/>
```

### FeedbackReviewQueue

صفحه مدیریت بازخوردها در پنل ادمین.

```jsx
import FeedbackReviewQueue from './components/FeedbackReviewQueue';

<FeedbackReviewQueue />
```

**قابلیت‌ها:**
- فیلتر بر اساس وضعیت و نوع
- مشاهده جزئیات کامل هر بازخورد
- بروزرسانی وضعیت (Reviewed, Resolved, Dismissed)
- نمایش آمار کلی

---

## 🔄 جریان کامل (Complete Flow)

```
1. کاربر: "سفارش من را با موبایلم ثبت کن"
    ↓
2. Authority Manager: بررسی قیمت محصول
   - Level 1: Manual Override? → 120 تومان ✓
    ↓
3. Gemini: تولید پاسخ + Actions
   {
     "response": "...",
     "actions": [verify_otp, create_order, send_sms]
   }
    ↓
4. Action Orchestrator: اجرای زنجیره‌ای
   - verify_otp ✓
   - create_order ✓
   - send_sms ✓
    ↓
5. نمایش پاسخ نهایی + دکمه‌های بازخورد
    ↓
6. کاربر: کلیک بر دیسلایک 👎
    ↓
7. Feedback System: ذخیره در Review Queue
    ↓
8. مدیر: بررسی و اصلاح
```

---

## 🛡️ امنیت و محدودیت‌ها

### محدودیت ثبت بازخورد

تنها کاربران با شرایط زیر می‌توانند بازخورد ثبت کنند:

1. **کاربران لاگین شده:** همیشه مجاز
2. **کاربران مهمان:** با امتیاز امنیتی ≥ 50 (PR16)

```php
// Check eligibility
if (is_user_logged_in()) {
    return true; // Always allowed
}

$security_score = $behavior_tracker->get_security_score();
if ($security_score >= 50) {
    return true; // Guest allowed
}

return false; // Not eligible
```

### Rollback در Orchestrator

در صورت خطا در هر مرحله، اقدامات قبلی برگشت می‌خورند:

```php
// Example: If SMS fails, order will be deleted
[
    verify_otp ✓,
    create_order ✓ → Order #123 created,
    send_sms ✗ → SMS failed
]
// Result: Order #123 deleted (Rollback)
```

---

## 📊 جداول دیتابیس

### homa_authority_overrides

```sql
CREATE TABLE homa_authority_overrides (
    id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    override_key varchar(255) NOT NULL,
    override_value text NOT NULL,
    value_type varchar(20) DEFAULT 'string',
    reason text,
    admin_user_id bigint(20),
    is_active tinyint(1) DEFAULT 1,
    created_at datetime,
    updated_at datetime
);
```

### homa_feedback

```sql
CREATE TABLE homa_feedback (
    id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    user_id bigint(20),
    user_identifier varchar(255),
    conversation_id varchar(100),
    rating varchar(20) NOT NULL, -- 'like' or 'dislike'
    response_text text NOT NULL,
    user_prompt text,
    error_details text,
    facts_used json,
    context_data json,
    status varchar(20) DEFAULT 'pending',
    admin_notes text,
    reviewer_id bigint(20),
    reviewed_at datetime,
    created_at datetime
);
```

---

## 🚀 نصب و راه‌اندازی

### 1. فعال‌سازی خودکار

همه کامپوننت‌های PR17 به صورت خودکار در `HT_Core::init_services()` بارگذاری می‌شوند:

```php
// Auto-initialized in HT_Core
$this->authority_manager = new HT_Authority_Manager();
$this->action_orchestrator = new HT_Action_Orchestrator($this);
$this->feedback_system = new HT_Feedback_System();
$this->feedback_api = new HT_Feedback_REST_API();
```

### 2. ساخت جداول دیتابیس

جداول به صورت خودکار در Activation ساخته می‌شوند:

```php
// In HT_Activator::activate()
$authority_manager->create_table();
$feedback_system->create_table();
```

### 3. افزودن FeedbackButtons به چت

```jsx
// In HomaSidebar.jsx or MessageList.jsx
import FeedbackButtons from './FeedbackButtons';

// Add after each Homa response
<FeedbackButtons
    conversationId={message.id}
    responseText={message.text}
    userPrompt={message.prompt}
    factsUsed={message.facts}
/>
```

---

## 📖 مثال‌های استفاده

مشاهده فایل: `examples/pr17-usage-examples.php`

```bash
php examples/pr17-usage-examples.php
```

---

## 🔧 تنظیمات و سفارشی‌سازی

### تغییر حداقل امتیاز امنیتی برای بازخورد

```php
// In HT_Feedback_System
private const MIN_SECURITY_SCORE = 50; // Change as needed
```

### افزودن نوع اکشن جدید

```php
// In HT_Action_Orchestrator
private const SUPPORTED_ACTIONS = [
    'verify_otp',
    'create_order',
    // ... existing actions
    'custom_action', // Add your action
];

// Implement handler
private function action_custom_action(array $params): array {
    // Your logic here
    return ['success' => true, 'message' => '...'];
}
```

### افزودن فکت جدید برای Authority Check

```php
// In HT_Gemini_Client::enhance_context_with_authority()
$fact_keys_to_check = [
    'shipping_cost',
    'min_order_value',
    'custom_fact_key', // Add your fact
];
```

---

## 📈 مانیتورینگ و آمار

### مشاهده آمار بازخوردها

```php
$stats = $feedback_system->get_statistics();
/*
Returns:
[
    'total' => 150,
    'likes' => 120,
    'dislikes' => 30,
    'satisfaction_rate' => 80.00,
    'status_breakdown' => [
        'pending' => 10,
        'reviewed' => 5,
        'resolved' => 12,
        'dismissed' => 3
    ]
]
*/
```

### مشاهده تاریخچه اجرای Actions

```php
$orchestrator = HT_Core::instance()->action_orchestrator;
$result = $orchestrator->execute_actions($actions);

$history = $orchestrator->get_execution_history();
/*
Returns:
[
    [
        'action' => ['type' => 'verify_otp', ...],
        'success' => true,
        'message' => '...',
        'timestamp' => '2024-01-01 12:00:00'
    ],
    ...
]
*/
```

---

## ⚠️ نکات مهم

1. **Race Conditions:** در عملیات زنجیره‌ای، شکست یک مرحله Rollback کامل انجام می‌دهد
2. **Security Score:** کاربران با امتیاز پایین نمی‌توانند بازخورد ثبت کنند
3. **Manual Overrides:** همیشه بالاترین اولویت را دارند
4. **Feedback Spam:** فیلترینگ خودکار بر اساس PR16 Security System

---

## 🤝 مشارکت و توسعه

برای توسعه و اضافه کردن قابلیت‌های جدید:

1. Fork کردن repository
2. ایجاد branch جدید
3. توسعه و test
4. ارسال Pull Request

---

## 📝 لاگ تغییرات

### v1.0.0 (PR17)
- ✅ Authority Manager با 4 سطح اولویت
- ✅ Action Orchestrator با قابلیت Rollback
- ✅ Feedback System با Review Queue
- ✅ REST API برای بازخورد
- ✅ کامپوننت‌های React برای UI
- ✅ یکپارچه‌سازی با Gemini Client
- ✅ مستندات کامل و مثال‌های عملی

---

## 📞 پشتیبانی

در صورت بروز مشکل یا سوال، از طریق GitHub Issues اطلاع دهید.

---

**تیم توسعه همای تابش** | Powered by Gemini 2.0 Flash
