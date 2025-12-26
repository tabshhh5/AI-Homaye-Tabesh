# PR17 Implementation Details

## 🏗️ معماری سیستم

### نمای کلی

```
┌─────────────────────────────────────────────────────────────┐
│                        HT_Core                               │
│  ┌───────────────────────────────────────────────────────┐  │
│  │              HT_Gemini_Client                         │  │
│  │  ┌────────────────────────────────────────────────┐  │  │
│  │  │  1. Enhance Context with Authority Manager    │  │  │
│  │  │  2. Generate Response + Actions                │  │  │
│  │  │  3. Execute Actions via Orchestrator           │  │  │
│  │  └────────────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────────────────┘  │
│                           ↓                                  │
│  ┌───────────────┬──────────────────┬──────────────────┐   │
│  │  Authority    │  Action          │  Feedback        │   │
│  │  Manager      │  Orchestrator    │  System          │   │
│  └───────────────┴──────────────────┴──────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 📦 Component 1: HT_Authority_Manager

### مسئولیت‌ها

1. مدیریت سلسله‌مراتب اعتبار دانش
2. حل تضاد اطلاعات با اولویت‌بندی
3. ذخیره و مدیریت Manual Overrides

### ساختار داده

```php
class HT_Authority_Manager {
    // Authority levels (priority: 1 > 2 > 3 > 4)
    public const LEVEL_MANUAL_OVERRIDE = 1;
    public const LEVEL_PANEL_SETTINGS = 2;
    public const LEVEL_LIVE_DATA = 3;
    public const LEVEL_GENERAL_KNOWLEDGE = 4;
    
    private string $table_name; // homa_authority_overrides
    private ?HT_WooCommerce_Context $woo_context;
    private ?HT_Knowledge_Base $knowledge_base;
}
```

### منطق تصمیم‌گیری

```php
public function get_final_fact(string $key, array $context = []): mixed
{
    // Level 1: Manual Override
    if ($value = $this->get_manual_override($key)) {
        return $value; // ✓ Highest priority
    }
    
    // Level 2: Panel Settings
    if ($value = $this->get_panel_setting($key, $context)) {
        return $value;
    }
    
    // Level 3: Live Data
    if ($value = $this->get_live_data($key, $context)) {
        return $value;
    }
    
    // Level 4: General Knowledge
    if ($value = $this->get_general_knowledge($key, $context)) {
        return $value;
    }
    
    return null; // Not found
}
```

### فرمت کلیدها (Key Format)

```
product_price_{id}     → قیمت محصول
product_stock_{id}     → موجودی محصول
product_name_{id}      → نام محصول
order_status_{id}      → وضعیت سفارش
order_total_{id}       → مبلغ سفارش
user_name_{id}         → نام کاربر
shipping_cost          → هزینه ارسال
min_order_value        → حداقل مبلغ سفارش
```

### جدول دیتابیس

```sql
CREATE TABLE homa_authority_overrides (
    id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    override_key varchar(255) NOT NULL,      -- Key to override
    override_value text NOT NULL,            -- New value
    value_type varchar(20) DEFAULT 'string', -- string|integer|float|boolean|json
    reason text,                             -- Why this override?
    admin_user_id bigint(20),               -- Who made the change?
    is_active tinyint(1) DEFAULT 1,         -- Active or disabled?
    created_at datetime,
    updated_at datetime,
    
    KEY override_key (override_key),
    KEY is_active (is_active)
);
```

---

## 📦 Component 2: HT_Action_Orchestrator

### مسئولیت‌ها

1. اجرای زنجیره‌ای عملیات (Sequential Execution)
2. مدیریت Rollback در صورت خطا
3. اشتراک Context بین Actions
4. ثبت تاریخچه اجرا برای دیباگ

### ساختار داده

```php
class HT_Action_Orchestrator {
    private array $execution_history = [];
    private ?HT_Core $core;
    private array $context = [];
    
    private const SUPPORTED_ACTIONS = [
        'verify_otp',
        'create_order',
        'add_to_cart',
        'send_sms',
        'update_user',
        'save_lead',
        'track_event',
        'send_notification',
    ];
}
```

### فرآیند اجرا

```
┌─────────────────────────────────────────────────────────┐
│  execute_actions([$action1, $action2, $action3])       │
└─────────────────────────────────────────────────────────┘
                           ↓
        ┌──────────────────┴──────────────────┐
        ↓                                      ↓
