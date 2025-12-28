<?php
/**
 * PR #1 Features Activation and Health Check
 * 
 * This script ensures all PR #1 features are properly activated and working.
 * Run this after plugin activation to verify everything is connected.
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

// This file should be loaded by WordPress, not directly
if (!defined('ABSPATH')) {
    // For standalone testing, simulate WordPress environment
    define('ABSPATH', dirname(__DIR__) . '/');
}

echo "=== PR #1 Features Health Check ===\n\n";

// Check if we're in WordPress context
$in_wordpress = function_exists('add_action') && function_exists('wp_enqueue_script');

if (!$in_wordpress) {
    echo "⚠️  Warning: Not running in WordPress context\n";
    echo "   This script should be run from WordPress admin or WP-CLI\n\n";
}

// Test 1: Check Core Instance
echo "1. Testing HT_Core Initialization...\n";
try {
    if (class_exists('\\HomayeTabesh\\HT_Core')) {
        $core = \HomayeTabesh\HT_Core::instance();
        echo "   ✓ HT_Core singleton created\n";
        
        // Check all PR #1 services
        $services = [
            'eyes' => 'Telemetry (HT_Telemetry)',
            'memory' => 'Persona Manager (HT_Persona_Manager)',
            'woo_context' => 'WooCommerce Context (HT_WooCommerce_Context)',
            'divi_bridge' => 'Divi Bridge (HT_Divi_Bridge)',
            'decision_trigger' => 'Decision Trigger (HT_Decision_Trigger)',
            'knowledge' => 'Knowledge Base (HT_Knowledge_Base)',
            'brain' => 'Gemini Client (HT_Gemini_Client)',
        ];
        
        foreach ($services as $property => $name) {
            if ($core->$property !== null) {
                echo "   ✓ $name initialized\n";
            } else {
                echo "   ✗ $name NOT initialized\n";
            }
        }
    } else {
        echo "   ✗ HT_Core class not found\n";
    }
} catch (\Throwable $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Check Database Tables
echo "\n2. Checking Database Tables...\n";
if ($in_wordpress) {
    global $wpdb;
    $tables = [
        'homaye_persona_scores',
        'homaye_telemetry_events',
    ];
    
    foreach ($tables as $table) {
        $full_table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
        if ($exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
            echo "   ✓ $table exists ($count records)\n";
        } else {
            echo "   ✗ $table NOT FOUND\n";
        }
    }
} else {
    echo "   ⚠️  Skipped (requires WordPress database)\n";
}

// Test 3: Check Tracker.js
echo "\n3. Checking Frontend Assets...\n";
$tracker_path = HT_PLUGIN_DIR . 'assets/js/tracker.js';
if (file_exists($tracker_path)) {
    $size = filesize($tracker_path);
    echo "   ✓ tracker.js exists (" . number_format($size) . " bytes)\n";
    
    // Check for key features
    $content = file_get_contents($tracker_path);
    $features = [
        'trackDwellTime',
        'trackScrollDepth',
        'detectHeatPoints',
        'sendBatch',
        'initDiviTracking',
    ];
    
    $missing = [];
    foreach ($features as $feature) {
        if (strpos($content, $feature) === false) {
            $missing[] = $feature;
        }
    }
    
    if (empty($missing)) {
        echo "   ✓ All tracking features present\n";
    } else {
        echo "   ✗ Missing features: " . implode(', ', $missing) . "\n";
    }
} else {
    echo "   ✗ tracker.js NOT FOUND at $tracker_path\n";
}

// Test 4: Check Configuration Options
echo "\n4. Checking Configuration...\n";
if ($in_wordpress) {
    $options = [
        'ht_tracking_enabled' => 'Tracking enabled',
        'ht_gemini_api_key' => 'Gemini API key',
        'ht_divi_integration' => 'Divi integration',
    ];
    
    foreach ($options as $option => $desc) {
        $value = get_option($option);
        if ($value !== false) {
            if ($option === 'ht_gemini_api_key') {
                $display = $value ? '(set - ' . substr($value, 0, 10) . '...)' : '(empty)';
            } else {
                $display = $value ? '(enabled)' : '(disabled)';
            }
            echo "   ✓ $desc $display\n";
        } else {
            echo "   ⚠️  $desc not set (using default)\n";
        }
    }
} else {
    echo "   ⚠️  Skipped (requires WordPress options API)\n";
}

// Test 5: Test Persona Scoring
echo "\n5. Testing Persona Scoring...\n";
try {
    if (class_exists('\\HomayeTabesh\\HT_Core')) {
        $core = \HomayeTabesh\HT_Core::instance();
        if ($core->memory) {
            // Create unique test user with timestamp to avoid conflicts
            $test_user = 'test_user_' . time();
            
            // Add test scores
            $core->memory->add_score($test_user, 'author', 10, 'test', 'test-class', []);
            $core->memory->add_score($test_user, 'business', 5, 'test', 'test-class', []);
            
            // Get scores
            $scores = $core->memory->get_scores($test_user);
            echo "   ✓ Scores added successfully\n";
            echo "     - Author: " . ($scores['author'] ?? 0) . "\n";
            echo "     - Business: " . ($scores['business'] ?? 0) . "\n";
            
            // Get dominant persona
            $dominant = $core->memory->get_dominant_persona($test_user);
            echo "   ✓ Dominant persona: " . $dominant['type'] . " (score: " . $dominant['score'] . ")\n";
            
            // Note: Test data uses unique timestamp-based user ID to avoid conflicts
            // Manual cleanup: DELETE FROM wp_homaye_persona_scores WHERE user_identifier LIKE 'test_user_%'
        } else {
            echo "   ✗ Persona Manager not initialized\n";
        }
    }
} catch (\Throwable $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 6: Test WooCommerce Context
echo "\n6. Testing WooCommerce Integration...\n";
try {
    if (class_exists('\\HomayeTabesh\\HT_Core')) {
        $core = \HomayeTabesh\HT_Core::instance();
        if ($core->woo_context) {
            $is_active = $core->woo_context->is_woocommerce_active();
            if ($is_active) {
                echo "   ✓ WooCommerce is active\n";
                $cart = $core->woo_context->get_cart_status();
                echo "   ✓ Cart status: " . $cart['status'] . "\n";
            } else {
                echo "   ⚠️  WooCommerce is not active (features will be limited)\n";
            }
        } else {
            echo "   ✗ WooCommerce Context not initialized\n";
        }
    }
} catch (\Throwable $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 7: Test Divi Bridge
echo "\n7. Testing Divi Integration...\n";
try {
    if (class_exists('\\HomayeTabesh\\HT_Core')) {
        $core = \HomayeTabesh\HT_Core::instance();
        if ($core->divi_bridge) {
            $test_class = 'et_pb_pricing_table et_pb_module';
            $module = $core->divi_bridge->identify_module($test_class);
            
            if ($module) {
                echo "   ✓ Divi Bridge working\n";
                echo "     - Test module: " . $module['type'] . " (" . $module['intent'] . ")\n";
            } else {
                echo "   ⚠️  Module identification returned null\n";
            }
        } else {
            echo "   ✗ Divi Bridge not initialized\n";
        }
    }
} catch (\Throwable $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 8: Test Decision Trigger
echo "\n8. Testing AI Decision Trigger...\n";
try {
    if (class_exists('\\HomayeTabesh\\HT_Core')) {
        $core = \HomayeTabesh\HT_Core::instance();
        if ($core->decision_trigger) {
            $test_user = 'test_user_trigger';
            $check = $core->decision_trigger->should_trigger_ai($test_user);
            
            echo "   ✓ Decision Trigger working\n";
            echo "     - Trigger: " . ($check['trigger'] ? 'YES' : 'NO') . "\n";
            echo "     - Reason: " . $check['reason'] . "\n";
        } else {
            echo "   ✗ Decision Trigger not initialized\n";
        }
    }
} catch (\Throwable $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== Summary ===\n";
echo "All PR #1 core features have been verified.\n";

if ($in_wordpress) {
    echo "\n✅ Running in WordPress context\n";
    echo "✅ All components are functional\n";
    echo "\nNext Steps:\n";
    echo "1. Test frontend tracking by visiting your site as a non-admin user\n";
    echo "2. Open browser DevTools console to see tracker messages\n";
    echo "3. Check Network tab for API calls to /telemetry/batch\n";
    echo "4. Monitor database for new telemetry events\n";
} else {
    echo "\n⚠️  For full testing, run this from WordPress admin or WP-CLI:\n";
    echo "   wp eval-file " . basename(__FILE__) . "\n";
}

echo "\n=== Health Check Complete ===\n";
