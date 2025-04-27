# KwetuPizza Plugin Logging System

This document explains how to use the logging system in the KwetuPizza plugin.

## Overview

The plugin includes a comprehensive logging system that captures:
- General informational messages
- Warnings and errors
- Plugin events
- API interactions
- Database operations
- WhatsApp messaging activity
- Payment processing

All logs are stored in a central `debug.txt` file in the plugin's root directory.

## Accessing Logs

### Admin Interface

The plugin provides a Log Viewer in the WordPress admin area:

1. Go to **KwetuPizza > Log Viewer** in the WordPress admin menu
2. Use the filters to view specific log levels or limit the number of entries shown
3. Click "Clear Logs" to reset the log file if it becomes too large

### Direct File Access

The log file is located at:
```
/wp-content/plugins/kwetu-pizza-plugin-with-notification/debug.txt
```

## Using the Logging Functions

### Basic Logging

```php
// Log informational messages
kwetu_log_info('Something happened');

// Log warnings
kwetu_log_warning('Something might be wrong');

// Log errors
kwetu_log_error('Something went wrong');

// Log debug information
kwetu_log_debug('Debug information');
```

### Logging with Context

All logging functions accept a second parameter for additional context:

```php
kwetu_log_info('User registered', [
    'user_id' => 123,
    'email' => 'user@example.com'
]);
```

### Specialized Logging Functions

The plugin provides specialized logging functions for common operations:

```php
// Log database operations
kwetu_log_db_operation('insert', 'wp_kwetupizza_orders', $data, $result);

// Log WhatsApp operations
kwetu_log_whatsapp('send', $phone_number, $message_data, $result);

// Log payment operations
kwetu_log_payment('process', $payment_id, $payment_data, $result);

// Log user actions
kwetu_log_user_action('create_order', $user_id, $order_data);

// Log API requests
kwetu_log_api_request('/endpoint', 'POST', $request_data, $response_data);

// Log general plugin events
kwetu_log_event('order_created', 'Order created successfully', $order_data);
```

## Best Practices

1. **Always log important operations** - Orders, payments, and customer interactions should always be logged
2. **Include context data** - Add relevant data to provide context for the log entry
3. **Use appropriate log levels** - Use INFO for normal events, WARNING for potential issues, and ERROR for failures
4. **Log start and end of critical processes** - For important operations, log when they start and complete
5. **Include IDs in log messages** - Always include relevant IDs (order ID, user ID, etc.) in log messages

## Error Handling with Logging

Combine logging with error handling for robust code:

```php
try {
    // Attempt some operation
    $result = some_operation();
    
    if (!$result) {
        kwetu_log_warning('Operation returned false', [
            'context' => $context
        ]);
        return false;
    }
    
    kwetu_log_info('Operation completed successfully');
    return true;
} catch (Exception $e) {
    kwetu_log_error('Exception in operation: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    return false;
}
```

## Examples

See `includes/logging-examples.php` for detailed examples of how to use the logging system in various scenarios.

## Log Rotation

The logging system automatically rotates log files when they reach 5MB in size. Old log files are preserved with timestamps in the filename. 