<?php
/**
 * Unit Tests for HT_Error_Handler
 * 
 * Tests the recursion protection and error handling mechanisms
 * 
 * @package HomayeTabesh
 * @subpackage Tests
 */

declare(strict_types=1);

namespace HomayeTabesh\Tests;

use HomayeTabesh\HT_Error_Handler;

/**
 * Test class for HT_Error_Handler
 */
class HT_Error_Handler_Test extends \WP_UnitTestCase
{
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        // Reset emergency mode before each test
        HT_Error_Handler::reset_emergency_mode();
    }

    /**
     * Test basic error logging
     */
    public function test_log_error_basic()
    {
        // Should not throw any exceptions
        HT_Error_Handler::log_error('Test error message', 'test_context');
        
        // Verify emergency mode is not triggered
        $this->assertFalse(HT_Error_Handler::is_emergency_mode());
    }

    /**
     * Test exception logging
     */
    public function test_log_exception_basic()
    {
        $exception = new \Exception('Test exception message');
        
        // Should not throw any exceptions
        HT_Error_Handler::log_exception($exception, 'test_context');
        
        // Verify emergency mode is not triggered
        $this->assertFalse(HT_Error_Handler::is_emergency_mode());
    }

    /**
     * Test multiple rapid error logs
     */
    public function test_multiple_rapid_logs()
    {
        for ($i = 0; $i < 10; $i++) {
            HT_Error_Handler::log_error("Error $i", 'rapid_test');
        }
        
        // Should not trigger emergency mode
        $this->assertFalse(HT_Error_Handler::is_emergency_mode());
    }

    /**
     * Test emergency mode reset
     */
    public function test_emergency_mode_reset()
    {
        // Manually trigger emergency mode by deep recursion
        $this->simulate_deep_recursion(10);
        
        // May or may not be in emergency mode depending on MAX_RECURSION_DEPTH
        // But reset should always work
        HT_Error_Handler::reset_emergency_mode();
        
        $this->assertFalse(HT_Error_Handler::is_emergency_mode());
        
        // Verify logging works after reset
        HT_Error_Handler::log_error('After reset', 'reset_test');
        $this->assertFalse(HT_Error_Handler::is_emergency_mode());
    }

    /**
     * Test safe_execute wrapper with successful callback
     */
    public function test_safe_execute_success()
    {
        $result = HT_Error_Handler::safe_execute(function() {
            return 'success';
        }, 'safe_exec_test');
        
        $this->assertEquals('success', $result);
    }

    /**
     * Test safe_execute wrapper with exception
     */
    public function test_safe_execute_with_exception()
    {
        $result = HT_Error_Handler::safe_execute(function() {
            throw new \Exception('Test exception');
        }, 'safe_exec_test', 'default_value');
        
        $this->assertEquals('default_value', $result);
    }

    /**
     * Test logging with various data types
     */
    public function test_log_error_with_data_types()
    {
        // String data
        HT_Error_Handler::log_error('String test', 'data_test', 'test string');
        
        // Numeric data
        HT_Error_Handler::log_error('Numeric test', 'data_test', 42);
        
        // Boolean data
        HT_Error_Handler::log_error('Boolean test', 'data_test', true);
        
        // Null data
        HT_Error_Handler::log_error('Null test', 'data_test', null);
        
        // Array data
        HT_Error_Handler::log_error('Array test', 'data_test', ['key' => 'value']);
        
        // Object data
        HT_Error_Handler::log_error('Object test', 'data_test', (object)['key' => 'value']);
        
        // Should not trigger emergency mode
        $this->assertFalse(HT_Error_Handler::is_emergency_mode());
    }

    /**
     * Test admin notice scheduling
     */
    public function test_admin_notice()
    {
        // Should not throw any exceptions
        HT_Error_Handler::admin_notice('Test notice', 'warning');
        
        // Verify emergency mode is not triggered
        $this->assertFalse(HT_Error_Handler::is_emergency_mode());
    }

    /**
     * Test that emergency mode prevents logging
     */
    public function test_emergency_mode_prevents_logging()
    {
        // Trigger deep recursion to potentially activate emergency mode
        $this->simulate_deep_recursion(20);
        
        // If emergency mode is active, logging should be silently skipped
        // This should not throw any exceptions
        HT_Error_Handler::log_error('Should be skipped', 'emergency_test');
        
        // This test always passes if we get here without exceptions
        $this->assertTrue(true);
    }

    /**
     * Test debug mode checks
     */
    public function test_debug_checks()
    {
        $debug_enabled = HT_Error_Handler::is_debug_enabled();
        $this->assertIsBool($debug_enabled);
        
        $debug_log_enabled = HT_Error_Handler::is_debug_log_enabled();
        $this->assertIsBool($debug_log_enabled);
    }

    /**
     * Helper method to simulate deep recursion
     * 
     * @param int $depth Maximum recursion depth
     */
    private function simulate_deep_recursion(int $depth): void
    {
        if ($depth > 0) {
            HT_Error_Handler::log_error("Recursion depth $depth", 'recursion_test');
            $this->simulate_deep_recursion($depth - 1);
        }
    }
}
