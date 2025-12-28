<?php
/**
 * Validation Script for PR Fixes
 * 
 * Tests the critical fixes applied in this PR:
 * 1. Type compatibility in get_user_behavior()
 * 2. Existence of get_user_journey() method
 * 3. GapGPT settings in Console API
 * 4. Dynamic AI provider in diagnostics
 * 5. CSS file existence for Super Console
 * 
 * @package HomayeTabesh
 */

declare(strict_types=1);

echo "=== PR Fixes Validation ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Check HT_Parallel_UI.php type declaration
echo "Test 1: Checking get_user_behavior() type declaration...\n";
$parallel_ui_file = __DIR__ . '/includes/HT_Parallel_UI.php';
$content = file_get_contents($parallel_ui_file);

if (preg_match('/function get_user_behavior\(\$user_id\): array/', $content)) {
    $success[] = "✓ get_user_behavior() accepts mixed type (int|string)";
} elseif (preg_match('/function get_user_behavior\(string \$user_id\): array/', $content)) {
    $errors[] = "✗ get_user_behavior() still has strict string type hint";
} else {
    $warnings[] = "⚠ Could not verify get_user_behavior() signature";
}

// Test 2: Check HT_Telemetry.php for get_user_journey() method
echo "Test 2: Checking for get_user_journey() method...\n";
$telemetry_file = __DIR__ . '/includes/HT_Telemetry.php';
$content = file_get_contents($telemetry_file);

if (strpos($content, 'function get_user_journey(') !== false) {
    $success[] = "✓ get_user_journey() method exists in HT_Telemetry";
} else {
    $errors[] = "✗ get_user_journey() method not found in HT_Telemetry";
}

// Test 3: Check Console API for GapGPT settings
echo "Test 3: Checking Console API for GapGPT settings...\n";
$console_api_file = __DIR__ . '/includes/HT_Console_Analytics_API.php';
$content = file_get_contents($console_api_file);

$has_gapgpt_settings = (
    strpos($content, "'ai_provider'") !== false &&
    strpos($content, "'gapgpt_api_key'") !== false &&
    strpos($content, "'gapgpt_base_url'") !== false
);

if ($has_gapgpt_settings) {
    $success[] = "✓ Console API includes GapGPT settings (ai_provider, gapgpt_api_key, gapgpt_base_url)";
} else {
    $errors[] = "✗ Console API missing GapGPT settings";
}

// Test 4: Check System Diagnostics for dynamic API test
echo "Test 4: Checking System Diagnostics for dynamic AI provider...\n";
$diagnostics_file = __DIR__ . '/includes/HT_System_Diagnostics.php';
$content = file_get_contents($diagnostics_file);

if (strpos($content, "'gapgpt_api' => \$this->test_ai_connection()") !== false) {
    $success[] = "✓ System diagnostics uses dynamic test_ai_connection() method";
} elseif (strpos($content, "'gemini_api' => \$this->test_gemini_connection()") !== false) {
    $errors[] = "✗ System diagnostics still hardcoded to Gemini";
} else {
    $warnings[] = "⚠ Could not verify diagnostics API test method";
}

// Check if test_ai_connection() uses dynamic provider
if (strpos($content, "get_option('ht_ai_provider'") !== false) {
    $success[] = "✓ test_ai_connection() reads ai_provider option dynamically";
} else {
    $warnings[] = "⚠ test_ai_connection() may not be reading provider dynamically";
}

// Test 5: Check for Super Console CSS file
echo "Test 5: Checking for Super Console CSS file...\n";
$css_file = __DIR__ . '/assets/css/super-console.css';

if (file_exists($css_file)) {
    $css_size = filesize($css_file);
    $success[] = "✓ super-console.css exists (" . round($css_size / 1024, 2) . " KB)";
    
    // Check if it has substantial content
    if ($css_size > 10000) {
        $success[] = "✓ super-console.css has substantial content";
    } else {
        $warnings[] = "⚠ super-console.css seems small, may be incomplete";
    }
} else {
    $errors[] = "✗ super-console.css not found";
}

// Test 6: Check HT_Admin.php enqueues the CSS
echo "Test 6: Checking if HT_Admin enqueues Super Console CSS...\n";
$admin_file = __DIR__ . '/includes/HT_Admin.php';
$content = file_get_contents($admin_file);

if (strpos($content, "wp_enqueue_style") !== false && 
    strpos($content, "super-console") !== false &&
    strpos($content, "super-console.css") !== false) {
    $success[] = "✓ HT_Admin enqueues super-console.css";
} else {
    $errors[] = "✗ HT_Admin does not enqueue super-console.css";
}

// Test 7: Check that JSX components don't have styled-jsx
echo "Test 7: Checking JSX components for styled-jsx removal...\n";
$jsx_files = glob(__DIR__ . '/assets/react/super-console-components/*.jsx');
$has_styled_jsx = false;

foreach ($jsx_files as $jsx_file) {
    $content = file_get_contents($jsx_file);
    if (strpos($content, '<style jsx>') !== false) {
        $has_styled_jsx = true;
        $errors[] = "✗ " . basename($jsx_file) . " still contains <style jsx>";
    }
}

if (!$has_styled_jsx) {
    $success[] = "✓ All JSX components have styled-jsx removed";
}

// Test 8: Check webpack build output
echo "Test 8: Checking webpack build output...\n";
$super_console_js = __DIR__ . '/assets/build/super-console.js';

if (file_exists($super_console_js)) {
    $js_size = filesize($super_console_js);
    $success[] = "✓ super-console.js exists (" . round($js_size / 1024, 2) . " KB)";
    
    // Check modification time (should be recent)
    $mtime = filemtime($super_console_js);
    if (time() - $mtime < 3600) { // Less than 1 hour old
        $success[] = "✓ super-console.js was recently rebuilt";
    } else {
        $warnings[] = "⚠ super-console.js may be outdated (not recently built)";
    }
} else {
    $errors[] = "✗ super-console.js not found (webpack build failed?)";
}

// Print results
echo "\n=== VALIDATION RESULTS ===\n\n";

if (!empty($success)) {
    echo "✅ SUCCESS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

// Final verdict
$total_checks = count($success) + count($warnings) + count($errors);
$pass_rate = round((count($success) / $total_checks) * 100, 1);

echo "=== SUMMARY ===\n";
echo "Total Checks: $total_checks\n";
echo "Passed: " . count($success) . "\n";
echo "Warnings: " . count($warnings) . "\n";
echo "Failed: " . count($errors) . "\n";
echo "Pass Rate: $pass_rate%\n\n";

if (empty($errors)) {
    echo "🎉 All critical fixes validated successfully!\n";
    exit(0);
} else {
    echo "❌ Some critical fixes failed validation.\n";
    exit(1);
}
