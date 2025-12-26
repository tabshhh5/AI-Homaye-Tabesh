# Critical Recursion Fix - Implementation Summary

## Problem Statement (Persian Translation)
The system was crashing even in isolated environments (without other plugins) due to recursive calls during internal warning logs (like missing WooCommerce). The memory stack was overflowing.

## Root Causes Identified

1. **HT_Error_Handler Recursion**: The error handler used `$is_processing` flag but had a complex flow where `log_exception()` would call `log_error()`, which could trigger more errors.

2. **BlackBox Logger WordPress Dependencies**: The `HT_BlackBox_Logger` was calling WordPress functions like:
   - `get_current_user_id()` - could trigger authentication hooks
   - `wp_json_encode()` - could trigger filters
   - `get_bloginfo()`, `get_option()`, `wp_get_theme()` - could trigger database errors
   - These functions could fail during early initialization or when dependencies are missing

3. **Error Propagation**: When BlackBox logger encountered an error, it would use `error_log()` which might trigger WordPress error handlers, causing recursive loops.

## Solutions Implemented

### 1. Enhanced Static Lock in HT_Error_Handler

**File**: `includes/HT_Error_Handler.php`

**Changes**:
- Added separate `$is_logging` static flag specifically for recursion prevention
- Changed `log_error()` to check `$is_logging` FIRST before any operations
- Changed `log_exception()` to directly format and log without calling `log_error()`
- Used `finally` blocks to ensure locks are always released
- Simplified error logging to use pure PHP `error_log()` without WordPress hooks

**Key Code**:
```php
private static bool $is_logging = false;

public static function log_error(string $message, string $context = 'general', $data = null): void
{
    // Critical: Check logging lock FIRST before any other operations
    if (self::$is_logging) {
        return;
    }
    
    // Set lock immediately to prevent recursion
    self::$is_logging = true;
    
    try {
        // ... logging code ...
        error_log($log_message);
    } finally {
        // Always release lock
        self::$is_logging = false;
    }
}
```

### 2. Isolated Logging Layer in HT_BlackBox_Logger

**File**: `includes/HT_BlackBox_Logger.php`

**Changes**:
- Replaced `wp_json_encode()` with pure PHP `json_encode()`
- Created `safe_get_user_id()` helper that wraps `get_current_user_id()` with error handling
- Enhanced `capture_environment_state()` to wrap ALL WordPress functions with:
  - `function_exists()` checks
  - Try-catch blocks
  - Fallback to PHP equivalents (e.g., `date()` instead of `current_time()`)
- Added comprehensive try-catch blocks in:
  - `log_ai_transaction()` - wraps entire method
  - `log_error()` - prevents cascading errors
  - `get_user_identifier()` - safe fallback
- Changed all error logging to use pure PHP `error_log()` instead of `HT_Error_Handler`

**Key Code**:
```php
private function safe_get_user_id(): ?int
{
    try {
        if (!function_exists('get_current_user_id')) {
            return null;
        }
        $user_id = get_current_user_id();
        return $user_id ?: null;
    } catch (\Throwable $e) {
        return null;
    }
}

private function capture_environment_state(): array
{
    try {
        return [
            'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : 'unknown',
            'time' => date('Y-m-d H:i:s'), // Use PHP date instead of current_time
            // ... other safe WordPress function calls ...
        ];
    } catch (\Throwable $e) {
        // Return minimal state if WordPress functions fail
        return [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'time' => date('Y-m-d H:i:s'),
            'error' => 'Failed to capture full state: ' . $e->getMessage(),
        ];
    }
}
```

### 3. Verified Dependency Loading

**File**: `homaye-tabesh.php`

**Verified**:
- Plugin initialization already uses `plugins_loaded` hook (line 80, priority 10)
- WooCommerce check uses emergency file logging, not error handler
- HT_Loader uses emergency logging that doesn't rely on WordPress
- All critical paths have try-catch with emergency logging fallback

## Testing Results

Created and ran comprehensive recursion protection tests:

1. ✓ Normal error logging works
2. ✓ Multiple rapid error logs don't crash
3. ✓ Exception logging works correctly
4. ✓ Recursion protection blocks nested calls (only 1 call executes)
5. ✓ Mixed exception and error logging works

**Test output confirmed**: Recursion protection is working correctly!

## Benefits

1. **No More Stack Overflow**: The static lock prevents infinite recursion
2. **Isolated Logging**: Pure PHP functions don't trigger WordPress hooks
3. **Graceful Degradation**: Safe wrappers return fallback values instead of crashing
4. **Early Boot Safety**: All critical code works even before WordPress is fully loaded
5. **Emergency Fallback**: File-based logging ensures errors are captured even when everything else fails

## Backward Compatibility

- ✓ No breaking changes to public APIs
- ✓ All existing error logging calls work the same way
- ✓ BlackBox logger interface unchanged
- ✓ Emergency log files maintain same format
- ✓ WordPress function calls still work when available

## Files Modified

1. `includes/HT_Error_Handler.php` - Enhanced recursion protection
2. `includes/HT_BlackBox_Logger.php` - Isolated logging layer

## Security Considerations

- No new security vulnerabilities introduced
- Emergency logging still respects file permissions
- Sensitive data masking still works
- No credentials or secrets logged

## Performance Impact

- Minimal: Added a few boolean checks (nanoseconds)
- Positive: Prevents expensive stack overflow recovery
- Positive: Faster error handling with pure PHP functions

## Deployment Notes

1. No database changes required
2. No WordPress version requirement changes
3. Works in isolated environments (no WooCommerce, no other plugins)
4. Safe to deploy to production immediately
5. Emergency logs will be in: `wp-content/homa-emergency-log.txt`

## Future Improvements

Consider for future PRs:
1. Add PHPUnit test suite for error handling
2. Monitor emergency log file growth and auto-rotate
3. Add metrics for recursion prevention hits
4. Dashboard widget to show recent errors
