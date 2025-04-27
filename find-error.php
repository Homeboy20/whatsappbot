<?php
// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Write directly to a log file we can access
$log_file = 'error-debug.log';
file_put_contents($log_file, "Test started: " . date('Y-m-d H:i:s') . "\n");

try {
    // Test file paths and directory creation
    $plugin_dir = __DIR__;
    file_put_contents($log_file, "Plugin directory: $plugin_dir\n", FILE_APPEND);
    
    $logs_dir = $plugin_dir . '/logs';
    file_put_contents($log_file, "Logs directory: $logs_dir\n", FILE_APPEND);
    
    if (!file_exists($logs_dir)) {
        file_put_contents($log_file, "Logs directory doesn't exist, trying to create it\n", FILE_APPEND);
        $mkdir_result = mkdir($logs_dir);
        file_put_contents($log_file, "mkdir result: " . ($mkdir_result ? "true" : "false") . "\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "Logs directory exists\n", FILE_APPEND);
    }
    
    // Check file permissions
    $debug_file = $plugin_dir . '/includes/kwetu-debug.php';
    if (file_exists($debug_file)) {
        $perms = fileperms($debug_file);
        file_put_contents($log_file, "Debug file exists, permissions: " . decoct($perms & 0777) . "\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "Debug file doesn't exist!\n", FILE_APPEND);
    }
    
    // Test log file creation
    $test_log = $logs_dir . '/test.log';
    $write_test = @file_put_contents($test_log, "Test log entry\n");
    if ($write_test === false) {
        file_put_contents($log_file, "Failed to write to test log file\n", FILE_APPEND);
        file_put_contents($log_file, "Error: " . error_get_last()['message'] . "\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "Successfully wrote to test log file\n", FILE_APPEND);
    }
    
    // Test including the debug file
    file_put_contents($log_file, "Attempting to include kwetu-debug.php\n", FILE_APPEND);
    try {
        include_once($debug_file);
        file_put_contents($log_file, "Successfully included debug file\n", FILE_APPEND);
    } catch (Throwable $e) {
        file_put_contents($log_file, "Error including debug file: " . $e->getMessage() . "\n", FILE_APPEND);
        file_put_contents($log_file, "  at line: " . $e->getLine() . "\n", FILE_APPEND);
    }
    
    // Check if any constants are defined
    file_put_contents($log_file, "KWETU_DEBUG defined: " . (defined('KWETU_DEBUG') ? "yes" : "no") . "\n", FILE_APPEND);
    if (defined('KWETU_DEBUG_LOG')) {
        file_put_contents($log_file, "KWETU_DEBUG_LOG value: " . KWETU_DEBUG_LOG . "\n", FILE_APPEND);
    }
    
    // Test debug log function
    if (function_exists('kwetu_debug_log')) {
        file_put_contents($log_file, "kwetu_debug_log function exists\n", FILE_APPEND);
        try {
            kwetu_debug_log("Test message");
            file_put_contents($log_file, "kwetu_debug_log called successfully\n", FILE_APPEND);
        } catch (Throwable $e) {
            file_put_contents($log_file, "Error calling kwetu_debug_log: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    } else {
        file_put_contents($log_file, "kwetu_debug_log function doesn't exist\n", FILE_APPEND);
    }
    
    // Check if we can create the debug log directly
    if (defined('KWETU_DEBUG_LOG')) {
        $debug_log_path = KWETU_DEBUG_LOG;
        $direct_write = @file_put_contents($debug_log_path, "# Direct test write\n");
        file_put_contents($log_file, "Direct write to debug log: " . ($direct_write !== false ? "success" : "failed") . "\n", FILE_APPEND);
        if ($direct_write === false) {
            file_put_contents($log_file, "Error: " . error_get_last()['message'] . "\n", FILE_APPEND);
        }
    }
    
    file_put_contents($log_file, "Test completed\n", FILE_APPEND);
    
} catch (Throwable $e) {
    file_put_contents($log_file, "Fatal error: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($log_file, "  at " . $e->getFile() . " line " . $e->getLine() . "\n", FILE_APPEND);
} 