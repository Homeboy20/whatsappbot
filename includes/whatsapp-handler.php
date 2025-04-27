<?php
/**
 * WhatsApp Handler for KwetuPizza Plugin
 * 
 * Improved implementation for reliable WhatsApp Cloud API webhook registration
 * and message processing.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '/common-functions.php';

// Get business name function for messages
if (!function_exists('kwetupizza_get_business_name')) {
function kwetupizza_get_business_name() {
    return get_option('kwetupizza_business_name', 'KwetuPizza');
    }
}

/**
 * Register WhatsApp webhook routes
 * Separated webhook registration from other routes for clarity
 */
if (!function_exists('kwetupizza_register_webhook_routes')) {
    function kwetupizza_register_webhook_routes() {
        // WhatsApp Webhook Routes - GET for verification
        register_rest_route('kwetupizza/v1', '/whatsapp-webhook', array(
            'methods' => 'GET',
            'callback' => 'kwetupizza_handle_whatsapp_verification',
            'permission_callback' => '__return_true',
        ));

        // WhatsApp Webhook Routes - POST for message processing
        register_rest_route('kwetupizza/v1', '/whatsapp-webhook', array(
            'methods' => 'POST',
            'callback' => 'kwetupizza_handle_whatsapp_messages',
            'permission_callback' => '__return_true',
        ));

        // Flutterwave Webhook Route
        register_rest_route('kwetupizza/v1', '/flutterwave-webhook', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'log_flutterwave_payment_webhook',
            'permission_callback' => '__return_true',
        ));
        
        // Order tracking route
        register_rest_route('kwetupizza/v1', '/order-status/(?P<order_id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'kwetupizza_get_order_status',
            'permission_callback' => '__return_true',
        ));

        // Add Webhook Testing Endpoint
        register_rest_route('kwetupizza/v1', '/test-webhook', array(
            'methods' => WP_REST_Server::ALLMETHODS, // Accept GET, POST, etc.
            'callback' => 'kwetupizza_test_webhook_callback',
            'permission_callback' => '__return_true',
        ));
    }
}
add_action('rest_api_init', 'kwetupizza_register_webhook_routes');

/**
 * WhatsApp webhook verification handler
 * Optimized to strictly follow WhatsApp Cloud API requirements
 * 
 * @param WP_REST_Request $request The request object
 */
if (!function_exists('kwetupizza_handle_whatsapp_verification')) {
    function kwetupizza_handle_whatsapp_verification($request) {
        // Log all request details for debugging
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp webhook verification - Full request details:');
            error_log('Method: ' . $_SERVER['REQUEST_METHOD']);
            error_log('Query String: ' . $_SERVER['QUERY_STRING']);
            error_log('REQUEST parameters: ' . print_r($_REQUEST, true));
            error_log('GET parameters: ' . print_r($_GET, true));
        }
        
        // Get verification parameters from the request
        // Try multiple methods to get the parameters
        $mode = $request->get_param('hub.mode');
        if (empty($mode)) {
            $mode = isset($_GET['hub_mode']) ? $_GET['hub_mode'] : (isset($_GET['hub.mode']) ? $_GET['hub.mode'] : '');
        }
        
        $token = $request->get_param('hub.verify_token');
        if (empty($token)) {
            $token = isset($_GET['hub_verify_token']) ? $_GET['hub_verify_token'] : (isset($_GET['hub.verify_token']) ? $_GET['hub.verify_token'] : '');
        }
        
        $challenge = $request->get_param('hub.challenge');
        if (empty($challenge)) {
            $challenge = isset($_GET['hub_challenge']) ? $_GET['hub_challenge'] : (isset($_GET['hub.challenge']) ? $_GET['hub.challenge'] : '');
        }
        
        // Retrieve stored token
        $verify_token = get_option('kwetupizza_whatsapp_verify_token');
        
        // Log verification attempt details
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp webhook verification attempt:');
            error_log('hub.mode: ' . sanitize_text_field($mode ?: ''));
            error_log('hub.challenge: ' . sanitize_text_field($challenge ?: ''));
            error_log('Token provided: ' . (empty($token) ? 'EMPTY' : 'PROVIDED'));
            error_log('Token stored: ' . (empty($verify_token) ? 'EMPTY' : 'EXISTS'));
        }
        
        // Step 1: Check if all required parameters exist
        if (empty($mode) || empty($token) || empty($challenge)) {
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Verification failed: Missing required parameters');
            }
            return new WP_REST_Response('Verification failed: Missing parameters', 400);
        }
        
        // Step 2: Verify the mode is 'subscribe'
        if ($mode !== 'subscribe') {
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Verification failed: Invalid mode "' . sanitize_text_field($mode) . '"');
            }
            return new WP_REST_Response('Verification failed: Invalid mode', 400);
        }
        
        // Step 3: Verify the token matches
        if ($verify_token !== $token) {
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Verification failed: Token mismatch');
            }
            return new WP_REST_Response('Verification failed: Token mismatch', 403);
        }
        
        // Log success
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('Webhook verification successful! Returning challenge.');
        }
        
        // CRITICAL: Return a plain text response with the challenge - NOT WordPress JSON
        // This must be a direct output without modification by WordPress
        status_header(200);
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }
}

/**
 * Handle WhatsApp messages coming from the webhook
 * 
 * @param WP_REST_Request $request The webhook request
 * @return WP_REST_Response The response to send back
 */
