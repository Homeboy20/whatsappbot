<?php
/**
 * KwetuPizza Debug Functions
 * 
 * This file contains functions for debugging the KwetuPizza plugin during development.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define debug constants
define('KWETU_DEBUG', true);
define('KWETU_DEBUG_LOG', plugin_dir_path(dirname(__FILE__)) . 'logs/debug.log');
define('KWETU_DEBUG_LOG_MAX_SIZE', 5 * 1024 * 1024); // 5MB

/**
 * Initialize the debug system
 */
function kwetu_debug_init() {
    // Create logs directory if it doesn't exist
    $logs_dir = plugin_dir_path(dirname(__FILE__)) . 'logs';
    if (!file_exists($logs_dir)) {
        // Use native PHP mkdir instead of wp_mkdir_p
        if (!mkdir($logs_dir, 0755, true) && !is_dir($logs_dir)) {
            // Handle directory creation failure
            error_log("Failed to create logs directory: $logs_dir");
            return;
        }
        
        // Add .htaccess to protect logs
        @file_put_contents($logs_dir . '/.htaccess', 'Deny from all');
    }
    
    // Initialize debug log with empty file if it doesn't exist
    if (!file_exists(KWETU_DEBUG_LOG)) {
        @file_put_contents(KWETU_DEBUG_LOG, "# KwetuPizza Debug Log\n# Started: " . date('Y-m-d H:i:s') . "\n\n");
    }
    
    // Register debug endpoint
    add_action('rest_api_init', 'kwetu_register_debug_endpoints');
}

/**
 * Register REST API endpoints for debugging
 */
function kwetu_register_debug_endpoints() {
    register_rest_route('kwetupizza/v1', '/debug', array(
        'methods' => 'GET',
        'callback' => 'kwetu_debug_endpoint',
        'permission_callback' => 'kwetu_debug_permissions_check'
    ));
    
    register_rest_route('kwetupizza/v1', '/debug/log', array(
        'methods' => 'GET',
        'callback' => 'kwetu_debug_log_endpoint',
        'permission_callback' => 'kwetu_debug_permissions_check'
    ));
    
    register_rest_route('kwetupizza/v1', '/debug/data', array(
        'methods' => 'GET',
        'callback' => 'kwetu_debug_data_endpoint',
        'permission_callback' => 'kwetu_debug_permissions_check'
    ));
}

/**
 * Check if user has permission to access debug endpoints
 */
function kwetu_debug_permissions_check() {
    // Only allow in development environments or for admins
    return KWETU_DEBUG && (current_user_can('manage_options') || wp_get_environment_type() === 'development');
}

/**
 * Debug endpoint callback - Shows debug info panel
 */