┌──────────────────┐                  ┌──────────────────┐
│  Validate Each   │                  │  Execute Each    │
│  Action Schema   │  ──────→         │  Sequentially    │
└──────────────────┘                  └──────────────────┘
                                              ↓
                              ┌───────────────┴───────────────┐
                              ↓                               ↓
                      ┌──────────────┐              ┌──────────────┐
                      │  Success?    │              │  Failed?     │
                      │  Continue    │              │  Rollback!   │
                      └──────────────┘              └──────────────┘
```

### Rollback Strategy

```php
private function rollback_actions(array $results): void
{
    // Reverse order (LIFO)
    foreach (array_reverse($results) as $result) {
        if (!$result['success']) continue;
        
        $this->perform_rollback($result);
    }
}

private function perform_rollback(array $result): void
{
    $rollback_data = $result['rollback_data'] ?? [];
    
    // Rollback order creation
    if (isset($rollback_data['order_id'])) {
        $order = wc_get_order($rollback_data['order_id']);
        $order->delete(true); // Force delete
    }
    
    // Rollback cart addition
    if (isset($rollback_data['cart_item_key'])) {
        WC()->cart->remove_cart_item($rollback_data['cart_item_key']);
    }
}
```

### Action Schema

```typescript
interface Action {
    type: string;           // Action type
    params: {               // Action parameters
        [key: string]: any;
    };
}

interface ActionResult {
    success: boolean;       // Execution status
    message: string;        // Result message
    data?: object;          // Output data
    rollback_data?: object; // Data needed for rollback
}
```

### مثال عملی

```php
// Input
$actions = [
    [
        'type' => 'verify_otp',
        'params' => ['phone' => '09123456789', 'code' => '1234'],
    ],
    [
        'type' => 'create_order',
        'params' => ['product_id' => 101, 'quantity' => 1],
    ],
    [
        'type' => 'send_sms',
        'params' => ['template' => 'order_confirmed'],
    ],
];

// Execution Flow
Step 1: verify_otp ✓
    → context['phone_verified'] = true
    → context['phone_number'] = '09123456789'

Step 2: create_order ✓
    → context['order_id'] = 123
    → context['order_total'] = 120.00
    → rollback_data = ['order_id' => 123]

Step 3: send_sms ✓
    → Uses context['phone_number'] from Step 1
    → Uses context['order_id'] from Step 2

// Output
{
    "success": true,
    "message": "شماره تایید شد. سفارش ثبت گردید. پیامک ارسال شد.",
    "results": [...]
}
```

---

## 📦 Component 3: HT_Feedback_System

### مسئولیت‌ها

1. ثبت بازخورد کاربران (Like/Dislike)
2. مدیریت Review Queue برای مدیر
3. ارسال نوتیفیکیشن در صورت بازخورد منفی
4. محدودیت امنیتی بر اساس Security Score

### ساختار داده

```php
class HT_Feedback_System {
    private string $table_name; // homa_feedback
    private const MIN_SECURITY_SCORE = 50;
}
```

### فرآیند ثبت بازخورد

```
┌──────────────────────────────────────────────────────┐
│  User clicks 👍 or 👎                                │
└──────────────────────────────────────────────────────┘
                    ↓
        ┌───────────┴───────────┐
        ↓                       ↓
┌─────────────────┐     ┌─────────────────┐
│  Like (👍)      │     │  Dislike (👎)   │
│  ↓              │     │  ↓              │
│  Store in DB    │     │  Show Error Form│
│  Done ✓         │     │  ↓              │
└─────────────────┘     │  User explains  │
                        │  ↓              │
                        │  Store in DB    │
                        │  ↓              │
                        │  Notify Admin   │
                        │  ↓              │
                        │  Add to Queue   │
                        └─────────────────┘