function kwetupizza_handle_whatsapp_messages($request) {
    // Get the JSON data
    $json_data = $request->get_json_params();
    
    // Get webhook information and log it
    if (get_option('kwetupizza_enable_logging', false)) {
        error_log('WhatsApp Webhook received: ' . json_encode($json_data));
    }
    
    // Save webhook body for debugging
    update_option('kwetupizza_last_webhook_data', json_encode($json_data));
    
    // Process the webhook
    try {
        // Store webhook metadata for reuse
        global $kwetupizza_webhook_metadata;
        $kwetupizza_webhook_metadata = array();
        
        if (isset($json_data['entry'][0]['changes'][0]['value']['metadata'])) {
            $kwetupizza_webhook_metadata['phone_number_id'] = $json_data['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'];
        }
        
        // Check if this is a message or status update
        if (isset($json_data['entry'][0]['changes'][0]['value']['messages'])) {
            // This is a message update
            $message = $json_data['entry'][0]['changes'][0]['value']['messages'][0];
            $from = $message['from'];
            $message_id = $message['id'];
            
            // Process the message based on type
            kwetupizza_process_whatsapp_webhook($json_data);
        } 
        elseif (isset($json_data['entry'][0]['changes'][0]['value']['statuses'])) {
            // This is a status update, process it
            $status = $json_data['entry'][0]['changes'][0]['value']['statuses'][0];
            $status_id = $status['id'];
            $recipient_id = $status['recipient_id'];
            $status_type = $status['status'];
            
            // Log the status update
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log("WhatsApp Status Update: ID: $status_id, To: $recipient_id, Status: $status_type");
            }
            
            // Fire status update action for other plugins to hook into
            do_action('kwetupizza_whatsapp_status_update', $status);
        }
        
        // If the message or status was processed successfully, return success
        return new WP_REST_Response(['success' => true], 200);
    } catch (Exception $e) {
        // Log the error and return a 500 status code
        error_log('Error processing WhatsApp webhook: ' . $e->getMessage());
        return new WP_REST_Response(['error' => $e->getMessage()], 500);
    }
}

/**
 * Process incoming WhatsApp message based on type
 * 
 * @param array $data The message data from webhook
 * @return bool Whether processing was successful
 */
function kwetupizza_process_whatsapp_webhook($data) {
    // Extract values from the webhook data
    if (!isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
        error_log('WhatsApp Webhook: No message found in data');
        return false;
    }
    
    $message = $data['entry'][0]['changes'][0]['value']['messages'][0];
    $from = $message['from'];
    
    // Process message based on type
    switch ($message['type']) {
        case 'text':
            $text = $message['text']['body'];
            do_action('kwetupizza_incoming_whatsapp_message', $from, $text);
            return kwetupizza_process_message($from, $text, 'text');
            
        case 'interactive':
            do_action('kwetupizza_incoming_whatsapp_message', $from, 'Interactive message received');
            return kwetupizza_process_interactive_message($from, $message['interactive']);
            
        case 'image':
            $image_id = $message['image']['id'];
            $caption = isset($message['image']['caption']) ? $message['image']['caption'] : '';
            do_action('kwetupizza_incoming_whatsapp_message', $from, 'Image received: ' . $caption);
            return kwetupizza_process_message($from, $caption, 'image', $image_id);
            
        case 'location':
            $latitude = $message['location']['latitude'];
            $longitude = $message['location']['longitude'];
            $location_data = "{$latitude},{$longitude}";
            do_action('kwetupizza_incoming_whatsapp_message', $from, 'Location received');
            return kwetupizza_process_message($from, $location_data, 'location');
            
        default:
            // Log unsupported message type
            error_log("Unsupported WhatsApp message type: {$message['type']}");
            kwetupizza_send_whatsapp_message($from, "Sorry, I don't understand that type of message. Please send text or use the menu options.");
            return false;
    }
}

/**
 * Process interactive messages from WhatsApp
 * 
 * @param string $from The sender's phone number
 * @param array $interactive The interactive message data
 * @return bool Whether processing was successful
 */
function kwetupizza_process_interactive_message($from, $interactive) {
    $context = kwetupizza_get_conversation_context($from);
    
    // Log for debugging
    if (get_option('kwetupizza_enable_logging', false)) {
        error_log('Processing interactive message: ' . json_encode($interactive));
    }
    
    // Process based on interactive type
    switch ($interactive['type']) {
        case 'button_reply':
            $button_id = $interactive['button_reply']['id'];
            $button_text = $interactive['button_reply']['title'];
            
            // Log the user's selection
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log("Button selected: {$button_id} - {$button_text}");
            }
            
            // Process button selection
            return kwetupizza_process_button_selection($from, $button_id, $context);
            
        case 'list_reply':
            $list_id = $interactive['list_reply']['id'];
            $list_title = $interactive['list_reply']['title'];
            
            // Log the user's selection
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log("List item selected: {$list_id} - {$list_title}");
            }
            
            // Process list selection
            return kwetupizza_process_list_selection($from, $list_id, $context);
            
        default:
            // Log unsupported interactive type
            error_log("Unsupported WhatsApp interactive type: {$interactive['type']}");
            kwetupizza_send_whatsapp_message($from, "Sorry, I don't understand that type of interaction. Please try again.");
            return false;
    }
}

/**
 * Process button selections from interactive messages
 * 
 * @param string $from The sender's phone number
 * @param string $button_id The selected button ID
 * @param array $context The user's conversation context
 * @return bool Whether processing was successful
 */
