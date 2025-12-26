<?php
/**
 * Fallback Autoloader
 * 
 * PSR-4 compatible autoloader for when Composer's vendor/autoload.php is not available.
 * This allows the plugin to work for end users who download from GitHub releases
 * without needing to run composer install.
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register PSR-4 autoloader for HomayeTabesh namespace
 */
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'HomayeTabesh\\';
    
    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/';
    
    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    // and append with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
