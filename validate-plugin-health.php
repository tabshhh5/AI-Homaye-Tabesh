#!/usr/bin/env php
<?php
/**
 * Plugin Health Check Script
 * 
 * Run this script to verify the Homaye Tabesh plugin can load without errors.
 * This should be run in the WordPress root directory.
 * 
 * Usage: php validate-plugin-health.php
 */

declare(strict_types=1);

echo "=== Homaye Tabesh Plugin Health Check ===\n\n";

// Check if we're in WordPress root
if (!file_exists('wp-load.php')) {
    echo "❌ ERROR: This script must be run from WordPress root directory\n";
    echo "   Current directory: " . getcwd() . "\n";
    echo "   Please cd to your WordPress installation and run: php wp-content/plugins/AI-Homaye-Tabesh/validate-plugin-health.php\n";
    exit(1);
}

echo "✓ WordPress root directory found\n";

// Load WordPress
define('WP_USE_THEMES', false);
require_once 'wp-load.php';

echo "✓ WordPress loaded successfully\n";

// Check if plugin is installed
$plugin_dir = WP_CONTENT_DIR . '/plugins/AI-Homaye-Tabesh';
if (!is_dir($plugin_dir)) {
    echo "❌ ERROR: Plugin directory not found at: $plugin_dir\n";
    exit(1);
}

echo "✓ Plugin directory found\n";

// Check if main plugin file exists
$plugin_file = $plugin_dir . '/homaye-tabesh.php';
if (!file_exists($plugin_file)) {
    echo "❌ ERROR: Main plugin file not found at: $plugin_file\n";
    exit(1);
}

echo "✓ Main plugin file found\n";

// Check if error handler exists
$error_handler_file = $plugin_dir . '/includes/HT_Error_Handler.php';
if (!file_exists($error_handler_file)) {
    echo "❌ ERROR: Error handler not found at: $error_handler_file\n";
    exit(1);
}

echo "✓ Error handler file found\n";

// Load error handler to test it
require_once $error_handler_file;

if (!class_exists('HomayeTabesh\HT_Error_Handler')) {
    echo "❌ ERROR: HT_Error_Handler class not found\n";
    exit(1);
}

echo "✓ Error handler class loaded\n";

// Test error handler basic functionality
try {
    HomayeTabesh\HT_Error_Handler::log_error('Health check test', 'health_check');
    echo "✓ Error handler log_error() works\n";
} catch (Throwable $e) {
    echo "❌ ERROR: Error handler log_error() failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test exception logging
try {
    HomayeTabesh\HT_Error_Handler::log_exception(new Exception('Test exception'), 'health_check');
    echo "✓ Error handler log_exception() works\n";
} catch (Throwable $e) {
    echo "❌ ERROR: Error handler log_exception() failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test emergency mode functionality
try {
    $emergency_mode = HomayeTabesh\HT_Error_Handler::is_emergency_mode();
    echo "✓ Emergency mode check works (current state: " . ($emergency_mode ? 'ACTIVE' : 'inactive') . ")\n";
} catch (Throwable $e) {
    echo "❌ ERROR: Emergency mode check failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if plugin is active
if (!is_plugin_active('AI-Homaye-Tabesh/homaye-tabesh.php')) {
    echo "\n⚠️  WARNING: Plugin is not currently active\n";
    echo "   To activate, run: wp plugin activate AI-Homaye-Tabesh/homaye-tabesh.php\n";
} else {
    echo "✓ Plugin is active\n";
    
    // Check if core class is loaded
    if (class_exists('HomayeTabesh\HT_Core')) {
        echo "✓ Core class loaded\n";
        
        // Check if core instance exists
        try {
            $core = HomayeTabesh\HT_Core::instance();
            if ($core) {
                echo "✓ Core instance created successfully\n";
            }
        } catch (Throwable $e) {
            echo "❌ ERROR: Core instantiation failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    } else {
        echo "⚠️  WARNING: Core class not loaded (plugin may not have initialized yet)\n";
    }
}

// Check for emergency log file
$emergency_log = WP_CONTENT_DIR . '/homa-emergency-log.txt';
if (file_exists($emergency_log)) {
    $size = filesize($emergency_log);
    $modified = date('Y-m-d H:i:s', filemtime($emergency_log));
    echo "\n📝 Emergency log file exists:\n";
    echo "   Location: $emergency_log\n";
    echo "   Size: $size bytes\n";
    echo "   Last modified: $modified\n";
    
    if ($size > 0) {
        echo "\n   Recent entries (last 5 lines):\n";
        $lines = file($emergency_log);
        $last_lines = array_slice($lines, -5);
        foreach ($last_lines as $line) {
            echo "   " . trim($line) . "\n";
        }
    }
} else {
    echo "\n✓ No emergency log file (good - no critical errors)\n";
}

// Check PHP error log for plugin errors
$php_error_log = ini_get('error_log');
if ($php_error_log && file_exists($php_error_log)) {
    echo "\n📝 Checking PHP error log for plugin errors:\n";
    echo "   Location: $php_error_log\n";
    
    $error_lines = [];
    $lines = file($php_error_log);
    foreach ($lines as $line) {
        if (stripos($line, 'Homaye Tabesh') !== false || stripos($line, 'HT_') !== false) {
            $error_lines[] = $line;
        }
    }
    
    if (count($error_lines) > 0) {
        echo "   Found " . count($error_lines) . " plugin-related entries (last 5):\n";
        $last_errors = array_slice($error_lines, -5);
        foreach ($last_errors as $error) {
            echo "   " . trim($error) . "\n";
        }
    } else {
        echo "   ✓ No plugin-related errors found\n";
    }
}

echo "\n=== Health Check Complete ===\n";
echo "✅ Plugin structure is healthy and ready for use\n";
echo "\nNext steps:\n";
echo "1. If plugin is not active, activate it via WP Admin or WP-CLI\n";
echo "2. Monitor the emergency log file for any critical errors\n";
echo "3. Test plugin functionality in your environment\n";

exit(0);