function kwetupizza_process_button_selection($from, $button_id, $context) {
    global $wpdb;
    
    // Check what the user is awaiting
    $awaiting = isset($context['awaiting']) ? $context['awaiting'] : '';
    
    switch ($awaiting) {
        case 'main_menu_selection':
            // Handle main menu buttons
            switch ($button_id) {
                case 'view_menu':
                    return kwetupizza_show_interactive_menu($from);
                    
                case 'track_order':
                    $message = "Please provide your order number to track your order.";
                    $context['awaiting'] = 'order_number';
                    kwetupizza_set_conversation_context($from, $context);
                    return kwetupizza_send_whatsapp_message($from, $message);
                    
                case 'help':
                    return kwetupizza_send_help_message($from);
                    
                default:
                    return kwetupizza_send_default_message($from);
            }
            break;
            
        case 'help_selection':
            // Handle help menu buttons
            return kwetupizza_handle_help_selection($from, $button_id);
            
        case 'payment_confirmation':
            // Handle payment confirmation buttons
            if ($button_id == 'payment_yes') {
                // Confirm payment using existing function
                if (function_exists('kwetupizza_confirm_payment')) {
                    return kwetupizza_confirm_payment($from);
                } else {
                    $message = "Thank you for confirming your payment. We'll process your order shortly.";
                    return kwetupizza_send_whatsapp_message($from, $message);
                }
            } else {
                // Handle payment retry/cancel
                if (function_exists('kwetupizza_retry_payment')) {
                    return kwetupizza_retry_payment($from);
                } else {
                    $message = "Your payment wasn't confirmed. Please try again or contact our support team.";
                    return kwetupizza_send_whatsapp_message($from, $message);
                }
            }
            break;
            
        case 'order_confirmation':
            // Handle order confirmation buttons
            if ($button_id == 'order_confirm') {
                // Finalize order using existing function
                if (function_exists('kwetupizza_finalize_order')) {
                    return kwetupizza_finalize_order($from);
                } else {
                    $message = "Thank you for confirming your order. We'll process it right away!";
                    return kwetupizza_send_whatsapp_message($from, $message);
                }
            } else {
                // Cancel order
                if (function_exists('kwetupizza_cancel_order')) {
                    return kwetupizza_cancel_order($from);
                } else {
                    $message = "Your order has been cancelled. Feel free to start a new order anytime!";
                    $context = array(); // Reset context
                    kwetupizza_set_conversation_context($from, $context);
                    return kwetupizza_send_whatsapp_message($from, $message);
                }
            }
            break;
            
        default:
            // For any other button ID, try to process it
            if (strpos($button_id, 'quantity_') === 0) {
                // Extract quantity value from button ID
                $quantity = substr($button_id, 9);
                
                // Update cart with quantity
                if (isset($context['current_product_id'])) {
                    return kwetupizza_process_quantity_selection($from, "{$context['current_product_id']}_{$quantity}");
                } else {
                    return kwetupizza_send_default_message($from);
                }
            } elseif (strpos($button_id, 'help_') === 0) {
                return kwetupizza_handle_help_selection($from, $button_id);
            } else {
                return kwetupizza_send_default_message($from);
            }
    }
    
    return false;
}

/**
 * Process list selections from interactive messages
 * 
 * @param string $from The sender's phone number
 * @param string $list_id The selected list item ID
 * @param array $context The user's conversation context
 * @return bool Whether processing was successful
 */
function kwetupizza_process_list_selection($from, $list_id, $context) {
    // Check if the user is in the main menu
    $awaiting = isset($context['awaiting']) ? $context['awaiting'] : '';
    
    switch ($awaiting) {
        case 'main_menu_selection':
            // Process main menu selection
            return kwetupizza_handle_main_menu_selection($from, $list_id);
            
        case 'menu_selection':
            // Process food menu selection
            if (strpos($list_id, 'pizza_') === 0) {
                $product_id = substr($list_id, 6);
                return kwetupizza_process_menu_selection($from, $product_id);
            } elseif (strpos($list_id, 'side_') === 0) {
                $product_id = substr($list_id, 5);
                return kwetupizza_process_menu_selection($from, $product_id);
            } elseif (strpos($list_id, 'drink_') === 0) {
                $product_id = substr($list_id, 6);
                return kwetupizza_process_menu_selection($from, $product_id);
            } elseif (strpos($list_id, 'dessert_') === 0) {
                $product_id = substr($list_id, 8);
                return kwetupizza_process_menu_selection($from, $product_id);
            } else {
                return kwetupizza_send_default_message($from);
            }
            break;
            
        default:
            // For any other list ID, try general processing
            if (strpos($list_id, 'help_') === 0) {
                return kwetupizza_handle_help_selection($from, $list_id);
            } else {
                return kwetupizza_send_default_message($from);
            }
    }
    
    return false;
}

/**
 * Verify WhatsApp webhook signature
 * 
 * @param string $signature The signature from the request header
 * @param string $payload The raw request body
 * @return bool Whether the signature is valid
 */
if (!function_exists('kwetupizza_verify_whatsapp_signature')) {
function kwetupizza_verify_whatsapp_signature($signature, $payload) {
        $app_secret = get_option('kwetupizza_whatsapp_app_secret');
        
        // If no app secret configured, skip verification
        if (empty($app_secret) || empty($signature) || empty($payload)) {
            return true;
        }
        
        // Ensure we have strings
        $signature = (string)$signature;
        $payload = (string)$payload;
        
        // Check signature format
        if (strpos($signature, 'sha256=') !== 0) {
        return false;
    }

        // Generate expected signature
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $app_secret);
        
        // Compare using timing-safe comparison
        return hash_equals($expected, $signature);
    }
}

