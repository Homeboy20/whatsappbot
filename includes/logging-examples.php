<?php
/**
 * KwetuPizza Logging Examples
 * 
 * Examples of how to use the logging system throughout the plugin.
 * This file is for reference only and is not included in the main plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * EXAMPLE 1: Logging general information
 */
function kwetu_example_log_info() {
    // Basic info logging
    kwetu_log_info('This is a simple info message');
    
    // Info with context data
    kwetu_log_info('User logged in', [
        'user_id' => 123,
        'username' => 'pizza_lover',
        'login_time' => date('Y-m-d H:i:s')
    ]);
}

/**
 * EXAMPLE 2: Logging errors
 */
function kwetu_example_log_errors() {
    try {
        // Some code that might fail
        $result = json_decode('invalid json', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON parsing error: ' . json_last_error_msg());
        }
    } catch (Exception $e) {
        // Log the error
        kwetu_log_error($e->getMessage(), [
            'source' => 'JSON processing',
            'data' => 'invalid json'
        ]);
    }
}

/**
 * EXAMPLE 3: Logging database operations
 */
function kwetu_example_db_operations() {
    global $wpdb;
    
    // Example data
    $order_data = [
        'customer_name' => 'John Doe',
        'customer_phone' => '1234567890',
        'total' => 29.99
    ];
    
    // Log before operation
    kwetu_log_db_operation('insert', $wpdb->prefix . 'kwetupizza_orders', $order_data);
    
    // Perform the operation
    $result = $wpdb->insert(
        $wpdb->prefix . 'kwetupizza_orders',
        $order_data
    );
    
    // Log the result
    kwetu_log_db_operation('insert', $wpdb->prefix . 'kwetupizza_orders', $order_data, [
        'success' => ($result !== false),
        'insert_id' => $wpdb->insert_id,
        'error' => $wpdb->last_error
    ]);
}

/**
 * EXAMPLE 4: Logging WhatsApp operations
 */
function kwetu_example_whatsapp_logs() {
    $phone = '1234567890';
    $message = 'Your order #123 has been confirmed';
    
    // Log the attempt
    kwetu_log_whatsapp('send', $phone, [
        'message' => $message
    ]);
    
    // Perform the operation
    $result = send_whatsapp_message($phone, $message); // Example function
    
    // Log the result
    kwetu_log_whatsapp('send', $phone, [
        'message' => $message
    ], $result);
}

/**
 * EXAMPLE 5: Logging payment events
 */
function kwetu_example_payment_logs() {
    $payment_id = 'TX12345';
    $payment_data = [
        'amount' => 29.99,
        'currency' => 'TZS',
        'method' => 'Flutterwave'
    ];
    
    // Log payment creation
    kwetu_log_payment('create', $payment_id, $payment_data);
    
    // Process payment
    $result = process_payment($payment_id); // Example function
    
    // Log payment processing
    kwetu_log_payment('process', $payment_id, $payment_data, $result);
}

/**
 * EXAMPLE 6: Logging user actions
 */
function kwetu_example_user_actions() {
    $user_id = 123;
    
    // Log user login
    kwetu_log_user_action('login', $user_id, [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
    
    // Log order creation
    kwetu_log_user_action('create_order', $user_id, [
        'order_id' => 456,
        'total' => 29.99
    ]);
}

/**
 * EXAMPLE 7: Logging API requests
 */
function kwetu_example_api_logs() {
    $endpoint = '/wp-json/kwetupizza/v1/orders';
    $method = 'POST';
    $request_data = [
        'customer_name' => 'John Doe',
        'items' => [
            ['product_id' => 1, 'quantity' => 2]
        ]
    ];
    
    // Log API request
    kwetu_log_api_request($endpoint, $method, $request_data);
    
    // Process API request
    $response = process_api_request(); // Example function
    
    // Log API response
    kwetu_log_api_request($endpoint, $method, $request_data, $response);
}

/**
 * EXAMPLE 8: Using the logs in hooks and actions
 */
function kwetu_example_hooks_logging() {
    // Hook into order creation
    add_action('kwetupizza_order_created', function($order_id, $order_data) {
        kwetu_log_event('order_created', "Order #$order_id created", $order_data);
    }, 10, 2);
    
    // Hook into payment completion
    add_action('kwetupizza_payment_complete', function($payment_id, $payment_data) {
        kwetu_log_payment('complete', $payment_id, $payment_data);
    }, 10, 2);
    
    // Log plugin activation
    register_activation_hook(__FILE__, function() {
        kwetu_log_event('plugin_activation', 'Plugin activated', [
            'version' => '1.3',
            'time' => current_time('mysql')
        ]);
    });
}

/**
 * EXAMPLE 9: Logging for debugging
 */
function kwetu_example_debug_logs() {
    // Log variable values for debugging
    $cart = ['item1', 'item2']; 
    kwetu_log_debug('Current cart contents', $cart);
    
    // Log function execution
    kwetu_log_debug('Starting checkout process');
    // ... checkout code ...
    kwetu_log_debug('Checkout process completed');
}

/**
 * EXAMPLE 10: Using in a try-catch block
 */
function kwetu_example_try_catch() {
    try {
        kwetu_log_debug('Attempting risky operation');
        
        // Risky operation here
        $result = risky_operation(); // Example function
        
        kwetu_log_debug('Risky operation completed', ['result' => $result]);
    } catch (Exception $e) {
        kwetu_log_error('Error in risky operation: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

/**
 * INTEGRATING WITH EXISTING CODE EXAMPLES
 */

// Example: Order processing function with logging
function kwetu_process_order_with_logging($order_id) {
    kwetu_log_info("Processing order #$order_id");
    
    // Get order data
    global $wpdb;
    $order_table = $wpdb->prefix . 'kwetupizza_orders';
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $order_table WHERE id = %d", $order_id), ARRAY_A);
    
    if (!$order) {
        kwetu_log_error("Order #$order_id not found");
        return false;
    }
    
    try {
        // Update order status
        $wpdb->update(
            $order_table,
            ['status' => 'processing'],
            ['id' => $order_id]
        );
        
        if ($wpdb->last_error) {
            kwetu_log_error("Database error when updating order #$order_id", [
                'error' => $wpdb->last_error
            ]);
            return false;
        }
        
        // Send notification to customer
        $customer_phone = $order['customer_phone'];
        $message = "Your order #$order_id is now being processed.";
        
        $notification_sent = send_notification($customer_phone, $message); // Example function
        
        if (!$notification_sent) {
            kwetu_log_warning("Failed to send notification for order #$order_id", [
                'phone' => $customer_phone
            ]);
        } else {
            kwetu_log_info("Notification sent for order #$order_id", [
                'phone' => $customer_phone
            ]);
        }
        
        kwetu_log_event('order_processed', "Order #$order_id processed successfully", $order);
        return true;
    } catch (Exception $e) {
        kwetu_log_error("Exception when processing order #$order_id: " . $e->getMessage());
        return false;
    }
}

// Example: WhatsApp handler with logging
function kwetu_handle_whatsapp_message_with_logging($from, $message) {
    kwetu_log_whatsapp('receive', $from, ['message' => $message]);
    
    try {
        // Process the message
        $response = process_whatsapp_message($from, $message); // Example function
        
        // Send response
        $sent = send_whatsapp_response($from, $response); // Example function
        
        kwetu_log_whatsapp('send', $from, [
            'response' => $response,
            'success' => $sent
        ]);
        
        return true;
    } catch (Exception $e) {
        kwetu_log_error("WhatsApp processing error for $from: " . $e->getMessage());
        return false;
    }
} 