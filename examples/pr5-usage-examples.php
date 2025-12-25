<?php
/**
 * PR5 Usage Examples - Action & Conversion Engine
 * 
 * This file demonstrates how to use the Action & Conversion Engine features
 * 
 * @package HomayeTabesh
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example 1: Fast Cart Addition with Homa Configuration
 * 
 * This example shows how Homa can add a product to cart with custom configuration
 * from the chat conversation.
 */
function pr5_example_fast_cart_addition() {
    echo "<h2>مثال ۱: افزودن سریع به سبد خرید</h2>";
    
    // Simulate adding a book printing product with custom specifications
    $product_id = 405; // Example product ID
    $homa_config = [
        'book_title' => 'ققنوس',
        'pages' => '240',
        'binding_type' => 'Hardcover',
        'paper_type' => 'گلاسه',
        'cover_finish' => 'براق',
        'quantity' => '500',
        'color_mode' => 'CMYK',
        'source' => 'homa_chat'
    ];
    
    echo "<h3>داده‌های ارسالی به API:</h3>";
    echo "<pre>";
    echo json_encode([
        'product_id' => $product_id,
        'quantity' => 1,
        'homa_config' => $homa_config
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
    echo "<h3>نحوه استفاده از JavaScript:</h3>";
    echo "<pre>";
    echo htmlspecialchars("
// از سمت چت Homa
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
            pages: '240',
            binding_type: 'Hardcover',
            quantity: '500'
        }
    })
});

const data = await response.json();
console.log('محصول به سبد اضافه شد:', data);
    ");
    echo "</pre>";
}

/**
 * Example 2: Applying Dynamic Discounts
 * 
 * Shows how Homa can apply discounts based on user behavior
 */
function pr5_example_apply_discount() {
    echo "<h2>مثال ۲: اعمال تخفیف هوشمند</h2>";
    
    echo "<h3>سناریو: کاربر ۵ بار قیمت را تغییر داده و هنوز خرید نکرده</h3>";
    
    echo "<h3>درخواست API برای تخفیف ۲۰٪:</h3>";
    echo "<pre>";
    echo htmlspecialchars("
// هما تشخیص می‌دهد کاربر با قیمت مشکل دارد
fetch('/wp-json/homaye/v1/cart/apply-discount', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': homayePerceptionConfig.nonce
    },
    body: JSON.stringify({
        discount_type: 'percentage',
        discount_value: 20,
        reason: 'price_hesitation_recovery'
    })
}).then(response => response.json())
  .then(data => {
      console.log('تخفیف اعمال شد:', data);
      // نمایش پیام به کاربر
      showToast('تخفیف ۲۰٪ برای شما اعمال شد!');
  });
    ");
    echo "</pre>";
    
    echo "<h3>نتیجه:</h3>";
    echo "<ul>";
    echo "<li>کوپن یکبار مصرف ایجاد می‌شود</li>";
    echo "<li>به صورت خودکار به سبد اعمال می‌شود</li>";
    echo "<li>کاربر پیام موفقیت دریافت می‌کند</li>";
    echo "<li>هما دکمه «پرداخت با هما» را نمایش می‌دهد</li>";
    echo "</ul>";
}

/**
 * Example 3: Form Field Synchronization
 * 
 * Shows how chat values sync to shortcode forms
 */