// Handle Flutterwave Payment Webhook
function log_flutterwave_payment_webhook($request) {
    // Get the raw payload
    $payload = file_get_contents('php://input');  
    error_log('Payload: ' . $payload);

    // Retrieve all headers from the request
    $headers = $request->get_headers();
    error_log('Request headers via $request->get_headers(): ' . print_r($headers, true));

    // Attempt to retrieve headers via $_SERVER
    $server_headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $header_name = str_replace('_', '-', substr($key, 5));
            $server_headers[$header_name] = $value;
        }
    }
    error_log('Request headers via $_SERVER: ' . print_r($server_headers, true));

    // Verify the signature
    $verification = kwetupizza_verify_flutterwave_signature($request);
    if (is_wp_error($verification)) {
        error_log('Signature verification failed');
        return new WP_REST_Response('Invalid signature', 403);
    }

    // Decode the webhook data
    $webhook_data = json_decode($payload, true);
    
    // Log decoded data for debugging
    error_log('Decoded webhook data: ' . print_r($webhook_data, true));

    // Ensure that the event is 'charge.completed'
    if (isset($webhook_data['event']) && $webhook_data['event'] === 'charge.completed') {
        $status = $webhook_data['data']['status'] ?? null;
        $transaction_id = $webhook_data['data']['id'] ?? null;
        $tx_ref = $webhook_data['data']['tx_ref'] ?? null;
        $phone_number = $webhook_data['data']['customer']['phone_number'] ?? '';

        if (!$transaction_id || !$tx_ref || !$status) {
            error_log('Missing essential transaction details.');
            return new WP_REST_Response('Invalid data', 400);
        }

        error_log("Processing transaction: $transaction_id with status: $status");

// Handle successful payment
if ($status === 'successful') {
    // Verify and process payment, notify customer and admin
    if (kwetupizza_confirm_payment_and_notify($transaction_id)) {
        kwetupizza_send_payment_success_notification($phone_number, $tx_ref);
        return new WP_REST_Response('Payment processed successfully', 200);
    } else {
        error_log('Payment verification failed during order confirmation.');
        return new WP_REST_Response('Payment verification failed', 400);
    }
}
// Handle failed payment
elseif ($status === 'failed') {
    error_log("Payment failed for transaction: $transaction_id");
    kwetupizza_send_failed_payment_notification($phone_number, $tx_ref);
    kwetupizza_notify_admin_by_order_tx_ref($tx_ref, false);
    return new WP_REST_Response('Payment failed', 400);
}


        // Handle pending or other statuses
        else {
            error_log("Unhandled payment status: $status for transaction: $transaction_id");
            return new WP_REST_Response('Unhandled payment status', 202);
        }
    } else {
        error_log('Webhook event is not charge.completed or event missing.');
        return new WP_REST_Response('Invalid event', 400);
    }
}

// Verify Flutterwave webhook signature
function kwetupizza_verify_flutterwave_signature($request) {
    // Retrieve the secret hash from your settings
    $secret_hash = get_option('kwetupizza_flw_webhook_secret');

    // Retrieve the signature from the headers
    $received_signature = $request->get_header('verif-hash');

    // If not found, try accessing it via $_SERVER
    if (!$received_signature && isset($_SERVER['HTTP_VERIF_HASH'])) {
        $received_signature = $_SERVER['HTTP_VERIF_HASH'];
    }

    // Log the received signature and secret hash
    error_log('Received signature: ' . $received_signature);
    error_log('Secret hash: ' . $secret_hash);

    // Check if we have the signature from Flutterwave
    if (!$received_signature) {
        error_log('Signature header "verif-hash" not found.');
        return new WP_Error('invalid_signature', 'Invalid signature', ['status' => 403]);
    }

    // Compare the received signature with the secret hash
    if (strtolower(trim($received_signature)) !== strtolower(trim($secret_hash))) {
        error_log('Invalid signature in Flutterwave webhook');
        return new WP_Error('invalid_signature', 'Invalid signature', ['status' => 403]);
    }

    // If everything matches, return true
    error_log('Webhook signature verified successfully');
    return true;
}


/**
 * Enhanced mobile money push function with better error handling
 */
