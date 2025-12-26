# Error Handling and Logging System

## Overview

The Homaye Tabesh plugin now includes comprehensive error handling and logging to prevent site-wide failures and provide clear diagnostic information when issues occur.

## Key Features

### 1. Centralized Error Handler (`HT_Error_Handler`)

A new utility class that provides:
- Structured error logging to WordPress debug.log
- Exception logging with full stack traces
- Safe execution wrappers to prevent cascade failures
- WordPress debugging compatibility checks

### 2. Protected Initialization

All plugin initialization points are now wrapped in try/catch blocks:
- **Main plugin file** (`homaye-tabesh.php`)
  - Autoloader loading
  - Plugin initialization on `plugins_loaded` hook
  - Activation hook
  - Deactivation hook

- **Core class** (`HT_Core`)
  - Constructor
  - Service initialization (`init_services`)
  - Hook registration (`register_hooks`)
  - Individual service instantiation

- **Activator** (`HT_Activator`)
  - Database table creation
  - Option initialization

### 3. Graceful Degradation

When a service fails to initialize:
- The error is logged with full context
- The service is set to `null` instead of crashing
- Other services continue to initialize normally
- Admin notices inform users of issues in debug mode

## Usage

### Logging Errors

```php
// Simple error message
\HomayeTabesh\HT_Error_Handler::log_error('Something went wrong', 'context');

// Log exception with stack trace
try {
    // Risky operation
} catch (\Throwable $e) {
    \HomayeTabesh\HT_Error_Handler::log_exception($e, 'my_context');
}

// Safe execution wrapper
$result = \HomayeTabesh\HT_Error_Handler::safe_execute(
    fn() => risky_operation(),
    'operation_context',
    'default_value'
);
```

### Error Log Format

All errors are logged in this format:
```
[Homaye Tabesh - context] Error message | Data: {...}
```

Example:
```
[Homaye Tabesh - init_service_HT_Gemini_Client] Exception in init_service_HT_Gemini_Client: API key not configured in /path/to/file.php:123
```

## WordPress Debug Configuration

To enable full error logging, add these to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Logs will be written to: `wp-content/debug.log`

## Behavior by Scenario

### Scenario 1: Service Initialization Fails
- Error logged to debug.log
- Service property set to `null`
- Other services continue initializing
- Site remains accessible
- Admin notice displayed (if WP_DEBUG enabled)

### Scenario 2: Plugin Activation Fails
- Error logged to debug.log
- `wp_die()` called with error message
- User sees error screen (not white screen)
- Plugin activation is halted
- User can diagnose from logs

### Scenario 3: Hook Registration Fails
- Error logged to debug.log
- Failed hooks are skipped
- Other hooks continue registering
- Site remains accessible

## Testing Error Handling

To test the error handling:

1. Enable WordPress debugging in `wp-config.php`
2. Intentionally cause an error (e.g., remove a required file)
3. Activate or use the plugin
4. Check `wp-content/debug.log` for error messages
5. Verify the site remains accessible

## Benefits

1. **No More White Screens**: Errors don't crash the entire site
2. **Clear Diagnostics**: All errors logged with context and stack traces
3. **Graceful Degradation**: Plugin continues working with available services
4. **Developer-Friendly**: Easy to debug with structured logs
5. **User-Friendly**: Meaningful error messages instead of generic 503 errors

## Migration Notes

- All existing `error_log()` calls remain functional
- New error handling is non-breaking
- Compatible with WordPress debugging standards
- No changes needed to existing service classes

## Future Enhancements

- [ ] Add error notification system for admins
- [ ] Implement error rate limiting
- [ ] Add error statistics dashboard
- [ ] Integrate with external error tracking services
