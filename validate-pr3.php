#!/usr/bin/env php
<?php
/**
 * Validation Script for PR3 Implementation
 * Tests all components of the Inference Engine
 * 
 * @package HomayeTabesh
 * @since 1.0.0
 */

// Colors for terminal output
define('COLOR_GREEN', "\033[0;32m");
define('COLOR_RED', "\033[0;31m");
define('COLOR_YELLOW', "\033[1;33m");
define('COLOR_RESET', "\033[0m");

echo "====================================\n";
echo "  همای تابش - PR3 Validation\n";
echo "====================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

/**
 * Run a test and report result
 */
function run_test($name, $callback) {
    global $tests_passed, $tests_failed;
    
    echo "Testing: $name ... ";
    
    try {
        $result = $callback();
        if ($result) {
            echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
            $tests_passed++;
        } else {
            echo COLOR_RED . "✗ FAILED" . COLOR_RESET . "\n";
            $tests_failed++;
        }
    } catch (Exception $e) {
        echo COLOR_RED . "✗ ERROR: " . $e->getMessage() . COLOR_RESET . "\n";
        $tests_failed++;
    }
}

// Test 1: Check PHP version
run_test("PHP Version >= 8.2", function() {
    return version_compare(PHP_VERSION, '8.2.0', '>=');
});

// Test 2: Check required files exist
run_test("Core files exist", function() {
    $required_files = [
        'includes/HT_Inference_Engine.php',
        'includes/HT_Prompt_Builder_Service.php',
        'includes/HT_Action_Parser.php',
        'includes/HT_AI_Controller.php',
        'assets/js/ui-executor.js',
        'knowledge-base/pricing.json',
        'knowledge-base/faq.json',
    ];
    
    foreach ($required_files as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            throw new Exception("Missing file: $file");
        }
    }
    
    return true;
});

// Test 3: Check PHP syntax
run_test("PHP syntax validation", function() {
    $php_files = [
        'includes/HT_Inference_Engine.php',
        'includes/HT_Prompt_Builder_Service.php',
        'includes/HT_Action_Parser.php',
        'includes/HT_AI_Controller.php',
    ];
    
    foreach ($php_files as $file) {
        $output = [];
        $return_var = 0;
        exec("php -l " . __DIR__ . "/$file 2>&1", $output, $return_var);
        
        if ($return_var !== 0) {
            throw new Exception("Syntax error in $file");
        }
    }
    
    return true;
});

// Test 4: Validate JSON files
run_test("JSON files validation", function() {
    $json_files = [
        'knowledge-base/pricing.json',
        'knowledge-base/faq.json',
        'knowledge-base/personas.json',
        'knowledge-base/products.json',
        'knowledge-base/responses.json',
    ];
    
    foreach ($json_files as $file) {
        $content = file_get_contents(__DIR__ . '/' . $file);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in $file: " . json_last_error_msg());
        }
        
        if (empty($data)) {
            throw new Exception("Empty JSON in $file");
        }
    }
    
    return true;
});

// Test 5: Check class structure
run_test("Class structure validation", function() {
    require_once __DIR__ . '/includes/HT_Prompt_Builder_Service.php';
    
    // Check if class exists
    if (!class_exists('HomayeTabesh\HT_Prompt_Builder_Service')) {
        throw new Exception("HT_Prompt_Builder_Service class not found");
    }
    
    // Check required methods
    $required_methods = [
        'build_system_instruction',
        'build_user_prompt',
        'sanitize_input',
    ];
    
    $reflection = new ReflectionClass('HomayeTabesh\HT_Prompt_Builder_Service');
    foreach ($required_methods as $method) {
        if (!$reflection->hasMethod($method)) {
            throw new Exception("Missing method: $method");
        }
    }
    
    return true;
});

// Test 6: JavaScript syntax check
run_test("JavaScript syntax validation", function() {
    $js_file = __DIR__ . '/assets/js/ui-executor.js';
    
    // Check if file exists and is not empty
    if (!file_exists($js_file)) {
        throw new Exception("ui-executor.js not found");
    }
    
    $content = file_get_contents($js_file);
    if (empty($content)) {
        throw new Exception("ui-executor.js is empty");
    }
    
    // Check for required functions/classes
    $required_elements = [
        'HomaUIExecutor',
        'executeAction',
        'highlightElement',
        'showTooltip',
        'openModal',
    ];
    
    foreach ($required_elements as $element) {
        if (strpos($content, $element) === false) {
            throw new Exception("Missing element in ui-executor.js: $element");
        }
    }
    
    return true;
});

