<?php
/**
 * Example: Using Homaye Tabesh API
 * 
 * This file demonstrates how to use the Homaye Tabesh plugin
 * in your WordPress theme or other plugins.
 * 
 * @package HomayeTabesh
 * @since 1.0.0
 */

// Don't run this file directly - it's just for reference
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example 1: Get user's persona
 */
function example_get_user_persona() {
    // Get plugin instance
    $homaye = \HomayeTabesh\HT_Core::instance();
    
    // Get current user identifier
    $user_id = is_user_logged_in() ? 'user_' . get_current_user_id() : 'guest_' . session_id();
    
    // Get dominant persona
    $persona = $homaye->memory->get_dominant_persona($user_id);
    
    echo "نوع پرسونا: " . $persona['type'] . "<br>";
    echo "امتیاز: " . $persona['score'] . "<br>";
    echo "اطمینان: " . $persona['confidence'] . "%<br>";
}

/**
 * Example 2: Generate AI response based on user behavior
 */
function example_ai_recommendation() {
    $homaye = \HomayeTabesh\HT_Core::instance();
    
    $user_id = 'user_123'; // Replace with actual user ID
    
    // Get user's behavior summary
    $behavior = $homaye->memory->get_behavior_summary($user_id);
    
    // Get product context if WooCommerce is active
    $products = [];
    if (class_exists('WooCommerce')) {
        $products = $homaye->brain->get_woocommerce_context();
    }
    
    // Get persona
    $persona = $homaye->memory->get_dominant_persona($user_id);
    
    // Generate recommendation
    $response = $homaye->brain->generate_content(
        'بر اساس رفتار کاربر، چه محصولی را پیشنهاد می‌کنی؟',
        [
            'products' => $products,
            'behavior' => $behavior,
            'persona' => $persona,
        ]
    );
    
    if ($response['success']) {
        echo $response['raw_text'];
    } else {
        echo 'خطا: ' . $response['error'];
    }
}

/**
 * Example 3: Track custom event
 */
function example_track_custom_event() {
    // This would typically be done via JavaScript,
    // but you can also do it server-side
    
    $homaye = \HomayeTabesh\HT_Core::instance();
    $user_id = 'user_123';
    
    // Add score for viewing a specific page
    $homaye->memory->add_score($user_id, 'author', 10);
    
    echo 'Event tracked successfully!';
}

/**
 * Example 4: Get full user analysis
 */
function example_full_analysis() {
    $homaye = \HomayeTabesh\HT_Core::instance();
    $user_id = 'user_123';
    
    $analysis = $homaye->memory->get_full_analysis($user_id);
    
    echo '<pre>';
    print_r($analysis);
    echo '</pre>';
}

/**
 * Example 5: Add custom tracking to a button
 * 
 * Use this in your theme template:
 */
function example_tracked_button() {
    ?>
    <button class="my-button" data-homaye-track="hover,click">
        دانلود کاتالوگ
    </button>
    <?php
}

/**
 * Example 6: Check if user matches a persona
 */
function example_check_persona($user_id, $persona_type = 'author') {
    $homaye = \HomayeTabesh\HT_Core::instance();
    
    $scores = $homaye->memory->get_scores($user_id);
    $threshold = 100; // For author persona
    
    if (isset($scores[$persona_type]) && $scores[$persona_type] >= $threshold) {
        return true;
    }
    
    return false;
}

/**
 * Example 7: Display personalized content based on persona
 */
function example_personalized_content() {
    $homaye = \HomayeTabesh\HT_Core::instance();
    $user_id = is_user_logged_in() ? 'user_' . get_current_user_id() : 'guest_' . session_id();
    
    $persona = $homaye->memory->get_dominant_persona($user_id);
    
    switch ($persona['type']) {
        case 'author':
            echo '<div class="persona-banner">';
            echo '<h3>خدمات ویژه نویسندگان</h3>';
            echo '<p>ما در چاپ کتاب تخصص داریم!</p>';
            echo '</div>';
            break;
            
        case 'business':
            echo '<div class="persona-banner">';
            echo '<h3>راهکارهای چاپ سازمانی</h3>';
            echo '<p>تخفیفات ویژه سفارش‌های عمده</p>';
            echo '</div>';
            break;
            
        case 'designer':
            echo '<div class="persona-banner">';
            echo '<h3>چاپ با کیفیت حرفه‌ای</h3>';
            echo '<p>مناسب برای پروژه‌های گرافیکی شما</p>';
            echo '</div>';
            break;
            
        default:
            echo '<div class="persona-banner">';
            echo '<h3>خدمات چاپ چاپکو</h3>';
            echo '<p>با کیفیت بالا و قیمت مناسب</p>';
            echo '</div>';
    }
}

/**
 * Example 8: Hook into WordPress to show recommendations
 */
add_action('wp_footer', function() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $homaye = \HomayeTabesh\HT_Core::instance();
    $user_id = 'user_' . get_current_user_id();
    
    $persona = $homaye->memory->get_dominant_persona($user_id);
    
    // Only show if confidence is high enough
    if ($persona['confidence'] >= 50) {
        ?>
        <div id="homaye-recommendation" style="position: fixed; bottom: 20px; right: 20px; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h4>پیشنهاد ویژه برای شما</h4>
            <p>بر اساس رفتار شما، پرسونای <strong><?php echo $persona['type']; ?></strong> را تشخیص دادیم.</p>
            <button onclick="this.parentElement.remove()">بستن</button>
        </div>
        <?php
    }
});