if (!function_exists('kwetupizza_generate_mobile_money_push')) {
function kwetupizza_generate_mobile_money_push($from, $cart, $address, $payment_phone, $is_retry = false) {
    global $wpdb;
    
    // Get payment provider from context
    $context = kwetupizza_get_conversation_context($from);
    $payment_provider = isset($context['payment_provider']) ? $context['payment_provider'] : 'mpesa';
    
    // Map provider names to Flutterwave network codes
    $network_map = [
        'mpesa' => 'vodafone',
        'tigopesa' => 'tigo',
        'airtel' => 'airtel',
        'halopesa' => 'halotel'
    ];
    
    $network = isset($network_map[$payment_provider]) ? $network_map[$payment_provider] : 'vodafone';
    
    // Calculate total from cart
    $total = 0;
    foreach ($cart as $item) {
        $total += (isset($item['total']) ? $item['total'] : ($item['price'] * $item['quantity']));
    }
    
    // Add service fee if applicable
    if (isset($context['service_fee']) && $context['service_fee'] > 0) {
        $total += $context['service_fee'];
    }
    
    // Save the order in database
    $order_id = kwetupizza_save_order($from, $context);
    
    if (!$order_id) {
        kwetupizza_send_whatsapp_message($from, "Sorry, we couldn't process your order. Please try again later.");
        return;
    }
    
    // Format phone number for Flutterwave
    $payment_phone = preg_replace('/[^0-9]/', '', $payment_phone);
    // Ensure phone number starts with country code
    if (substr($payment_phone, 0, 1) === '0') {
        $payment_phone = '255' . substr($payment_phone, 1);
    } elseif (substr($payment_phone, 0, 3) !== '255') {
        $payment_phone = '255' . $payment_phone;
    }
    
    // Get customer details
    $customer_details = kwetupizza_get_customer_details($from);
    
    // Now that we have $order_id, define $tx_ref, appending '_retry' and a timestamp if it's a retry attempt
    $tx_ref = 'order_' . $order_id . ($is_retry ? '_retry_' . time() : '');

    // Update the order with the new tx_ref
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $wpdb->update($orders_table, array('tx_ref' => $tx_ref), array('id' => $order_id));

    // Save payment phone and tx_ref in context for retries
    $context['payment_phone'] = $payment_phone;
    $context['tx_ref'] = $tx_ref;
    $context['order_id'] = $order_id;
    kwetupizza_set_conversation_context($from, $context);

    // Prepare the payment request body
    $body = array(
        "tx_ref"        => $tx_ref,
        "amount"        => $total,
        "currency"      => "TZS",
        "email"         => $customer_details['email'] ?? "{$from}@example.com",
        "phone_number"  => $payment_phone,
        "network"       => ucfirst($network),
        "fullname"      => $customer_details['name'] ?? "WhatsApp Customer",
        "meta"          => array("delivery_address" => $address),
    );

    // Log the body being sent to Flutterwave
    if (get_option('kwetupizza_enable_logging', false)) {
        error_log('Payment initiation: Body - ' . print_r($body, true));
    }

    // Send payment request to Flutterwave API
    $response = wp_remote_post('https://api.flutterwave.com/v3/charges?type=mobile_money_tanzania', [
        'headers' => [
            'Authorization' => 'Bearer ' . get_option('kwetupizza_flw_secret_key'),
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($body)
    ]);

    if (is_wp_error($response)) {
        error_log('Flutterwave API Request Error: ' . $response->get_error_message());
        kwetupizza_send_whatsapp_message($from, "Error initiating the payment. Please check your internet connection and try again. For help, contact us at +255 696 110 259.");
        // Update context to wait for retry or cancellation
        $context['awaiting'] = 'payment_retry';
        kwetupizza_set_conversation_context($from, $context);
        return;
    }

    $response_body = wp_remote_retrieve_body($response);
    $result = json_decode($response_body, true);

    // Log the response for debugging
    if (get_option('kwetupizza_enable_logging', false)) {
        error_log('Flutterwave API Response: ' . print_r($result, true));
    }

    if (isset($result['status']) && strtolower($result['status']) == 'success') {
        kwetupizza_send_whatsapp_message($from, "Payment request has been sent to $payment_phone. Please confirm the payment.");
        // Update context to wait for payment confirmation
        $context['awaiting'] = 'payment_confirmation';
        kwetupizza_set_conversation_context($from, $context);
    } else {
        error_log('Payment initiation failed: ' . print_r($result, true));
        kwetupizza_send_whatsapp_message($from, "Error initiating the payment. Please try again or reply 'retry' to attempt payment again. For help, contact us at +255 696 110 259.");
        // Update context to wait for retry or cancellation
        $context['awaiting'] = 'payment_retry';
        kwetupizza_set_conversation_context($from, $context);
    }
}
}

/**
 * Get WhatsApp webhook URL for configuration
 * 
 * @return string The webhook URL
 */
if (!function_exists('kwetupizza_get_whatsapp_webhook_url')) {
    function kwetupizza_get_whatsapp_webhook_url() {
        return esc_url(home_url('/wp-json/kwetupizza/v1/whatsapp-webhook'));
    }
}

/**
 * Generate a random verify token for WhatsApp
 * 
 * @return string The generated token
 */
if (!function_exists('kwetupizza_generate_whatsapp_verify_token')) {
    function kwetupizza_generate_whatsapp_verify_token() {
        $token = wp_generate_password(32, false, false);
        update_option('kwetupizza_whatsapp_verify_token', $token);
        return $token;
    }
}

/**
 * Render the WhatsApp webhook helper in the admin interface
 */
function kwetupizza_render_whatsapp_webhook_helper() {
    // Only run this function in admin context
    if (!is_admin() || !function_exists('wp_create_nonce')) {
        return;
    }
    
    $verify_token = get_option('kwetupizza_whatsapp_verify_token', '');
    $webhook_url = kwetupizza_get_whatsapp_webhook_url();
    $last_test_result = get_option('kwetupizza_last_webhook_test', '');
    ?>
    <div class="kwetupizza-webhook-helper">
        <h3>WhatsApp Webhook Configuration</h3>
        <p>Use the following details to configure your WhatsApp Business API webhook:</p>
        
        <div class="webhook-url-container">
            <label>Webhook URL:</label>
            <div class="url-copy-container">
                <input type="text" id="webhook-url" value="<?php echo esc_url($webhook_url); ?>" readonly />
                <button type="button" class="button copy-webhook-url" onclick="copyWebhookUrl()">Copy</button>
            </div>
        </div>
        
        <div class="verify-token-container">
            <label>Verify Token:</label>
            <div class="token-container">
                <input type="text" id="verify-token" value="<?php echo esc_attr($verify_token); ?>" readonly />
                <button type="button" class="button generate-token" id="generate-token">Generate New Token</button>
            </div>
        </div>
        
        <div class="webhook-test-container">
            <button type="button" class="button test-webhook" id="test-webhook">Test Webhook</button>
            <div class="test-result" id="test-result" style="display: <?php echo empty($last_test_result) ? 'none' : 'block'; ?>">
                <h4>Last Test Result:</h4>
                <pre><?php echo esc_html($last_test_result); ?></pre>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                $('#generate-token').on('click', function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kwetupizza_generate_verify_token',
                            nonce: '<?php echo function_exists('wp_create_nonce') ? wp_create_nonce('kwetupizza_generate_token') : ""; ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#verify-token').val(response.data.token);
                            } else {
                                alert('Error generating token: ' + response.data.message);
                            }
                        }
                    });
                });
                
                $('#test-webhook').on('click', function() {
                    $(this).prop('disabled', true).text('Testing...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kwetupizza_test_whatsapp_webhook',
                            nonce: '<?php echo function_exists('wp_create_nonce') ? wp_create_nonce('kwetupizza_test_webhook') : ""; ?>'
                        },
                        success: function(response) {
                            $('#test-webhook').prop('disabled', false).text('Test Webhook');
                            
                            if (response.success) {
                                $('#test-result').show().find('pre').html(response.data.result);
                            } else {
                                alert('Error testing webhook: ' + response.data.message);
                            }
                        }
                    });
                });
            });
            
            function copyWebhookUrl() {
                var urlField = document.getElementById('webhook-url');
                urlField.select();
                document.execCommand('copy');
                
                var copyButton = document.querySelector('.copy-webhook-url');
                var originalText = copyButton.textContent;
                copyButton.textContent = 'Copied!';
                
                setTimeout(function() {
                    copyButton.textContent = originalText;
                }, 2000);
            }
        </script>
    </div>
    <?php
}

