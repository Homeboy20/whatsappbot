<?php
/**
 * KwetuPizza Debug Example
 * 
 * This file demonstrates how to use the debug functions.
 * You can include this in the main plugin file during development or
 * copy/paste the examples into your code as needed.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if debug is active before doing any debug operations
if (kwetu_is_debug_active()) {
    // Example 1: Basic logging
    kwetu_debug_log('This is a basic log message');
    
    // Example 2: Log an array or object
    $example_data = [
        'product_id' => 123,
        'name' => 'Pepperoni Pizza',
        'price' => 12.99,
        'options' => [
            'size' => 'large',
            'crust' => 'thin'
        ]
    ];
    kwetu_debug_log($example_data, 'info');
    
    // Example 3: Log a warning
    kwetu_debug_log('This is a warning message', 'warning');
    
    // Example 4: Log an error with backtrace
    kwetu_debug_log('This is an error message', 'error');
    
    // Example 5: Log a database query result
    global $wpdb;
    $products = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kwetupizza_products LIMIT 3", ARRAY_A);
    kwetu_debug_log("Found " . count($products) . " products");
    kwetu_debug_log($products);
}

/**
 * Example function with debugging
 */
function example_function_with_debug($order_id) {
    // Do something with the order
    
    // Log what's happening
    if (kwetu_is_debug_active()) {
        kwetu_debug_log("Processing order #$order_id");
        
        // More processing
        
        try {
            // Some operation that might fail
            $result = true; // Simulated success
            
            if (!$result) {
                throw new Exception("Failed to process order #$order_id");
            }
            
            kwetu_debug_log("Order #$order_id processed successfully");
        } catch (Exception $e) {
            kwetu_debug_log($e->getMessage(), 'error');
            return false;
        }
    }
    
    return true;
}

/**
 * Example of using the debug functions in hooks
 */
function example_debug_in_hooks() {
    // Add a debug log before processing an order
    add_action('kwetupizza_before_process_order', function($order_id) {
        if (kwetu_is_debug_active()) {
            kwetu_debug_log("About to process order #$order_id");
            
            // You could log the order details
            global $wpdb;
            $order = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d",
                    $order_id
                ),
                ARRAY_A
            );
            
            kwetu_debug_log($order);
        }
    });
    
    // Add a debug log after processing an order
    add_action('kwetupizza_after_process_order', function($order_id, $result) {
        if (kwetu_is_debug_active()) {
            kwetu_debug_log("Finished processing order #$order_id with result: " . ($result ? 'success' : 'failure'));
        }
    }, 10, 2);
}

/**
 * How to access the debug interface
 * 
 * 1. Visit: YOUR_SITE_URL/wp-json/kwetupizza/v1/debug
 * 2. View log: YOUR_SITE_URL/wp-json/kwetupizza/v1/debug/log
 * 3. View data: YOUR_SITE_URL/wp-json/kwetupizza/v1/debug/data
 */ 