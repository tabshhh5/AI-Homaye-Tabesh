# Error Handling Visual Guide

## Scenario: Plugin Activation with Error

### Before This PR (Bad Behavior)
```
User clicks "Activate" on plugin
         ↓
Error occurs in HT_Core initialization
         ↓
Fatal error: Uncaught exception
         ↓
WordPress crashes
         ↓
HTTP 503 Service Unavailable
         ↓
NO ERROR LOGS
         ↓
User sees: White screen or generic server error
         ↓
ENTIRE SITE IS DOWN ❌
```

### After This PR (Good Behavior)
```
User clicks "Activate" on plugin
         ↓
Error occurs in HT_Core initialization
         ↓
try/catch catches the exception
         ↓
Error logged to wp-content/debug.log:
  [Homaye Tabesh - core_init] Exception in core_init: 
  Database connection failed in HT_Core.php:320
         ↓
Admin notice displayed (if WP_DEBUG enabled):
  "خطا در راه‌اندازی هسته افزونه: Database connection failed"
         ↓
Execution stops gracefully (return)
         ↓
SITE REMAINS ACCESSIBLE ✓
REST OF WORDPRESS WORKS ✓
USER CAN DIAGNOSE ISSUE ✓
```

## Example Error Log Output

### Service Initialization Failure
```log
[26-Dec-2025 19:00:00 UTC] [Homaye Tabesh - init_service_HT_Gemini_Client] 
Exception in init_service_HT_Gemini_Client: API key not configured 
in /wp-content/plugins/homaye-tabesh/includes/HT_Gemini_Client.php:42 
| Data: {"trace":"#0 HT_Core.php(338): HT_Gemini_Client->__construct()..."}
```

### Database Table Creation Failure
```log
[26-Dec-2025 19:00:01 UTC] [Homaye Tabesh - activation_create_tables] 
Exception in activation_create_tables: Table 'wp_homaye_persona_scores' 
already exists in /wp-content/plugins/homaye-tabesh/includes/HT_Activator.php:61
| Data: {"trace":"#0 HT_Activator.php(24): HT_Activator::create_tables()..."}
```

### Hook Registration Failure
```log
[26-Dec-2025 19:00:02 UTC] [Homaye Tabesh - rest_api_hooks] 
Exception in rest_api_hooks: Invalid REST endpoint 
in /wp-content/plugins/homaye-tabesh/includes/HT_Core.php:582
| Data: {"trace":"#0 HT_Core.php(575): add_action()..."}
```

## Admin Notice Examples

### Debug Mode Enabled (WP_DEBUG = true)
```
┌────────────────────────────────────────────────────────────┐
│ ⚠️ همای تابش: خطا در راه‌اندازی افزونه: API key not      │
│ configured                                                  │
└────────────────────────────────────────────────────────────┘
```

### Production Mode (WP_DEBUG = false)
```
┌────────────────────────────────────────────────────────────┐
│ ⚠️ همای تابش: خطا در راه‌اندازی افزونه                   │
└────────────────────────────────────────────────────────────┘
```

## Graceful Degradation Example

When HT_Gemini_Client fails to initialize:

```php
// Before: Would crash entire plugin
$this->brain = new HT_Gemini_Client(); // Throws exception
$this->eyes = new HT_Telemetry();      // Never reached ❌

// After: Other services continue
$this->brain = $this->safe_init(...);  // Returns null
$this->eyes = $this->safe_init(...);   // Still initializes ✓
$this->memory = $this->safe_init(...); // Still initializes ✓
```

Result:
- `$this->brain` is `null`
- Other services work normally
- Features requiring `$this->brain` fail gracefully
- Rest of plugin functions

## Error Handling Flow Chart

```
┌─────────────────────────────────────┐
│   Plugin Entry Point                │
│   (activation, init, hook)          │
└──────────────┬──────────────────────┘
               │
               ▼
        ┌──────────────┐
        │   try {      │
        │   Execute    │
        │   }          │
        └──────┬───────┘
               │
        ┌──────▼───────┐
        │  Success?    │
        └──┬────────┬──┘
           │        │
       YES │        │ NO
           │        │
           ▼        ▼
    ┌──────────┐ ┌─────────────────┐
    │ Continue │ │ catch (Throwable) │
    │ Normal   │ │                   │
    │ Execution│ │ 1. Log error      │
    │          │ │ 2. Show notice    │
    │          │ │ 3. Return null    │
    └──────────┘ └─────────┬─────────┘
                           │
                           ▼
                    ┌──────────────┐
                    │ Graceful     │
                    │ Degradation  │
                    │              │
                    │ - Site works │
                    │ - User knows │
                    │ - Logs saved │
                    └──────────────┘
```

## Real-World Testing Scenario

To verify error handling works:

1. **Enable WordPress Debugging**
   ```php
   // wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **Introduce Intentional Error**
   ```php
   // In HT_Gemini_Client constructor
   throw new \Exception('Test error - API unavailable');
   ```

3. **Activate Plugin**
   - Plugin activates successfully
   - Site remains accessible
   - Admin notice appears
   - Error logged in `wp-content/debug.log`

4. **Check Logs**
   ```bash
   tail -f wp-content/debug.log
   ```

5. **Verify Site**
   - Homepage loads ✓
   - Admin dashboard works ✓
   - Other plugins work ✓
   - Only AI features affected

6. **Remove Test Error**
   - Remove throw statement
   - Deactivate and reactivate plugin
   - All features work normally

## Benefits Summary

| Aspect | Before | After |
|--------|--------|-------|
| Site crashes | Yes ❌ | No ✓ |
| Error logs | None ❌ | Detailed ✓ |
| User feedback | Generic 503 ❌ | Clear message ✓ |
| Debugging | Impossible ❌ | Easy ✓ |
| Other services | All fail ❌ | Continue ✓ |
| Recovery | Manual ❌ | Automatic ✓ |