/**
 * AJAX handler for generating WhatsApp verification token
 */
if (!function_exists('kwetupizza_generate_verify_token_ajax')) {
    function kwetupizza_generate_verify_token_ajax() {
        // Security check
        check_ajax_referer('kwetu_pizza_whatsapp_token_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $token = kwetupizza_generate_whatsapp_verify_token();
        wp_send_json_success(['token' => $token]);
    }
}
add_action('wp_ajax_generate_whatsapp_verify_token', 'kwetupizza_generate_verify_token_ajax');

/**
 * AJAX handler for testing the WhatsApp webhook
 */
if (!function_exists('kwetupizza_test_whatsapp_webhook_ajax')) {
    function kwetupizza_test_whatsapp_webhook_ajax() {
        // Security check
        check_ajax_referer('kwetu_pizza_whatsapp_test_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $webhook_url = kwetupizza_get_whatsapp_webhook_url();
        $verify_token = get_option('kwetupizza_whatsapp_verify_token');
        
        if (empty($verify_token)) {
            wp_send_json_error('Verify token is not configured');
        }
        
        // Build test URL with query parameters
        $test_url = add_query_arg(array(
            'hub.mode' => 'subscribe',
            'hub.verify_token' => $verify_token,
            'hub.challenge' => 'webhook_challenge_test'
        ), $webhook_url);
        
        // Send a test request
        $response = wp_remote_get($test_url, array(
            'timeout' => 15,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            wp_send_json_error("HTTP error $code: $body");
        }
        
        if ($body !== 'webhook_challenge_test') {
            wp_send_json_error("Challenge response mismatch. Got: $body");
        }
        
        wp_send_json_success();
    }
}
add_action('wp_ajax_test_whatsapp_webhook', 'kwetupizza_test_whatsapp_webhook_ajax');

/**
 * Webhook testing endpoint
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
if (!function_exists('kwetupizza_test_webhook_callback')) {
    function kwetupizza_test_webhook_callback($request) {
        $method = $request->get_method();
        $params = $request->get_params();
        $headers = $request->get_headers();
        $body = $request->get_body();
        
        // Create debug response
        $debug_info = array(
            'method' => $method,
            'params' => $params,
            'headers' => $headers,
            'body' => $body,
            'time' => current_time('mysql'),
            'received' => true
        );
        
        // Save last test for admin to review
        update_option('kwetupizza_last_webhook_test', $debug_info);
        
        // Respond success
        return new WP_REST_Response(array(
            'status' => 'success',
            'message' => 'Webhook test received successfully',
            'data' => $debug_info
        ), 200);
    }
}

// Process WhatsApp messages (called via wp-cron)
add_action('kwetupizza_process_whatsapp_message', 'kwetupizza_process_message', 10, 3);

/**
 * Process incoming WhatsApp messages
 * 
 * @param string $from The sender's phone number
 * @param string $message The message content
 * @param string $message_type The message type (text, image, etc.)
 * @return bool Whether the message was processed successfully
 */
function kwetupizza_process_message($from, $message, $message_type = 'text') {
    // Add detailed logging
    if (get_option('kwetupizza_enable_logging', false)) {
        error_log('Processing WhatsApp message: ' . $message . ' from: ' . $from . ' type: ' . $message_type);
    }
    
    try {
        // Check if we need to update the phone_id from webhook metadata
        global $kwetupizza_webhook_metadata;
        if (isset($kwetupizza_webhook_metadata) && isset($kwetupizza_webhook_metadata['phone_number_id'])) {
            update_option('kwetupizza_whatsapp_phone_id', $kwetupizza_webhook_metadata['phone_number_id']);
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Updated WhatsApp phone_id to: ' . $kwetupizza_webhook_metadata['phone_number_id']);
            }
        }
        
        // Clean up the message - lowercase & trim
        $original_message = $message;
        $message = strtolower(trim($message));
        
        // Check for greeting/menu/help commands
        $greeting_patterns = array(
            '/^hi$/i', 
            '/^hello$/i', 
            '/^hey$/i', 
            '/^howdy$/i', 
            '/^start$/i'
        );
        
        $is_greeting = false;
        foreach ($greeting_patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $is_greeting = true;
                break;
            }
        }
        
        // Get the conversation context
        $context = kwetupizza_get_conversation_context($from);
        
        // Check if user has requested a live agent
        if (isset($context['live_agent_requested']) && $context['live_agent_requested']) {
            // If a live agent is handling this conversation, just log the message and don't process it
            if (function_exists('kwetupizza_log_whatsapp_conversation')) {
                kwetupizza_log_whatsapp_conversation(array(
                    'phone' => $from,
                    'message' => $original_message,
                    'direction' => 'incoming',
                    'timestamp' => current_time('mysql')
                ));
            }
            return true;
        }
        
        // Handle special commands
        if ($is_greeting || $message === 'menu' || $message === 'start') {
            // Send new interactive menu
            return kwetupizza_send_main_menu($from);
        } elseif ($message === 'help') {
            // Create support ticket and send help options
            return kwetupizza_send_help_message($from);
        } elseif ($message === 'reset') {
            // Reset the conversation
            kwetupizza_reset_conversation_context($from);
            return kwetupizza_send_main_menu($from);
        } elseif ($message === 'agent' || $message === 'live agent' || $message === 'human') {
            // Direct connection to live agent
            $context['awaiting'] = 'live_agent';
            $context['live_agent_requested'] = true;
            kwetupizza_set_conversation_context($from, $context);
            
            // Create support ticket if none exists
            if (!isset($context['support_ticket_id'])) {
                $user_details = kwetupizza_get_customer_details($from);
                $name = !empty($user_details['name']) ? $user_details['name'] : 'Customer';
                $issue = "Direct request for live agent from {$name}";
                $ticket_id = kwetupizza_create_support_ticket($from, $issue);
                $context['support_ticket_id'] = $ticket_id;
                kwetupizza_set_conversation_context($from, $context);
            }
            
            // Notify admin about live agent request
            kwetupizza_notify_admin_of_live_chat($from, $context['support_ticket_id']);
            
            // Inform user
            $message = "I'm connecting you with a customer service agent. Please wait while I transfer your chat. An agent will respond shortly.";
            return kwetupizza_send_whatsapp_message($from, $message);
        }
        
        // Handle based on context
        if (isset($context['awaiting']) && !empty($context['awaiting'])) {
            switch ($context['awaiting']) {
                case 'help_order_details':
                case 'help_menu_details':
                case 'help_delivery_details':
                case 'help_payment_details':
                case 'help_details':
                    // Update the support ticket with the details
                    if (isset($context['support_ticket_id'])) {
                        global $wpdb;
                        $ticket_id = $context['support_ticket_id'];
                        $current_issue = $wpdb->get_var($wpdb->prepare(
                            "SELECT issue FROM {$wpdb->prefix}kwetupizza_support_tickets WHERE id = %d",
                            $ticket_id
                        ));
                        
                        if ($current_issue) {
                            $updated_issue = $current_issue . "\n\nCustomer's details: " . $original_message;
                            kwetupizza_update_support_ticket_issue($ticket_id, $updated_issue);
                        }
                        
                        // Let the user know their message has been received
                        $reply = "Thank you for providing those details. Our team will review your issue and get back to you soon. If you need to speak with an agent immediately, reply with 'agent'.";
                        kwetupizza_send_whatsapp_message($from, $reply);
                        
                        // Keep the context in help mode
                        $context['awaiting'] = 'further_help';
                        kwetupizza_set_conversation_context($from, $context);
                    } else {
                        // Create a new ticket if somehow we don't have one
                        kwetupizza_send_help_message($from);
                    }
                    return true;
                
                case 'product_selection':
                    // Handle product selection
                    kwetupizza_handle_product_selection($from, $message);
                    break;
                case 'quantity':
                    // Handle quantity
                    kwetupizza_handle_quantity_input($from, $message);
                    break;
                case 'address':
                    // Handle address
                    kwetupizza_handle_address_and_ask_payment_provider($from, $message);
                    break;
                case 'payment_provider':
                    // Handle payment provider selection
                    kwetupizza_handle_payment_provider_response($from, $message);
                    break;
                case 'use_whatsapp_number':
                    // Handle response to using WhatsApp number for payment
                    kwetupizza_handle_use_whatsapp_number_response($from, $message);
                    break;
                case 'payment_phone':
                    // Handle payment phone number input
                    kwetupizza_handle_payment_phone_input($from, $message);
                    break;
                default:
                    // For any other state, try the main menu
                    kwetupizza_send_main_menu($from);
            }
            
            return true;
        }
        
        // Handle general commands
        if (strtolower(trim($message)) === 'menu') {
            kwetupizza_send_full_menu($from);
            return true;
        } else if (strtolower(trim($message)) === 'help') {
            kwetupizza_send_help_message($from);
            return true;
        } else if (strtolower(trim($message)) === 'cart') {
            kwetupizza_send_cart_summary($from, $context['cart']);
            return true;
        } else if (strtolower(trim($message)) === 'checkout' || $message === 'checkout_btn') {
            kwetupizza_handle_add_or_checkout($from, 'checkout');
            return true;
        }
        
        // Send the main menu if nothing else matched
        kwetupizza_send_main_menu($from);
        
        return true;
    } catch (Exception $e) {
        error_log("Exception when processing message: " . $e->getMessage());
        return false;
    }
}

/**
 * Send a WhatsApp message
 * 
 * @param string $to Recipient phone number
 * @param string $message Message content
 * @param string $message_type Message type (text, image, interactive)
 * @param string $media_url Media URL for media messages
 * @param array $buttons Button data for interactive messages
 * @return bool Whether the message was sent successfully
 */
if (!function_exists('kwetupizza_send_whatsapp_message')) {
    function kwetupizza_send_whatsapp_message($to, $message, $message_type = 'text', $media_url = null, $buttons = null) {
        $token = get_option('kwetupizza_whatsapp_token');
        $phone_id = get_option('kwetupizza_whatsapp_phone_id');
        $api_version = get_option('kwetupizza_whatsapp_api_version', 'v15.0');
        
        if (empty($token) || empty($phone_id)) {
            error_log('WhatsApp API credentials missing');
            return false;
        }
        
        // Ensure version has 'v' prefix
        if (strpos($api_version, 'v') !== 0) {
            $api_version = 'v' . $api_version;
        }
        
        // Log API information for debugging
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp API URL: https://graph.facebook.com/' . $api_version . '/' . $phone_id . '/messages');
            error_log('Using Phone ID: ' . $phone_id);
        }
        
        // Prepare request data
        $request_data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ];
        
        // Set message type and content
        if ($message_type === 'text') {
            $request_data['type'] = 'text';
            $request_data['text'] = [
                'preview_url' => false,
                'body' => $message
            ];
        } else if ($message_type === 'image' && !empty($media_url)) {
            $request_data['type'] = 'image';
            $request_data['image'] = [
                'link' => $media_url
            ];
        } else if ($message_type === 'interactive' && !empty($buttons)) {
            $request_data['type'] = 'interactive';
            $request_data['interactive'] = $buttons;
        }
        
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp API Request: ' . json_encode($request_data));
        }
        
        // Try sending the message up to 3 times in case of failure
        $max_attempts = 3;
        $attempt = 1;
        $success = false;
        
        while ($attempt <= $max_attempts && !$success) {
            // Send API request
            $response = wp_remote_post("https://graph.facebook.com/{$api_version}/{$phone_id}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($request_data),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                error_log('WhatsApp API Error (Attempt ' . $attempt . '): ' . $response->get_error_message());
                $attempt++;
                continue;
            }
            
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code !== 200) {
                error_log('WhatsApp API Error (Attempt ' . $attempt . '): ' . print_r($response_body, true));
                $attempt++;
                continue;
            }
            
            // If we got here, the message was sent successfully
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('WhatsApp message sent successfully: ' . print_r($response_body, true));
            }
            
            $success = true;
        }
        
        return $success;
    }
}

