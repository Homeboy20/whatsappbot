<?php
/**
 * KwetuPizza Logger
 * 
 * Comprehensive logging system for both debug information and plugin events/errors.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define logging constants
define('KWETU_LOG_DEBUG', true);
define('KWETU_LOG_FILE', plugin_dir_path(dirname(__FILE__)) . 'debug.txt');
define('KWETU_LOG_ROTATE_SIZE', 5 * 1024 * 1024); // 5MB

// Log levels
define('KWETU_LOG_LEVEL_INFO', 'INFO');
define('KWETU_LOG_LEVEL_WARNING', 'WARNING');
define('KWETU_LOG_LEVEL_ERROR', 'ERROR');
define('KWETU_LOG_LEVEL_DEBUG', 'DEBUG');
define('KWETU_LOG_LEVEL_EVENT', 'EVENT');

/**
 * Initialize the logging system
 */
function kwetu_logger_init() {
    // Create the log file if it doesn't exist
    if (!file_exists(KWETU_LOG_FILE)) {
        @file_put_contents(KWETU_LOG_FILE, "# KwetuPizza Log File\n# Started: " . date('Y-m-d H:i:s') . "\n\n");
    }
    
    // Add shutdown function to capture fatal errors
    register_shutdown_function('kwetu_logger_shutdown_handler');
}

/**
 * Add a log entry
 *
 * @param string $message The message to log
 * @param string $level The log level (use KWETU_LOG_LEVEL_* constants)
 * @param array $context Additional context data to include
 * @return bool Success or failure
 */
function kwetu_log($message, $level = KWETU_LOG_LEVEL_INFO, $context = []) {
    if (!KWETU_LOG_DEBUG) {
        return false;
    }
    
    // Check log file size and rotate if needed
    if (file_exists(KWETU_LOG_FILE) && filesize(KWETU_LOG_FILE) > KWETU_LOG_ROTATE_SIZE) {
        $backup_log = str_replace('.txt', '-' . date('Y-m-d-H-i-s') . '.txt', KWETU_LOG_FILE);
        @rename(KWETU_LOG_FILE, $backup_log);
        @file_put_contents(KWETU_LOG_FILE, "# KwetuPizza Log File\n# Rotated: " . date('Y-m-d H:i:s') . "\n\n");
    }
    
    // Format the log entry
    $timestamp = date('Y-m-d H:i:s');
    $formatted_message = "[$timestamp] [$level] $message";
    
    // Add context data if available
    if (!empty($context)) {
        $formatted_message .= "\nContext: " . json_encode($context, JSON_PRETTY_PRINT);
    }
    
    // Add backtrace for errors
    if ($level === KWETU_LOG_LEVEL_ERROR) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $formatted_message .= "\nTrace: ";
        foreach ($trace as $i => $step) {
            if ($i === 0) continue; // Skip this function call
            $formatted_message .= "\n  " . ($i) . ". ";
            $formatted_message .= (isset($step['class']) ? $step['class'] . '::' : '');
            $formatted_message .= $step['function'] . '()';
            $formatted_message .= (isset($step['file']) ? ' in ' . $step['file'] . ':' . $step['line'] : '');
        }
    }
    
    // Add separator for readability
    $formatted_message .= "\n" . str_repeat('-', 80) . "\n";
    
    // Write to log file
    $result = @file_put_contents(KWETU_LOG_FILE, $formatted_message, FILE_APPEND);
    
    return ($result !== false);
}

/**
 * Log informational message
 *
 * @param string $message The message to log
 * @param array $context Additional context data
 */
function kwetu_log_info($message, $context = []) {
    kwetu_log($message, KWETU_LOG_LEVEL_INFO, $context);
}

/**
 * Log warning message
 *
 * @param string $message The message to log
 * @param array $context Additional context data
 */
function kwetu_log_warning($message, $context = []) {
    kwetu_log($message, KWETU_LOG_LEVEL_WARNING, $context);
}

/**
 * Log error message
 *
 * @param string $message The message to log
 * @param array $context Additional context data
 */
function kwetu_log_error($message, $context = []) {
    kwetu_log($message, KWETU_LOG_LEVEL_ERROR, $context);
}

/**
 * Log debug message
 *
 * @param string $message The message to log
 * @param array $context Additional context data
 */
