# Homaye Tabesh Tests

This directory contains unit tests for the Homaye Tabesh plugin.

## Running Tests

### Prerequisites

1. WordPress test suite installed
2. PHPUnit installed
3. Plugin installed in WordPress test environment

### Run All Tests

```bash
phpunit
```

### Run Specific Test File

```bash
phpunit tests/test-ht-error-handler.php
```

### Run Specific Test Method

```bash
phpunit --filter test_log_error_basic
```

## Test Coverage

### HT_Error_Handler Tests

- `test_log_error_basic` - Verifies basic error logging works
- `test_log_exception_basic` - Verifies exception logging works
- `test_multiple_rapid_logs` - Tests rapid sequential logging
- `test_emergency_mode_reset` - Tests emergency mode reset functionality
- `test_safe_execute_success` - Tests safe_execute wrapper with success
- `test_safe_execute_with_exception` - Tests safe_execute wrapper with exceptions
- `test_log_error_with_data_types` - Tests logging with various data types
- `test_admin_notice` - Tests admin notice scheduling
- `test_emergency_mode_prevents_logging` - Tests emergency mode prevents logging
- `test_debug_checks` - Tests debug mode check methods

## Adding New Tests

1. Create a new file in this directory: `test-{component-name}.php`
2. Extend `\WP_UnitTestCase` class
3. Add test methods with prefix `test_`
4. Use assertions to verify behavior

Example:

```php
<?php
namespace HomayeTabesh\Tests;

class My_Component_Test extends \WP_UnitTestCase
{
    public function test_my_feature()
    {
        // Test code here
        $this->assertTrue(true);
    }
}
```

## Test Standards

- All test methods must start with `test_`
- Use descriptive test names that explain what is being tested
- Each test should test one specific behavior
- Clean up after tests in `tearDown()` method
- Use `setUp()` to prepare test environment
- Mock external dependencies when possible
- Never make real API calls or database modifications in unit tests

## Continuous Integration

Tests are automatically run on:
- Every pull request
- Every commit to main branch
- Before releases

Test failures will block merges and deployments.
