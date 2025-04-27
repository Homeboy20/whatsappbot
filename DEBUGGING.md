# KwetuPizza Plugin Debugging Guide

This document provides information on how to debug the KwetuPizza plugin during development.

## Debug Mode

The plugin includes a comprehensive debugging system that helps track issues and understand how the plugin works. Debug mode is enabled by default in the development environment.

### Debug Features

- **Debug Log**: Records detailed information about plugin operations
- **Debug Panel**: Web interface for viewing plugin status and data
- **API Endpoints**: Access plugin data and logs through REST API

## Accessing Debug Tools

### Debug Panel

The debug panel provides an overview of the plugin's status and data. Access it at:

```
YOUR_SITE_URL/wp-json/kwetupizza/v1/debug
```

### Debug Log

View the full debug log at:

```
YOUR_SITE_URL/wp-json/kwetupizza/v1/debug/log
```

The log file is stored at `wp-content/plugins/kwetu-pizza-plugin-with-notification/logs/debug.log`.

### Plugin Data

View all plugin data (database tables) at:

```
YOUR_SITE_URL/wp-json/kwetupizza/v1/debug/data
```

## Using Debug Functions in Code

### Basic Logging

```php
// Simple message
kwetu_debug_log('This is a debug message');

// Log with severity level (info, warning, error)
kwetu_debug_log('This is a warning', 'warning');
kwetu_debug_log('This is an error', 'error');

// Log arrays or objects
$data = ['key' => 'value', 'items' => [1, 2, 3]];
kwetu_debug_log($data);
```

### Conditional Debugging

Always wrap debug calls with the debug check to avoid overhead in production:

```php
if (kwetu_is_debug_active()) {
    kwetu_debug_log('Debug message here');
    // Additional debug operations
}
```

## Example Code

See `includes/debug-example.php` for detailed examples of how to use the debug functions in your code.

## Security Note

Debug mode should be disabled in production environments. The debug system includes security checks to prevent unauthorized access, but it's best practice to ensure KWETU_DEBUG is set to false in production.

To disable debug mode, edit `includes/kwetu-debug.php` and set:

```php
define('KWETU_DEBUG', false);
```

## Log File Management

The debug log is automatically rotated when it reaches 5MB to prevent disk space issues. Old logs are preserved with timestamps in the filename. 