function pr5_example_form_sync() {
    echo "<h2>مثال ۳: همگام‌سازی فرم</h2>";
    
    echo "<h3>سناریو: کاربر در چت می‌گوید «اسم کتابم ققنوس است و ۲۴۰ صفحه دارد»</h3>";
    
    echo "<h3>کد JavaScript برای همگام‌سازی:</h3>";
    echo "<pre>";
    echo htmlspecialchars("
// هما داده‌ها را از چت استخراج می‌کند
const extractedData = {
    book_title: 'ققنوس',
    pages: '240'
};

// همگام‌سازی تک فیلد
Homa.FormHydration.syncField('book_title', 'ققنوس');
Homa.FormHydration.syncField('pages', '240');

// یا همگام‌سازی دسته‌ای
Homa.FormHydration.syncBulk({
    book_title: 'ققنوس',
    pages: '240',
    binding_type: 'Hardcover'
});

// استفاده از رویداد سفارشی
document.dispatchEvent(new CustomEvent('homa:sync-field', {
    detail: {
        fieldName: 'book_title',
        value: 'ققنوس',
        triggerRecalc: true
    }
}));
    ");
    echo "</pre>";
    
    echo "<h3>ویژگی‌های پیاده‌سازی شده:</h3>";
    echo "<ul>";
    echo "<li>جستجوی هوشمند فیلد (با ID، name، semantic name، label)</li>";
    echo "<li>تریگر خودکار محاسبات قیمت</li>";
    echo "<li>پشتیبانی از Gravity Forms، Contact Form 7، WPForms و...</li>";
    echo "<li>سازگاری با فرم‌های AJAX</li>";
    echo "</ul>";
}

/**
 * Example 4: Exit Intent Handling
 * 
 * Shows how exit intent triggers work
 */
function pr5_example_exit_intent() {
    echo "<h2>مثال ۴: مدیریت قصد خروج</h2>";
    
    echo "<h3>سناریو: کاربر فرم را ۶۰٪ پر کرده و ماوس به سمت بالا می‌برد</h3>";
    
    echo "<h3>فرآیند تشخیص:</h3>";
    echo "<ol>";
    echo "<li>Velocity Tracker حرکت سریع ماوس به سمت بالا را تشخیص می‌دهد</li>";
    echo "<li>تریگر EXIT_INTENT فعال می‌شود</li>";
    echo "<li>هما به صورت خودکار پیشنهاد تخفیف می‌دهد</li>";
    echo "<li>یک تایمر معکوس ۱۰ دقیقه‌ای نمایش داده می‌شود</li>";
    echo "</ol>";
    
    echo "<h3>کد JavaScript خودکار:</h3>";
    echo "<pre>";
    echo htmlspecialchars("
// این کد به صورت خودکار در homa-conversion-triggers.js اجرا می‌شود

// تشخیص خروج
document.addEventListener('mouseleave', (e) => {
    if (e.clientY < 0 && !exitIntentShown) {
        // بررسی سرعت حرکت
        const avgVelocity = calculateAverageVelocity();
        
        if (avgVelocity < -0.5) {
            // تریگر مداخله
            Homa.ConversionTriggers.triggerExitIntent();
            
            // نمایش پیشنهاد
            Homa.OfferDisplay.showOffer('discount', {
                title: '⚡ یک لحظه صبر کنید!',
                message: 'یک تخفیف ویژه ۱۵٪ برای شما',
                discountPercent: 15,
                expiresIn: 600
            });
        }
    }
});
    ");
    echo "</pre>";
}

/**
 * Example 5: Conversion Session Tracking
 * 
 * Shows how to track and recover abandoned carts
 */
function pr5_example_session_tracking() {
    echo "<h2>مثال ۵: ردیابی جلسه تبدیل</h2>";
    
    $core = \HomayeTabesh\HT_Core::instance();
    $persona_manager = $core->memory;
    
    echo "<h3>ذخیره جلسه تبدیل:</h3>";
    echo "<pre>";
    echo htmlspecialchars("
// PHP - ذخیره داده‌های جلسه
\$session_data = [
    'form_completion' => 75,
    'cart_value' => 2500000,
    'conversion_status' => 'in_progress',
    'filled_fields' => ['book_title', 'pages', 'quantity'],
    'last_interaction' => 'price_change',
    'hesitation_count' => 3,
    'offers_shown' => ['exit_intent_15%'],
    'page_url' => '/order-form'
];

\$persona_manager->save_conversion_session('user_123', \$session_data);
    ");
    echo "</pre>";
    
    echo "<h3>بازیابی جلسه‌های رها شده:</h3>";
    echo "<pre>";
    echo htmlspecialchars("
// دریافت جلسه‌های رها شده (بدون فعالیت بیش از ۱ ساعت)
\$abandoned = \$persona_manager->get_abandoned_sessions(1);

foreach (\$abandoned as \$session) {
    echo \"کاربر: \" . \$session['user_identifier'] . \"\\n\";
    echo \"تکمیل فرم: \" . \$session['form_completion'] . \"%\\n\";
    echo \"ارزش سبد: \" . \$session['cart_value'] . \" تومان\\n\";
    
    // ارسال ایمیل بازگشت یا نمایش پیشنهاد ویژه
    send_recovery_email(\$session);
}
    ");
    echo "</pre>";
    
    echo "<h3>تکمیل تبدیل:</h3>";
    echo "<pre>";
    echo htmlspecialchars("
// زمانی که کاربر خرید را تکمیل کرد
\$persona_manager->complete_conversion_session('user_123', \$order_id = 1234);
    ");
    echo "</pre>";
    
    // Show example of abandoned sessions if any exist
    try {
        $abandoned = $persona_manager->get_abandoned_sessions(24); // Last 24 hours
        
        if (!empty($abandoned)) {
            echo "<h3>جلسه‌های رها شده در ۲۴ ساعت گذشته:</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr>";
            echo "<th>شناسه کاربر</th>";
            echo "<th>تکمیل فرم</th>";
            echo "<th>ارزش سبد</th>";
            echo "<th>آخرین فعالیت</th>";
            echo "</tr>";
            
            foreach (array_slice($abandoned, 0, 5) as $session) {
                echo "<tr>";
                echo "<td>" . esc_html(substr($session['user_identifier'], 0, 20)) . "...</td>";
                echo "<td>" . esc_html($session['form_completion']) . "%</td>";
                echo "<td>" . number_format($session['cart_value']) . " تومان</td>";
                echo "<td>" . esc_html($session['last_activity']) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p><em>هیچ جلسه رها شده‌ای یافت نشد.</em></p>";
        }
    } catch (\Exception $e) {
        echo "<p><em>خطا در بارگذاری جلسه‌های رها شده.</em></p>";
    }
}

/**
 * Example 6: Complete Workflow
 * 
 * Shows the entire conversion flow
 */
function pr5_example_complete_workflow() {
    echo "<h2>مثال ۶: گردش کار کامل</h2>";
    
    echo "<h3>مراحل تبدیل:</h3>";
    echo "<ol>";
    echo "<li><strong>کاربر وارد صفحه فرم می‌شود</strong>";
    echo "<ul><li>Homa Indexer فرم را شناسایی می‌کند</li></ul></li>";
    
    echo "<li><strong>کاربر شروع به پر کردن فرم می‌کند</strong>";
    echo "<ul><li>Input Observer متن را تحلیل می‌کند</li>";
    echo "<li>Session Tracking شروع می‌شود</li></ul></li>";
    
    echo "<li><strong>کاربر در فیلد قیمت مکث می‌کند (۶۰ ثانیه)</strong>";
    echo "<ul><li>Field Hesitation تریگر می‌شود</li>";
    echo "<li>هما پیشنهاد کمک می‌دهد</li></ul></li>";
    
    echo "<li><strong>کاربر ۵ بار قیمت را تغییر می‌دهد</strong>";
    echo "<ul><li>Price Change Counter فعال می‌شود</li>";
    echo "<li>هما تخفیف پیشنهاد می‌دهد</li></ul></li>";
    
    echo "<li><strong>کاربر تخفیف را می‌پذیرد</strong>";
    echo "<ul><li>کوپن ساخته و اعمال می‌شود</li>";
    echo "<li>محصول به سبد اضافه می‌شود</li></ul></li>";
    
    echo "<li><strong>هما دکمه «پرداخت با هما» را نمایش می‌دهد</strong>";
    echo "<ul><li>کاربر مستقیم به checkout می‌رود</li>";
    echo "<li>Session به عنوان completed علامت‌گذاری می‌شود</li></ul></li>";
    echo "</ol>";
}

// Run all examples
if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
    // Don't run in admin or AJAX
} else {
    echo "<!DOCTYPE html>";
    echo "<html dir='rtl'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<title>PR5 Usage Examples - Action & Conversion Engine</title>";
    echo "<style>";
    echo "body { font-family: Tahoma, Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }";
    echo "pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; direction: ltr; text-align: left; }";
    echo "h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }";
    echo "h3 { color: #34495e; }";
    echo "ul, ol { line-height: 1.8; }";
    echo "table { width: 100%; margin: 20px 0; }";
    echo "th { background: #3498db; color: white; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    echo "<h1>🚀 PR5: موتور عملیاتی و مداخله هوشمند</h1>";
    echo "<p>این صفحه مثال‌های استفاده از ویژگی‌های PR5 را نمایش می‌دهد.</p>";
    
    pr5_example_fast_cart_addition();
    echo "<hr>";
    
    pr5_example_apply_discount();
    echo "<hr>";
    
    pr5_example_form_sync();
    echo "<hr>";
    
    pr5_example_exit_intent();
    echo "<hr>";
    
    pr5_example_session_tracking();
    echo "<hr>";
    
    pr5_example_complete_workflow();
    
    echo "</body>";
    echo "</html>";
}