function kwetu_log_debug($message, $context = []) {
    kwetu_log($message, KWETU_LOG_LEVEL_DEBUG, $context);
}

/**
 * Log plugin event
 *
 * @param string $event_type The type of event
 * @param string $message Event description
 * @param array $data Event data
 */
function kwetu_log_event($event_type, $message, $data = []) {
    $context = [
        'event_type' => $event_type,
        'data' => $data
    ];
    kwetu_log($message, KWETU_LOG_LEVEL_EVENT, $context);
}

/**
 * Shutdown handler to catch fatal errors
 */
function kwetu_logger_shutdown_handler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
        $message = 'Fatal Error: ' . $error['message'];
        $context = [
            'file' => $error['file'],
            'line' => $error['line'],
            'error_type' => $error['type']
        ];
        kwetu_log($message, KWETU_LOG_LEVEL_ERROR, $context);
    }
}

/**
 * Log a database operation
 *
 * @param string $operation The operation (insert, update, select, delete)
 * @param string $table The table name
 * @param array $data Operation data
 * @param string $result Operation result
 */
function kwetu_log_db_operation($operation, $table, $data = [], $result = null) {
    $message = "Database $operation on table: $table";
    $context = [
        'data' => $data,
        'result' => $result
    ];
    kwetu_log($message, KWETU_LOG_LEVEL_DEBUG, $context);
}

/**
 * Log a WhatsApp API operation
 *
 * @param string $operation The operation (send, receive)
 * @param string $phone The phone number
 * @param array $data Operation data
 * @param string $result Operation result
 */
function kwetu_log_whatsapp($operation, $phone, $data = [], $result = null) {
    $message = "WhatsApp $operation for phone: $phone";
    $context = [
        'data' => $data,
        'result' => $result
    ];
    kwetu_log($message, KWETU_LOG_LEVEL_DEBUG, $context);
}

/**
 * Log a payment operation
 *
 * @param string $operation The operation (create, process)
 * @param string $payment_id The payment ID or reference
 * @param array $data Payment data
 * @param string $result Operation result
 */
function kwetu_log_payment($operation, $payment_id, $data = [], $result = null) {
    $message = "Payment $operation for ID: $payment_id";
    $context = [
        'data' => $data,
        'result' => $result
    ];
    kwetu_log($message, KWETU_LOG_LEVEL_EVENT, $context);
}

/**
 * Log a user action
 *
 * @param string $action The action performed
 * @param int|string $user_id The user ID or phone number
 * @param array $data Action data
 */
function kwetu_log_user_action($action, $user_id, $data = []) {
    $message = "User $user_id performed action: $action";
    kwetu_log($message, KWETU_LOG_LEVEL_EVENT, $data);
}

/**
 * Log a REST API request
 *
 * @param string $endpoint The API endpoint
 * @param string $method The HTTP method
 * @param array $request_data Request data
 * @param array $response_data Response data
 */
function kwetu_log_api_request($endpoint, $method, $request_data = [], $response_data = []) {
    $message = "$method request to endpoint: $endpoint";
    $context = [
        'request' => $request_data,
        'response' => $response_data
    ];
    kwetu_log($message, KWETU_LOG_LEVEL_DEBUG, $context);
}

/**
 * Get the log file content
 *
 * @param int $lines Number of lines to return (0 for all)
 * @return string Log content
 */
function kwetu_get_log_content($lines = 0) {
    if (!file_exists(KWETU_LOG_FILE)) {
        return '';
    }
    
    if ($lines <= 0) {
        return file_get_contents(KWETU_LOG_FILE);
    }
    
    $file = new SplFileObject(KWETU_LOG_FILE, 'r');
    $file->seek(PHP_INT_MAX);
    $total_lines = $file->key();
    
    $start_line = max(0, $total_lines - $lines);
    $log_content = '';
    
    $file->seek($start_line);
    while (!$file->eof()) {
        $log_content .= $file->fgets();
    }
    
    return $log_content;
}

/**
 * Clear the log file
 *
 * @return bool Success or failure
 */
function kwetu_clear_log() {
    return file_put_contents(KWETU_LOG_FILE, "# KwetuPizza Log File\n# Cleared: " . date('Y-m-d H:i:s') . "\n\n") !== false;
}

// Initialize logger system
kwetu_logger_init(); 