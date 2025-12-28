<?php
/**
 * PR3 Structure Test (No WordPress Required)
 * Tests that all PR3 components are properly structured and connected
 * 
 * @package HomayeTabesh
 * @since 1.0.0
 */

// Colors for output
function color_text($text, $color) {
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

echo "\n";
echo "====================================\n";
echo "  PR3 Structure Test\n";
echo "====================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

/**
 * Test 1: All core files exist
 */
echo "Test 1: Core PHP files exist ... ";
$required_files = [
    'includes/HT_Inference_Engine.php',
    'includes/HT_Prompt_Builder_Service.php',
    'includes/HT_Action_Parser.php',
    'includes/HT_AI_Controller.php',
    'includes/HT_Gemini_Client.php',
    'includes/HT_Knowledge_Base.php',
    'includes/HT_Core.php',
];

$all_exist = true;
$missing_files = [];

foreach ($required_files as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $all_exist = false;
        $missing_files[] = $file;
    }
}

if ($all_exist) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - Missing: " . implode(', ', $missing_files) . "\n", 'red');
    $tests_failed++;
}

/**
 * Test 2: Knowledge Base files exist
 */
echo "Test 2: Knowledge Base files exist ... ";
$kb_files = [
    'knowledge-base/pricing.json',
    'knowledge-base/faq.json',
    'knowledge-base/products.json',
    'knowledge-base/personas.json',
    'knowledge-base/responses.json',
];

$all_kb_exist = true;
$missing_kb = [];

foreach ($kb_files as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $all_kb_exist = false;
        $missing_kb[] = $file;
    }
}

if ($all_kb_exist) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - Missing: " . implode(', ', $missing_kb) . "\n", 'red');
    $tests_failed++;
}

/**
 * Test 3: Frontend files exist
 */
echo "Test 3: Frontend JS files exist ... ";
$js_files = [
    'assets/js/ui-executor.js',
    'assets/js/tracker.js',
];

$all_js_exist = true;
$missing_js = [];

foreach ($js_files as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $all_js_exist = false;
        $missing_js[] = $file;
    }
}

if ($all_js_exist) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - Missing: " . implode(', ', $missing_js) . "\n", 'red');
    $tests_failed++;
}

/**
 * Test 4: Check HT_Core initializes inference_engine
 */
echo "Test 4: HT_Core initializes inference_engine ... ";
$core_content = file_get_contents(__DIR__ . '/includes/HT_Core.php');

if (strpos($core_content, 'public ?HT_Inference_Engine $inference_engine') !== false &&
    strpos($core_content, 'new HT_Inference_Engine') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - inference_engine not properly initialized\n", 'red');
    $tests_failed++;
}

/**
 * Test 5: Check HT_Core registers AI Controller REST endpoints
 */
echo "Test 5: HT_Core registers REST endpoints ... ";
if (strpos($core_content, 'public ?HT_AI_Controller $ai_controller') !== false &&
    strpos($core_content, "add_action('rest_api_init'") !== false &&
    strpos($core_content, '$this->ai_controller') !== false &&
    strpos($core_content, 'register_endpoints') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - AI Controller endpoints not registered\n", 'red');
    $tests_failed++;
}

/**
 * Test 6: Check HT_Core enqueues UI executor
 */
echo "Test 6: HT_Core enqueues ui-executor.js ... ";
if (strpos($core_content, 'homaye-tabesh-ui-executor') !== false &&
    strpos($core_content, 'assets/js/ui-executor.js') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - ui-executor not enqueued\n", 'red');
    $tests_failed++;
}

/**
 * Test 7: Check Inference Engine calls generate_decision
 */
echo "Test 7: Inference Engine has generate_decision ... ";
$inference_content = file_get_contents(__DIR__ . '/includes/HT_Inference_Engine.php');

if (strpos($inference_content, 'public function generate_decision') !== false &&
    strpos($inference_content, 'prompt_builder->build_system_instruction') !== false &&
    strpos($inference_content, 'brain->get_json_response') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - generate_decision not properly implemented\n", 'red');
    $tests_failed++;
}

/**
 * Test 8: Check AI Controller handles requests
 */
echo "Test 8: AI Controller has request handlers ... ";
$controller_content = file_get_contents(__DIR__ . '/includes/HT_AI_Controller.php');

$required_endpoints = [
    '/ai/query',
    '/ai/suggestion',
    '/ai/intent',
    '/ai/health',
];

$all_endpoints = true;
$missing_endpoints = [];

foreach ($required_endpoints as $endpoint) {
    if (strpos($controller_content, "'" . $endpoint . "'") === false) {
        $all_endpoints = false;
        $missing_endpoints[] = $endpoint;
    }
}