```

### بررسی مجاز بودن کاربر

```php
private function check_user_eligibility(): array
{
    // Logged-in users: Always allowed
    if (get_current_user_id() > 0) {
        return ['eligible' => true];
    }
    
    // Guest users: Check security score
    if (class_exists('\HomayeTabesh\HT_User_Behavior_Tracker')) {
        $tracker = new HT_User_Behavior_Tracker();
        $score = $tracker->get_security_score();
        
        if ($score >= self::MIN_SECURITY_SCORE) {
            return ['eligible' => true];
        }
        
        return [
            'eligible' => false,
            'reason' => 'امتیاز امنیتی کافی نیست',
        ];
    }
    
    return ['eligible' => true];
}
```

### جدول دیتابیس

```sql
CREATE TABLE homa_feedback (
    id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    user_id bigint(20),                     -- Logged-in user ID
    user_identifier varchar(255),           -- User fingerprint for guests
    conversation_id varchar(100),           -- Conversation reference
    rating varchar(20) NOT NULL,            -- 'like' or 'dislike'
    response_text text NOT NULL,            -- Homa's response
    user_prompt text,                       -- User's original question
    error_details text,                     -- User's explanation (if dislike)
    facts_used json,                        -- Facts used in response
    context_data json,                      -- Additional context
    status varchar(20) DEFAULT 'pending',   -- pending|reviewed|resolved|dismissed
    admin_notes text,                       -- Admin's notes
    reviewer_id bigint(20),                 -- Admin who reviewed
    reviewed_at datetime,
    created_at datetime,
    
    KEY user_id (user_id),
    KEY rating (rating),
    KEY status (status),
    KEY created_at (created_at)
);
```

### وضعیت‌های بازخورد (Status Flow)

```
pending (جدید)
    ↓
    ├─→ reviewed (بررسی شده)
    │       ↓
    │       └─→ resolved (حل شده)
    │
    └─→ dismissed (رد شده)
```

---

## 🔗 Component 4: HT_Feedback_REST_API

### Endpoints

```
POST   /wp-json/homaye-tabesh/v1/feedback
       - Submit new feedback
       - Permission: Public (with security check)
       
GET    /wp-json/homaye-tabesh/v1/feedback/queue
       - Get review queue
       - Permission: Admin only
       
GET    /wp-json/homaye-tabesh/v1/feedback/{id}
       - Get single feedback details
       - Permission: Admin only
       
PUT    /wp-json/homaye-tabesh/v1/feedback/{id}/status
       - Update feedback status
       - Permission: Admin only
       
GET    /wp-json/homaye-tabesh/v1/feedback/statistics
       - Get feedback statistics
       - Permission: Admin only
```

---

## 🔄 یکپارچه‌سازی با Gemini

### 1. Context Enhancement

```php
// In HT_Gemini_Client::generate_content()
$context = $this->enhance_context_with_authority($context);
```

```php
private function enhance_context_with_authority(array $context): array
{
    $authority_manager = HT_Core::instance()->authority_manager;
    $checked_facts = [];
    
    // Check critical facts with authority manager
    foreach ($fact_keys as $key) {
        $value = $authority_manager->get_final_fact($key, $context);
        if ($value !== null) {
            $checked_facts[$key] = $value;
        }
    }
    
    $context['checked_facts'] = $checked_facts;
    return $context;
}
```

### 2. System Instruction Update

```php
$instruction .= "سیستم سلسله‌مراتب اعتبار دانش:\n";
$instruction .= "1. بالاترین اولویت: اصلاحات دستی مدیر\n";
$instruction .= "2. تنظیمات پنل مدیریت\n";
$instruction .= "3. داده‌های زنده WooCommerce\n";
$instruction .= "4. دانش عمومی شما\n\n";