/**
 * Get conversation context for a given phone number
 * 
 * @param string $phone The phone number to get context for
 * @return array The conversation context
 */
if (!function_exists('kwetupizza_get_conversation_context')) {
    function kwetupizza_get_conversation_context($phone) {
        global $wpdb;
        
        // Try to get context from user record
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kwetupizza_users WHERE phone = %s",
            $phone
        ));
        
        if (!$user) {
            // Create a new user if they don't exist
            $wpdb->insert(
                $wpdb->prefix . 'kwetupizza_users',
                array(
                    'name' => 'WhatsApp User',
                    'email' => '',
                    'phone' => $phone,
                    'role' => 'customer',
                    'state' => 'greeting'
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
            
            // Return default context for new users
            return array(
                'state' => 'greeting',
                'cart' => array(),
                'last_activity' => time(),
                'awaiting' => '',
                'order_id' => 0
            );
        }
        
        // State is stored directly in the user record
        $state = $user->state ?: 'greeting';
        
        // Try to get additional context from user meta
        $context_json = get_user_meta($user->id, 'whatsapp_context', true);
        if (!empty($context_json)) {
            $context = json_decode($context_json, true);
            if (is_array($context)) {
                $context['state'] = $state; // Make sure state is current
                return $context;
            }
        }
        
        // Return default context if none found
        return array(
            'state' => $state,
            'cart' => array(),
            'last_activity' => time(),
            'awaiting' => '',
            'order_id' => 0
        );
    }
}

