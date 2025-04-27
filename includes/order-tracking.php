<?php
// Location: /wp-content/plugins/kwetu-pizza-plugin/includes/order-tracking.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Register custom cron schedules
 */
function kwetupizza_add_cron_schedules($schedules) {
    $schedules['every_15_min'] = array(
        'interval' => 15 * 60, // 15 minutes in seconds
        'display'  => __('Every 15 Minutes')
    );
    return $schedules;
}
add_filter('cron_schedules', 'kwetupizza_add_cron_schedules');

/**
 * Initialize order tracking when order is created
 */
function kwetupizza_init_order_tracking($order_id) {
    // Add initial tracking entry
    kwetupizza_add_tracking_entry($order_id, 'pending', 'Order received and pending confirmation');
    
    // Schedule tracking updates
    if (!wp_next_scheduled('kwetupizza_order_tracking', array($order_id))) {
        wp_schedule_event(time(), 'every_15_min', 'kwetupizza_order_tracking', array($order_id));
    }
    
    // Calculate and set estimated delivery time
    kwetupizza_estimate_delivery_time($order_id);
}
add_action('kwetupizza_order_created', 'kwetupizza_init_order_tracking');

/**
 * Add tracking entry to the database
 */
function kwetupizza_add_tracking_entry($order_id, $status, $description = '', $location = null) {
    global $wpdb;
    $tracking_table = $wpdb->prefix . 'kwetupizza_order_tracking';
    
    $data = array(
        'order_id'        => $order_id,
        'status'          => $status,
        'description'     => $description,
        'location_update' => $location ? json_encode($location) : null,
        'created_at'      => current_time('mysql')
    );
    
    $wpdb->insert($tracking_table, $data);
    
    // Update order status in orders table
    $wpdb->update(
        $wpdb->prefix . 'kwetupizza_orders',
        array('status' => $status),
        array('id' => $order_id)
    );
    
    // Send notifications
    kwetupizza_send_tracking_notifications($order_id, $status, $description, $location);
    
    return $wpdb->insert_id;
}

/**
 * Send tracking notifications to customer and admin
 */
function kwetupizza_send_tracking_notifications($order_id, $status, $description = '', $location = null) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $orders_table WHERE id = %d",
        $order_id
    ));
    
    if (!$order) return;
    
    // Prepare notification message
    $message = kwetupizza_get_order_status_message($status, $order_id, $description, $location);
    
    // Send to customer
    kwetupizza_send_whatsapp_message($order->customer_phone, $message);
    
    // Send to admin
    $admin_whatsapp = get_option('kwetupizza_admin_whatsapp');
    if ($admin_whatsapp) {
        $admin_message = "🔄 Order #$order_id Update:\n";
        $admin_message .= "Status: " . ucfirst($status) . "\n";
        $admin_message .= "Customer: {$order->customer_name}\n";
        if ($description) {
            $admin_message .= "Details: $description\n";
        }
        if ($location) {
            $admin_message .= "Location: " . $location['address'] . "\n";
        }
        
        kwetupizza_send_whatsapp_message($admin_whatsapp, $admin_message);
    }
}

/**
 * Main tracking function called by cron
 */
function kwetupizza_track_order($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $orders_table WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        // Clear the scheduled event if order doesn't exist
        wp_clear_scheduled_hook('kwetupizza_order_tracking', array($order_id));
        return;
    }
    
    // Stop tracking if order is completed or cancelled
    if (in_array($order->status, array('completed', 'delivered', 'cancelled'))) {
        wp_clear_scheduled_hook('kwetupizza_order_tracking', array($order_id));
        return;
    }
    
    // Check if estimated delivery time has passed
    $estimated_delivery = new DateTime($order->estimated_delivery_time);
    $current_time = new DateTime();
    
    if ($current_time > $estimated_delivery && $order->status != 'delivered') {
        // Order is delayed
        kwetupizza_add_tracking_entry(
            $order_id,
            $order->status,
            'Order is taking longer than expected. We apologize for the delay.'
        );
        
        // Notify admin about the delay
        $admin_whatsapp = get_option('kwetupizza_admin_whatsapp');
        if ($admin_whatsapp) {
            $delay_message = "⚠️ DELAY ALERT - Order #$order_id\n";
            $delay_message .= "Order has exceeded estimated delivery time.\n";
            $delay_message .= "Current Status: " . ucfirst($order->status) . "\n";
            $delay_message .= "Customer: {$order->customer_name}\n";
            $delay_message .= "Phone: {$order->customer_phone}";
            
            kwetupizza_send_whatsapp_message($admin_whatsapp, $delay_message);
        }
    }
}
add_action('kwetupizza_order_tracking', 'kwetupizza_track_order');

/**
 * Update order status with location
 */
function kwetupizza_update_order_location($order_id, $latitude, $longitude, $address = '', $eta = null) {
    $location = array(
        'latitude'  => $latitude,
        'longitude' => $longitude,
        'address'   => $address,
        'eta'       => $eta
    );
    
    $description = "Order location updated";
    if ($eta) {
        $description .= ". Estimated arrival: $eta";
    }
    
    return kwetupizza_add_tracking_entry($order_id, 'delivering', $description, $location);
}

/**
 * Mark order as delivered
 */
function kwetupizza_mark_order_delivered($order_id, $delivery_notes = '') {
    global $wpdb;
    
    // Update order status
    $wpdb->update(
        $wpdb->prefix . 'kwetupizza_orders',
        array(
            'status' => 'delivered',
            'actual_delivery_time' => current_time('mysql'),
            'delivery_notes' => $delivery_notes
        ),
        array('id' => $order_id)
    );
    
    // Add tracking entry
    kwetupizza_add_tracking_entry($order_id, 'delivered', 'Order has been delivered successfully. ' . $delivery_notes);
    
    // Clear tracking schedule
    wp_clear_scheduled_hook('kwetupizza_order_tracking', array($order_id));
    
    return true;
}

/**
 * Get order tracking history
 */
function kwetupizza_get_tracking_history($order_id) {
    global $wpdb;
    $tracking_table = $wpdb->prefix . 'kwetupizza_order_tracking';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tracking_table WHERE order_id = %d ORDER BY created_at DESC",
        $order_id
    ));
}

/**
 * Cancel order tracking
 */
function kwetupizza_cancel_order_tracking($order_id, $reason = '') {
    global $wpdb;
    
    // Update order status
    $wpdb->update(
        $wpdb->prefix . 'kwetupizza_orders',
        array('status' => 'cancelled'),
        array('id' => $order_id)
    );
    
    // Add tracking entry
    kwetupizza_add_tracking_entry($order_id, 'cancelled', 'Order cancelled. ' . $reason);
    
    // Clear tracking schedule
    wp_clear_scheduled_hook('kwetupizza_order_tracking', array($order_id));
    
    return true;
}

// Clean up tracking schedules on plugin deactivation
function kwetupizza_cleanup_tracking_schedules() {
    global $wpdb;
    
    $active_orders = $wpdb->get_results(
        "SELECT id FROM {$wpdb->prefix}kwetupizza_orders 
         WHERE status NOT IN ('completed', 'delivered', 'cancelled')"
    );
    
    foreach ($active_orders as $order) {
        wp_clear_scheduled_hook('kwetupizza_order_tracking', array($order->id));
    }
}
register_deactivation_hook(KWETUPIZZA_PLUGIN_FILE, 'kwetupizza_cleanup_tracking_schedules'); 