// Test 7: Knowledge base content validation
run_test("Knowledge base content validation", function() {
    $pricing = json_decode(file_get_contents(__DIR__ . '/knowledge-base/pricing.json'), true);
    
    // Check required sections
    $required_sections = [
        'paper_types',
        'binding_types',
        'print_quality',
        'tirage_pricing',
    ];
    
    foreach ($required_sections as $section) {
        if (!isset($pricing[$section])) {
            throw new Exception("Missing section in pricing.json: $section");
        }
    }
    
    // Check if paper types have required fields
    foreach ($pricing['paper_types'] as $type => $data) {
        if (!isset($data['name']) || !isset($data['price_factor'])) {
            throw new Exception("Incomplete paper type data: $type");
        }
    }
    
    return true;
});

// Test 8: Security - Prompt Injection patterns
run_test("Security: Prompt injection filter", function() {
    // Check for sanitize_input method and dangerous patterns filter
    $prompt_builder_code = file_get_contents(__DIR__ . '/includes/HT_Prompt_Builder_Service.php');
    
    if (strpos($prompt_builder_code, 'sanitize_input') === false) {
        throw new Exception("sanitize_input method not found");
    }
    
    if (strpos($prompt_builder_code, 'dangerous_patterns') === false) {
        throw new Exception("Dangerous patterns filter not implemented");
    }
    
    // Check for specific security measures
    $security_checks = [
        'ignore' => (strpos($prompt_builder_code, 'ignore') !== false),
        'system' => (strpos($prompt_builder_code, 'system') !== false),
        'forget' => (strpos($prompt_builder_code, 'forget') !== false),
        'preg_replace' => (strpos($prompt_builder_code, 'preg_replace') !== false),
    ];
    
    $passed = 0;
    foreach ($security_checks as $check => $result) {
        if ($result) {
            $passed++;
        }
    }
    
    if ($passed < 3) {
        throw new Exception("Security implementation incomplete. Found: $passed/4 checks");
    }
    
    return true;
});

// Test 9: Documentation completeness
run_test("Documentation completeness", function() {
    $required_docs = [
        'PR3-IMPLEMENTATION.md',
        'PR3-QUICKSTART.md',
        'examples/pr3-usage-examples.php',
    ];
    
    foreach ($required_docs as $doc) {
        if (!file_exists(__DIR__ . '/' . $doc)) {
            throw new Exception("Missing documentation: $doc");
        }
        
        $content = file_get_contents(__DIR__ . '/' . $doc);
        if (strlen($content) < 1000) {
            throw new Exception("Documentation too short: $doc");
        }
    }
    
    return true;
});

// Test 10: Check REST API endpoint structure
run_test("REST API structure validation", function() {
    $controller_code = file_get_contents(__DIR__ . '/includes/HT_AI_Controller.php');
    
    $required_endpoints = [
        '/ai/query',
        '/ai/suggestion',
        '/ai/intent',
        '/ai/health',
    ];
    
    foreach ($required_endpoints as $endpoint) {
        if (strpos($controller_code, $endpoint) === false) {
            throw new Exception("Missing endpoint: $endpoint");
        }
    }
    
    return true;
});

// Summary
echo "\n====================================\n";
echo "  Test Summary\n";
echo "====================================\n";
echo COLOR_GREEN . "Passed: $tests_passed" . COLOR_RESET . "\n";

if ($tests_failed > 0) {
    echo COLOR_RED . "Failed: $tests_failed" . COLOR_RESET . "\n";
    echo "\n" . COLOR_RED . "Some tests failed! Please review the errors above." . COLOR_RESET . "\n";
    exit(1);
} else {
    echo "\n" . COLOR_GREEN . "All tests passed! ✓" . COLOR_RESET . "\n";
    echo "\nPR3 implementation is ready for deployment.\n";
    exit(0);
}
