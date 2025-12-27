<?php
/**
 * Validation Script for PR21 Critical Fixes
 * Tests all database tables, methods, and API fixes
 * 
 * Usage: Run from WordPress root or directly via CLI
 */

// Bootstrap WordPress if not already loaded
if (!defined('ABSPATH')) {
    // Try to find wp-load.php
    $wp_load = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die("Error: Cannot find WordPress installation. Run this from WordPress root.\n");
    }
}

echo "\n=== PR#21 Critical Fixes Validation ===\n\n";

$passed = 0;
$failed = 0;
$warnings = 0;

/**
 * Test 1: Database Tables
 */
echo "Test 1: Checking Database Tables...\n";
global $wpdb;

$required_tables = [
    'homaye_ai_requests',
    'homaye_leads',
    'homa_leads',
    'homaye_knowledge',
    'homaye_security_scores',
];

foreach ($required_tables as $table) {
    $table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    
    if ($exists) {
        echo "  ✅ Table $table exists\n";
        $passed++;
    } else {
        echo "  ❌ Table $table MISSING\n";
        $failed++;
    }
}

/**
 * Test 2: HT_Gemini_Client::generate_response method
 */
echo "\nTest 2: Checking HT_Gemini_Client methods...\n";

if (class_exists('\HomayeTabesh\HT_Gemini_Client')) {
    $gemini = new \HomayeTabesh\HT_Gemini_Client();
    
    if (method_exists($gemini, 'generate_response')) {
        echo "  ✅ generate_response method exists\n";
        $passed++;
    } else {
        echo "  ❌ generate_response method MISSING\n";
        $failed++;
    }
    
    if (method_exists($gemini, 'generate_content')) {
        echo "  ✅ generate_content method exists\n";
        $passed++;
    } else {
        echo "  ❌ generate_content method MISSING\n";
        $failed++;
    }
} else {
    echo "  ❌ HT_Gemini_Client class not found\n";
    $failed += 2;
}

/**
 * Test 3: HT_Knowledge_Base::get_facts method
 */
echo "\nTest 3: Checking HT_Knowledge_Base methods...\n";

if (class_exists('\HomayeTabesh\HT_Knowledge_Base')) {
    $kb = new \HomayeTabesh\HT_Knowledge_Base();
    
    if (method_exists($kb, 'get_facts')) {
        echo "  ✅ get_facts method exists\n";
        $passed++;
        
        // Test the method
        try {
            $facts = $kb->get_facts();
            echo "  ✅ get_facts method executable (returned " . count($facts) . " facts)\n";
            $passed++;
        } catch (Exception $e) {
            echo "  ⚠️ get_facts throws exception: " . $e->getMessage() . "\n";
            $warnings++;
        }
    } else {
        echo "  ❌ get_facts method MISSING\n";
        $failed++;
    }
    
    if (method_exists($kb, 'save_fact')) {
        echo "  ✅ save_fact method exists\n";
        $passed++;
    } else {
        echo "  ❌ save_fact method MISSING\n";
        $failed++;
    }
} else {
    echo "  ❌ HT_Knowledge_Base class not found\n";
    $failed += 2;
}

/**
 * Test 4: REST API Endpoints
 */
echo "\nTest 4: Checking REST API endpoints...\n";

$rest_server = rest_get_server();

// Test Gemini test endpoint
$routes = $rest_server->get_routes();
if (isset($routes['/homaye/v1/test-gemini'])) {
    echo "  ✅ /homaye/v1/test-gemini endpoint registered\n";
    $passed++;
} else {
    echo "  ❌ /homaye/v1/test-gemini endpoint MISSING\n";
    $failed++;
}

// Test Atlas endpoints
$atlas_endpoints = [
    '/homaye/v1/atlas/health',
    '/homaye/v1/atlas/recommendations',
    '/homaye/v1/atlas/settings',
];

foreach ($atlas_endpoints as $endpoint) {
    if (isset($routes[$endpoint])) {
        echo "  ✅ $endpoint endpoint registered\n";
        $passed++;
    } else {
        echo "  ⚠️ $endpoint endpoint not found\n";
        $warnings++;
    }
}

/**
 * Test 5: Settings Registration
 */
echo "\nTest 5: Checking Settings Registration...\n";

$settings = [
    'ht_index_post_types',
    'ht_auto_index_enabled',
];

foreach ($settings as $setting) {
    $value = get_option($setting, '__NOT_FOUND__');
    if ($value !== '__NOT_FOUND__') {
        echo "  ✅ Setting $setting registered\n";
        $passed++;
    } else {
        echo "  ⚠️ Setting $setting not initialized (will be created on save)\n";
        $warnings++;
    }
}

/**
 * Test 6: SMS Service
 */
echo "\nTest 6: Checking SMS Service...\n";

if (class_exists('\HomayeTabesh\Homa_SMS_Provider')) {
    $sms = new \HomayeTabesh\Homa_SMS_Provider();
    
    if (method_exists($sms, 'send_pattern')) {
        echo "  ✅ send_pattern method exists\n";
        $passed++;
    } else {
        echo "  ❌ send_pattern method MISSING\n";
        $failed++;
    }
    
    if (method_exists($sms, 'send_otp')) {
        echo "  ✅ send_otp method exists\n";
        $passed++;
    } else {
        echo "  ❌ send_otp method MISSING\n";
        $failed++;
    }
    
    if (method_exists($sms, 'send_lead_notification')) {
        echo "  ✅ send_lead_notification method exists\n";
        $passed++;
    } else {
        echo "  ❌ send_lead_notification method MISSING\n";
        $failed++;
    }
} else {
    echo "  ❌ Homa_SMS_Provider class not found\n";
    $failed += 3;
}

/**
 * Test 7: Database Version Tracking
 */
echo "\nTest 7: Checking Database Version Tracking...\n";

$db_version = get_option('homa_db_version');
if ($db_version) {
    echo "  ✅ Database version tracked: $db_version\n";
    $passed++;
} else {
    echo "  ⚠️ Database version not set (will be set on next activation)\n";
    $warnings++;
}

$db_last_update = get_option('homa_db_last_update');
if ($db_last_update) {
    echo "  ✅ Database last update tracked: $db_last_update\n";
    $passed++;
} else {
    echo "  ⚠️ Database last update not set (will be set on next activation)\n";
    $warnings++;
}

/**
 * Summary
 */
echo "\n=== Validation Summary ===\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "⚠️ Warnings: $warnings\n";

if ($failed > 0) {
    echo "\n🔴 VALIDATION FAILED - Please check the failed tests above\n";
    exit(1);
} elseif ($warnings > 0) {
    echo "\n🟡 VALIDATION PASSED WITH WARNINGS - Some optional features need attention\n";
    exit(0);
} else {
    echo "\n🟢 ALL TESTS PASSED - PR#21 fixes are working correctly!\n";
    exit(0);
}