/**
 * Set conversation context for a given phone number
 * 
 * @param string $phone The phone number to set context for
 * @param array $context The conversation context to set
 * @return bool Whether the operation was successful
 */
if (!function_exists('kwetupizza_set_conversation_context')) {
    function kwetupizza_set_conversation_context($phone, $context) {
        global $wpdb;

        if (empty($phone) || !is_array($context)) {
            return false;
        }
        
        // Get user ID
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}kwetupizza_users WHERE phone = %s",
            $phone
        ));
        
        if (!$user) {
            // Create user if they don't exist
            $result = $wpdb->insert(
                $wpdb->prefix . 'kwetupizza_users',
                array(
                    'name' => 'WhatsApp User',
                    'email' => '',
                    'phone' => $phone,
                    'role' => 'customer',
                    'state' => isset($context['state']) ? $context['state'] : 'greeting'
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
            
            if (!$result) {
                return false;
            }
            
            $user_id = $wpdb->insert_id;
        } else {
            $user_id = $user->id;
            
            // Update state in user record
            if (isset($context['state'])) {
                $wpdb->update(
                    $wpdb->prefix . 'kwetupizza_users',
                    array('state' => $context['state']),
                    array('id' => $user_id),
                    array('%s'),
                    array('%d')
                );
            }
        }
        
        // Set last activity time
        $context['last_activity'] = time();
        
        // Save context to user meta
        $context_json = json_encode($context);
        return update_user_meta($user_id, 'whatsapp_context', $context_json);
    }
}

/**
 * Reset conversation context for a given phone number
 * 
 * @param string $phone The phone number to reset context for
 * @return bool Whether the operation was successful
 */
if (!function_exists('kwetupizza_reset_conversation_context')) {
    function kwetupizza_reset_conversation_context($phone) {
        global $wpdb;
        
        // Update state to greeting
        $result = $wpdb->update(
            $wpdb->prefix . 'kwetupizza_users',
            array('state' => 'greeting'),
            array('phone' => $phone),
            array('%s'),
            array('%s')
        );
        
        // Get user ID
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}kwetupizza_users WHERE phone = %s",
            $phone
        ));
        
        if ($user) {
            // Reset context to default
            $default_context = array(
                'state' => 'greeting',
                'cart' => array(),
                'last_activity' => time(),
                'awaiting' => '',
                'order_id' => 0
            );
            
            update_user_meta($user->id, 'whatsapp_context', json_encode($default_context));
        }
        
        return true;
    }
}

// Keep existing functions
function kwetupizza_get_order_status($request) {
    // This should be implemented as per your order tracking needs
    $order_id = $request->get_param('order_id');
    
    if (empty($order_id)) {
        return new WP_REST_Response(['error' => 'Order ID is required'], 400);
    }
    
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        return new WP_REST_Response(['error' => 'Order not found'], 404);
    }
    
    return new WP_REST_Response([
        'order_id' => $order_id,
        'status' => $order->status,
        'created_at' => $order->created_at,
        'updated_at' => $order->updated_at
    ], 200);
}

// Only run this in admin area and after WordPress is fully loaded
add_action('admin_menu', 'kwetupizza_render_whatsapp_webhook_helper');