function kwetu_debug_endpoint($request) {
    $output = '<html><head><title>KwetuPizza Debug</title>';
    $output .= '<style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #2a5885; }
        .debug-panel { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; background: #f9f9f9; }
        .debug-section { margin-bottom: 20px; }
        code { background: #eee; padding: 2px 5px; border-radius: 3px; }
        pre { background: #f1f1f1; padding: 10px; overflow: auto; border-radius: 4px; }
        table { border-collapse: collapse; width: 100%; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style></head><body>';
    
    $output .= '<h1>KwetuPizza Debug Panel</h1>';
    
    // Plugin Information
    $output .= '<div class="debug-panel">';
    $output .= '<h2>Plugin Information</h2>';
    $output .= '<table>';
    $output .= '<tr><th>Plugin Version</th><td>' . get_option('kwetupizza_version', 'Not set') . '</td></tr>';
    $output .= '<tr><th>Database Version</th><td>' . get_option('kwetupizza_db_version', 'Not set') . '</td></tr>';
    $output .= '<tr><th>WordPress Version</th><td>' . get_bloginfo('version') . '</td></tr>';
    $output .= '<tr><th>PHP Version</th><td>' . phpversion() . '</td></tr>';
    $output .= '</table>';
    $output .= '</div>';
    
    // Settings
    $output .= '<div class="debug-panel">';
    $output .= '<h2>Plugin Settings</h2>';
    $output .= '<pre>';
    $settings = array(
        'currency' => get_option('kwetupizza_currency'),
        'location' => get_option('kwetupizza_location'),
        'delivery_area' => get_option('kwetupizza_delivery_area'),
        'whatsapp_enabled' => get_option('kwetupizza_whatsapp_enabled', '0'),
        'flutterwave_enabled' => get_option('kwetupizza_flutterwave_enabled', '0'),
        'nextsms_enabled' => get_option('kwetupizza_nextsms_enabled', '0'),
    );
    $output .= print_r($settings, true);
    $output .= '</pre>';
    $output .= '</div>';
    
    // Database Stats
    $output .= '<div class="debug-panel">';
    $output .= '<h2>Database Statistics</h2>';
    global $wpdb;
    $output .= '<table>';
    $tables = array(
        'users' => $wpdb->prefix . 'kwetupizza_users',
        'products' => $wpdb->prefix . 'kwetupizza_products',
        'orders' => $wpdb->prefix . 'kwetupizza_orders',
        'order_items' => $wpdb->prefix . 'kwetupizza_order_items',
        'transactions' => $wpdb->prefix . 'kwetupizza_transactions',
        'addresses' => $wpdb->prefix . 'kwetupizza_addresses',
        'order_tracking' => $wpdb->prefix . 'kwetupizza_order_tracking',
    );
    
    foreach ($tables as $name => $table) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $output .= '<tr><th>' . ucfirst(str_replace('_', ' ', $name)) . '</th><td>' . ($count !== null ? $count : 'Table not found') . ' rows</td></tr>';
    }
    $output .= '</table>';
    $output .= '</div>';
    
    // Log preview
    $output .= '<div class="debug-panel">';
    $output .= '<h2>Debug Log Preview</h2>';
    if (file_exists(KWETU_DEBUG_LOG)) {
        $log_content = file_get_contents(KWETU_DEBUG_LOG, false, null, -1000); // Last 1000 bytes
        $output .= '<pre>' . esc_html($log_content) . '</pre>';
    } else {
        $output .= '<p>Log file does not exist yet.</p>';
    }
    $output .= '<p><a href="' . rest_url('kwetupizza/v1/debug/log') . '">View full log</a></p>';
    $output .= '</div>';
    
    // Debug Toolbar
    $output .= '<div class="debug-panel">';
    $output .= '<h2>Debug Tools</h2>';
    $output .= '<ul>';
    $output .= '<li><a href="' . rest_url('kwetupizza/v1/debug/data') . '">View All Plugin Data</a></li>';
    $output .= '</ul>';
    $output .= '</div>';
    
    $output .= '</body></html>';
    
    return new WP_REST_Response($output);
}

/**
 * Debug log endpoint callback - Shows full debug log
 */
function kwetu_debug_log_endpoint($request) {
    if (file_exists(KWETU_DEBUG_LOG)) {
        $log_content = file_get_contents(KWETU_DEBUG_LOG);
        $output = '<html><head><title>KwetuPizza Debug Log</title>';
        $output .= '<style>
            body { font-family: monospace; margin: 20px; line-height: 1.4; }
            h1 { color: #2a5885; }
            pre { background: #f1f1f1; padding: 10px; overflow: auto; border-radius: 4px; }
        </style></head><body>';
        
        $output .= '<h1>KwetuPizza Debug Log</h1>';
        $output .= '<pre>' . esc_html($log_content) . '</pre>';
        $output .= '</body></html>';
        
        return new WP_REST_Response($output);
    }
    
    return new WP_Error('log_not_found', 'Debug log file not found', array('status' => 404));
}

/**
 * Debug data endpoint callback - Shows all plugin data
 */
function kwetu_debug_data_endpoint($request) {
    global $wpdb;
    
    $output = '<html><head><title>KwetuPizza Debug Data</title>';
    $output .= '<style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #2a5885; }
        .debug-panel { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; background: #f9f9f9; }
        pre { background: #f1f1f1; padding: 10px; overflow: auto; border-radius: 4px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style></head><body>';
    
    $output .= '<h1>KwetuPizza Plugin Data</h1>';
    
    // Tables to display
    $tables = array(
        'users' => $wpdb->prefix . 'kwetupizza_users',
        'products' => $wpdb->prefix . 'kwetupizza_products',
        'orders' => $wpdb->prefix . 'kwetupizza_orders',
        'order_items' => $wpdb->prefix . 'kwetupizza_order_items',
        'transactions' => $wpdb->prefix . 'kwetupizza_transactions',
        'addresses' => $wpdb->prefix . 'kwetupizza_addresses',
        'order_tracking' => $wpdb->prefix . 'kwetupizza_order_tracking',
    );
    
    foreach ($tables as $name => $table) {
        $output .= '<div class="debug-panel">';
        $output .= '<h2>' . ucfirst(str_replace('_', ' ', $name)) . '</h2>';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if ($table_exists) {
            // Get data
            $data = $wpdb->get_results("SELECT * FROM $table LIMIT 100", ARRAY_A);
            
            if ($data) {
                $output .= '<table>';
                
                // Table headers
                $output .= '<tr>';
                foreach (array_keys($data[0]) as $column) {
                    $output .= '<th>' . esc_html($column) . '</th>';
                }
                $output .= '</tr>';
                
                // Table data
                foreach ($data as $row) {
                    $output .= '<tr>';
                    foreach ($row as $value) {
                        $output .= '<td>' . esc_html($value) . '</td>';
                    }
                    $output .= '</tr>';
                }
                
                $output .= '</table>';
                
                // If there might be more rows
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                if ($count > 100) {
                    $output .= '<p>Showing 100 of ' . $count . ' total rows.</p>';
                }
            } else {
                $output .= '<p>No data in table.</p>';
            }
        } else {
            $output .= '<p>Table does not exist.</p>';
        }
        
        $output .= '</div>';
    }
    
    $output .= '</body></html>';
    
    return new WP_REST_Response($output);
}

/**
 * Log a debug message
 *
 * @param mixed $message Message to log (string or array/object to be printed)
 * @param string $type Log entry type (info, warning, error)
 */
function kwetu_debug_log($message, $type = 'info') {
    if (!KWETU_DEBUG) {
        return;
    }
    
    // Rotate log if too large
    if (file_exists(KWETU_DEBUG_LOG) && filesize(KWETU_DEBUG_LOG) > KWETU_DEBUG_LOG_MAX_SIZE) {
        $backup_log = str_replace('.log', '-' . date('Y-m-d-H-i-s') . '.log', KWETU_DEBUG_LOG);
        rename(KWETU_DEBUG_LOG, $backup_log);
        file_put_contents(KWETU_DEBUG_LOG, "# KwetuPizza Debug Log\n# Rotated: " . date('Y-m-d H:i:s') . "\n\n");
    }
    
    // Format message
    $formatted_message = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($type) . '] ';
    
    if (is_string($message)) {
        $formatted_message .= $message;
    } else {
        $formatted_message .= print_r($message, true);
    }
    
    // Add backtrace for errors
    if ($type === 'error') {
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
    
    // Write to log file
    file_put_contents(KWETU_DEBUG_LOG, $formatted_message . "\n\n", FILE_APPEND);
}

// Initialize debug system
kwetu_debug_init();

/**
 * Debugging helper functions
 */

/**
 * Debug database tables
 * 
 * @return string HTML output of table structure
 */
function kwetu_debug_db_tables() {
    global $wpdb;
    
    $output = '<div class="debug-section">';
    $output .= '<h3>Database Tables</h3>';
    
    $tables = array(
        $wpdb->prefix . 'kwetupizza_users',
        $wpdb->prefix . 'kwetupizza_products',
        $wpdb->prefix . 'kwetupizza_orders',
        $wpdb->prefix . 'kwetupizza_order_items',
        $wpdb->prefix . 'kwetupizza_transactions',
        $wpdb->prefix . 'kwetupizza_addresses',
        $wpdb->prefix . 'kwetupizza_order_tracking',
    );
    
    foreach ($tables as $table) {
        $output .= '<h4>' . $table . '</h4>';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if ($table_exists) {
            // Get table structure
            $structure = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
            
            $output .= '<table>';
            $output .= '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
            
            foreach ($structure as $column) {
                $output .= '<tr>';
                $output .= '<td>' . $column['Field'] . '</td>';
                $output .= '<td>' . $column['Type'] . '</td>';
                $output .= '<td>' . $column['Null'] . '</td>';
                $output .= '<td>' . $column['Key'] . '</td>';
                $output .= '<td>' . $column['Default'] . '</td>';
                $output .= '<td>' . $column['Extra'] . '</td>';
                $output .= '</tr>';
            }
            
            $output .= '</table>';
        } else {
            $output .= '<p>Table does not exist.</p>';
        }
    }
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Check if debug mode is active
 *
 * @return bool
 */
function kwetu_is_debug_active() {
    return defined('KWETU_DEBUG') && KWETU_DEBUG;
} 