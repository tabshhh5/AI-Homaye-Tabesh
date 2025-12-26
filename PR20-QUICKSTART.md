# Quick Reference: Recursion Fix PR#20

## Problem Solved ✓
Stack overflow crashes when error handler triggers more errors during initialization.

## What Changed

### HT_Error_Handler.php
```php
// Added new static lock
private static bool $is_logging = false;

// Simplified log_error - checks lock first
public static function log_error(...) {
    if (self::$is_logging) return;
    self::$is_logging = true;
    try {
        error_log($log_message);
    } finally {
        self::$is_logging = false;
    }
}

// Simplified log_exception - no nested calls
public static function log_exception(...) {
    if (self::$is_logging) return;
    self::$is_logging = true;
    try {
        error_log('[Homaye Tabesh - ' . $context . '] ' . $log_message);
    } finally {
        self::$is_logging = false;
    }
}
```

### HT_BlackBox_Logger.php
```php
// NEW: Safe user ID getter
private function safe_get_user_id(): ?int {
    if (!function_exists('get_current_user_id')) return null;
    return get_current_user_id() ?: null;
}

// UPDATED: Environment capture with safety checks
private function capture_environment_state(): array {
    try {
        return [
            'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : 'unknown',
            'time' => date('Y-m-d H:i:s'), // PHP not WordPress
            // ... all WordPress functions wrapped ...
        ];
    } catch (\Throwable $e) {
        return ['php_version' => PHP_VERSION, 'error' => $e->getMessage()];
    }
}

// UPDATED: Transaction logging with full error isolation
public function log_ai_transaction(array $data): int|false {
    try {
        // Use safe_get_user_id() not get_current_user_id()
        // Use json_encode() not wp_json_encode()
        // ... all operations wrapped ...
    } catch (\Throwable $e) {
        error_log('HT_BlackBox_Logger: Critical error - ' . $e->getMessage());
        return false;
    }
}
```

## Key Principles

1. **Check Lock First**: Always check `$is_logging` before ANY operation
2. **Pure PHP Only**: Use `error_log()`, `json_encode()`, `date()` not WordPress functions
3. **Wrap Everything**: Every WordPress function call needs `function_exists()` check
4. **Never Nest**: Don't call logging methods from other logging methods
5. **Always Release**: Use `finally` blocks to guarantee lock release

## Testing Commands

```bash
# Syntax check
php -l includes/HT_Error_Handler.php
php -l includes/HT_BlackBox_Logger.php

# Manual test (create test file in /tmp)
php /tmp/test_recursion_protection.php
```

## Emergency Logs Location

If WordPress errors fail, check:
```
wp-content/homa-emergency-log.txt
```

## Backward Compatible?

✓ Yes - all existing code works unchanged

## Production Ready?

✓ Yes - tested and safe to deploy