$instruction .= "قابلیت اجرای عملیات چندگانه:\n";
$instruction .= "می‌توانید چندین عملیات زنجیره‌ای انجام دهید:\n";
$instruction .= "- verify_otp, create_order, send_sms, ...\n";
```

### 3. Action Execution

```php
// After getting response from Gemini
if (isset($parsed_response['actions'])) {
    $orchestrator = HT_Core::instance()->action_orchestrator;
    $result = $orchestrator->execute_actions(
        $parsed_response['actions'],
        $context
    );
    
    if ($result['success']) {
        $parsed_response['response'] = $result['message'];
    } else {
        $parsed_response['response'] = 'خطا: ' . $result['error'];
    }
}
```

---

## 🎨 React Components

### FeedbackButtons.jsx

```jsx
const FeedbackButtons = ({ 
    conversationId, 
    responseText,
    userPrompt,
    factsUsed,
    contextData,
    onFeedbackSubmitted 
}) => {
    const [feedbackGiven, setFeedbackGiven] = useState(null);
    const [showErrorForm, setShowErrorForm] = useState(false);
    
    const submitFeedback = async (rating, errorDetails) => {
        const response = await fetch('/wp-json/homaye-tabesh/v1/feedback', {
            method: 'POST',
            body: JSON.stringify({
                rating,
                response_text: responseText,
                user_prompt: userPrompt,
                error_details: errorDetails,
                conversation_id: conversationId,
                facts_used: factsUsed,
                context_data: contextData,
            }),
        });
        
        const result = await response.json();
        if (result.success) {
            setFeedbackGiven(rating);
        }
    };
    
    // ... render logic
};
```

### FeedbackReviewQueue.jsx

```jsx
const FeedbackReviewQueue = () => {
    const [feedbackItems, setFeedbackItems] = useState([]);
    const [statistics, setStatistics] = useState(null);
    
    useEffect(() => {
        loadFeedbackQueue();
        loadStatistics();
    }, [filters]);
    
    const loadFeedbackQueue = async () => {
        const response = await fetch(
            '/wp-json/homaye-tabesh/v1/feedback/queue?status=pending'
        );
        const data = await response.json();
        setFeedbackItems(data.items);
    };
    
    const updateStatus = async (id, status, notes) => {
        await fetch(`/wp-json/homaye-tabesh/v1/feedback/${id}/status`, {
            method: 'PUT',
            body: JSON.stringify({ status, admin_notes: notes }),
        });
        loadFeedbackQueue();
    };
    
    // ... render logic
};
```

---

## 🔒 امنیت

### 1. محدودیت ثبت بازخورد

```php
// Only users with security score >= 50 can submit feedback
if (!is_user_logged_in()) {
    $score = $behavior_tracker->get_security_score();
    if ($score < 50) {
        return ['success' => false, 'message' => 'امتیاز کافی نیست'];
    }
}
```

### 2. REST API Permissions

```php
// Admin-only endpoints
public function admin_permission_check(): bool {
    return current_user_can('manage_options');
}
```

### 3. Sanitization

```php
$feedback_data = [
    'rating' => sanitize_text_field($request->get_param('rating')),
    'response_text' => sanitize_textarea_field($request->get_param('response_text')),
    'error_details' => sanitize_textarea_field($request->get_param('error_details')),
];
```

---

## 📈 Performance & Optimization

### 1. Authority Manager Caching

```php
// Cache checked facts in context to avoid redundant queries
$context['checked_facts'] = $checked_facts;
```

### 2. Batch Feedback Processing

```php
// Process multiple feedback items in one transaction
$wpdb->query('START TRANSACTION');
foreach ($feedback_items as $item) {
    $wpdb->insert(...);
}
$wpdb->query('COMMIT');
```

### 3. Lazy Loading

```php
// Initialize components only when needed
if (class_exists('\HomayeTabesh\HT_Authority_Manager')) {
    $this->authority_manager = new HT_Authority_Manager();
}
```

---

## 🧪 Testing

### Unit Tests (Example)

```php
// Test Authority Manager
$authority = new HT_Authority_Manager();

// Test Level 1: Manual Override
$authority->set_manual_override('test_key', 100);
$value = $authority->get_final_fact('test_key');
assert($value === 100);

// Test Orchestrator
$orchestrator = new HT_Action_Orchestrator();
$result = $orchestrator->execute_actions([
    ['type' => 'track_event', 'params' => ['event_type' => 'test']],
]);
assert($result['success'] === true);
```

---

## 📊 Monitoring & Logs

### Debug Logging

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log(sprintf(
        'Homa Authority Decision - Key: %s, Level: %s, Value: %s',
        $key, $level, $value
    ));
}
```

### Action History

```php
$history = $orchestrator->get_execution_history();
/*
[
    ['action' => [...], 'success' => true, 'timestamp' => '...'],
    ...
]
*/
```

---

**End of Implementation Document**