if ($all_endpoints && 
    strpos($controller_content, 'handle_ai_query') !== false &&
    strpos($controller_content, 'handle_suggestion_request') !== false &&
    strpos($controller_content, 'handle_intent_analysis') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - Missing endpoints or handlers\n", 'red');
    $tests_failed++;
}

/**
 * Test 9: Check Gemini Client has get_json_response
 */
echo "Test 9: Gemini Client has get_json_response ... ";
$gemini_content = file_get_contents(__DIR__ . '/includes/HT_Gemini_Client.php');

if (strpos($gemini_content, 'public function get_json_response') !== false &&
    strpos($gemini_content, 'temperature') !== false &&
    strpos($gemini_content, 'responseSchema') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - get_json_response not properly implemented\n", 'red');
    $tests_failed++;
}

/**
 * Test 10: Check Action Parser handles 9 action types
 */
echo "Test 10: Action Parser supports 9 action types ... ";
$parser_content = file_get_contents(__DIR__ . '/includes/HT_Action_Parser.php');

$required_actions = [
    'highlight_element',
    'show_tooltip',
    'scroll_to',
    'open_modal',
    'update_calculator',
    'suggest_product',
    'show_discount',
    'change_css',
    'redirect',
];

$all_actions = true;
$missing_actions = [];

foreach ($required_actions as $action) {
    if (strpos($parser_content, "'" . $action . "'") === false) {
        $all_actions = false;
        $missing_actions[] = $action;
    }
}

if ($all_actions) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - Missing actions: " . implode(', ', $missing_actions) . "\n", 'red');
    $tests_failed++;
}

/**
 * Test 11: Check Prompt Builder has sanitize_input
 */
echo "Test 11: Prompt Builder sanitizes input ... ";
$prompt_content = file_get_contents(__DIR__ . '/includes/HT_Prompt_Builder_Service.php');

if (strpos($prompt_content, 'public function sanitize_input') !== false ||
    strpos($prompt_content, 'function sanitize_input') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - sanitize_input not found\n", 'red');
    $tests_failed++;
}

/**
 * Test 12: Check Prompt Builder builds system instruction
 */
echo "Test 12: Prompt Builder builds system instruction ... ";

if (strpos($prompt_content, 'public function build_system_instruction') !== false &&
    strpos($prompt_content, 'knowledge_base') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - build_system_instruction not properly implemented\n", 'red');
    $tests_failed++;
}

/**
 * Test 13: Check UI Executor class exists
 */
echo "Test 13: UI Executor has HomaUIExecutor class ... ";
$ui_content = file_get_contents(__DIR__ . '/assets/js/ui-executor.js');

if (strpos($ui_content, 'class HomaUIExecutor') !== false &&
    strpos($ui_content, 'executeAction') !== false &&
    strpos($ui_content, 'window.HomaUIExecutor') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - HomaUIExecutor not properly implemented\n", 'red');
    $tests_failed++;
}

/**
 * Test 14: Check Knowledge Base has required methods
 */
echo "Test 14: Knowledge Base has load_rules method ... ";
$kb_content = file_get_contents(__DIR__ . '/includes/HT_Knowledge_Base.php');

if (strpos($kb_content, 'function load_rules') !== false) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - load_rules method not found\n", 'red');
    $tests_failed++;
}

/**
 * Test 15: Verify JSON files are valid
 */
echo "Test 15: Knowledge Base JSON files are valid ... ";

$all_valid = true;
$invalid_files = [];

foreach ($kb_files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $all_valid = false;
            $invalid_files[] = $file . ' (' . json_last_error_msg() . ')';
        }
    }
}

if ($all_valid) {
    echo color_text("✓ PASSED\n", 'green');
    $tests_passed++;
} else {
    echo color_text("✗ FAILED - Invalid: " . implode(', ', $invalid_files) . "\n", 'red');
    $tests_failed++;
}

// Summary
echo "\n";
echo "====================================\n";
echo "  Test Summary\n";
echo "====================================\n";
echo color_text("Passed: $tests_passed\n", 'green');
if ($tests_failed > 0) {
    echo color_text("Failed: $tests_failed\n", 'red');
}
echo "\n";

if ($tests_failed === 0) {
    echo color_text("All structural tests passed! ✓\n", 'green');
    echo color_text("\nPR3 components are properly structured and connected.\n", 'green');
    echo color_text("All features from PR#3 are in place and wired correctly.\n", 'green');
    exit(0);
} else {
    echo color_text("Some structural tests failed.\n", 'yellow');
    exit(1);
}
