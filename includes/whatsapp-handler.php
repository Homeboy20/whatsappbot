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
 * WhatsApp message handler
 * Processes incoming messages from WhatsApp
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
if (!function_exists('kwetupizza_handle_whatsapp_messages')) {
    function kwetupizza_handle_whatsapp_messages($request) {
        $body = $request->get_body();
        
        // Verify the signature if app secret is configured
        $signature = $request->get_header('x-hub-signature-256');
        if (!empty($signature) && !kwetupizza_verify_whatsapp_signature($signature, $body)) {
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('WhatsApp webhook: Signature verification failed');
            }
            return new WP_REST_Response('Invalid signature', 403);
        }

        // Parse request data
        $webhook_data = json_decode($body, true);

        // Log the request data for debugging
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp webhook payload received: ' . print_r($webhook_data, true));
        }
        
        // Always acknowledge receipt to prevent retries
        if (empty($webhook_data) || !is_array($webhook_data)) {
            return new WP_REST_Response('Received', 200);
        }
        
        // Process immediately for better response time
        if (isset($webhook_data['entry'][0]['changes'][0]['value']['messages'])) {
            // Process synchronously for actual messages
            kwetupizza_process_whatsapp_webhook($webhook_data);
        } else {
            // Use WordPress scheduling for status updates or other non-message events
            wp_schedule_single_event(time(), 'kwetupizza_process_whatsapp_webhook', array($webhook_data));
        }
        
        // Return success response immediately
        return new WP_REST_Response('Received', 200);
    }
}

/**
 * Async webhook processor
 * Handles the webhook data processing in the background
 */
if (!function_exists('kwetupizza_process_whatsapp_webhook')) {
    function kwetupizza_process_whatsapp_webhook($webhook_data) {
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('Processing webhook data asynchronously: ' . print_r($webhook_data, true));
        }
        
        // Extract data from the webhook payload
        if (!isset($webhook_data['entry'][0]['changes'][0]['value'])) {
            error_log('Invalid webhook structure: Missing value data');
            return;
        }
        
        $value = $webhook_data['entry'][0]['changes'][0]['value'];
        
        // Store the phone_number_id for use in replies
        global $kwetupizza_webhook_metadata;
        $kwetupizza_webhook_metadata = array();
        
        if (isset($value['metadata']) && isset($value['metadata']['phone_number_id'])) {
            $kwetupizza_webhook_metadata['phone_number_id'] = $value['metadata']['phone_number_id'];
            
            // Also update the option immediately
            update_option('kwetupizza_whatsapp_phone_id', $value['metadata']['phone_number_id']);
            
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Updated WhatsApp phone_id to: ' . $value['metadata']['phone_number_id']);
            }
        }
        
        // Message processing
        if (isset($value['messages']) && !empty($value['messages'][0])) {
            $message = $value['messages'][0];
            $from = $message['from'];
            $message_type = $message['type'];
            $message_content = '';

            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Extracted message from: ' . $from . ', type: ' . $message_type);
            }

            // Extract message content based on type
            switch ($message_type) {
                case 'text':
                    $message_content = isset($message['text']['body']) ? $message['text']['body'] : '';
                    break;
                    
                case 'interactive':
                    if (isset($message['interactive']['button_reply']['id'])) {
                        $message_content = $message['interactive']['button_reply']['id'];
                    } elseif (isset($message['interactive']['list_reply']['id'])) {
                        $message_content = $message['interactive']['list_reply']['id'];
                    }
                    break;
                    
                case 'location':
                    if (isset($message['location'])) {
                        $latitude = $message['location']['latitude'];
                        $longitude = $message['location']['longitude'];
                        $message_content = "location:$latitude,$longitude";
                    }
                    break;
                    
                default:
                    $message_content = "unsupported:$message_type";
            }

            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Extracted message content: ' . $message_content);
            }

            // Process the message if we have valid data
            if (!empty($from) && !empty($message_content)) {
                // Direct call instead of hook for more immediate response
                kwetupizza_process_message($from, $message_content, $message_type);
                
                if (get_option('kwetupizza_enable_logging', false)) {
                    error_log('Message passed to process_message function');
                }
            } else {
                error_log('Error: Empty from or message_content');
            }
        }
        // Status update processing
        elseif (isset($value['statuses']) && !empty($value['statuses'][0])) {
            $status = $value['statuses'][0];
            do_action('kwetupizza_whatsapp_status_update', $status);
        } else {
            error_log('No messages or statuses found in webhook data');
        }
    }
}
add_action('kwetupizza_process_whatsapp_webhook', 'kwetupizza_process_whatsapp_webhook');

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
 * Render WhatsApp webhook helper in the admin area
 */
if (!function_exists('kwetupizza_render_whatsapp_webhook_helper')) {
    function kwetupizza_render_whatsapp_webhook_helper() {
        // Only run this in admin and when WordPress functions are available
        if (!is_admin() || !function_exists('wp_create_nonce')) {
            return;
        }
        
        // Only add this to the settings page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'kwetupizza_page_kwetupizza-settings') {
            return;
        }
        
        ?>
        <div id="whatsapp-webhook-helper" style="margin-top: 20px;">
            <h3>WhatsApp Webhook Configuration Helper</h3>
            <p>This tool helps you verify your WhatsApp webhook configuration.</p>
            
            <div class="webhook-url-display">
                <strong>Your Webhook URL:</strong>
                <code><?php echo esc_url(kwetupizza_get_whatsapp_webhook_url()); ?></code>
                <button id="copy-webhook-url" class="button button-secondary">Copy URL</button>
            </div>
            
            <p>
                <strong>Verify Token:</strong>
                <code><?php echo esc_html(get_option('kwetupizza_whatsapp_verify_token', '')); ?></code>
                <button id="generate-verify-token" class="button button-secondary">Generate New Token</button>
            </p>
            
            <div id="test-webhook-container" style="margin-top: 15px;">
                <button id="test-webhook" class="button button-primary">Test Webhook Configuration</button>
                <div id="test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Copy webhook URL
            $('#copy-webhook-url').on('click', function() {
                var webhookUrl = '<?php echo esc_url(kwetupizza_get_whatsapp_webhook_url()); ?>';
                navigator.clipboard.writeText(webhookUrl).then(function() {
                    alert('Webhook URL copied to clipboard!');
                });
            });
            
            // Generate new verify token
            $('#generate-verify-token').on('click', function() {
                if (confirm('Are you sure you want to generate a new verification token? This will require updating your webhook settings with Meta.')) {
                    $.ajax({
                        url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                        type: 'POST',
                        data: {
                            action: 'generate_whatsapp_verify_token',
                            security: '<?php echo wp_create_nonce('kwetu_pizza_whatsapp_token_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('New token generated: ' + response.data.token);
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                            }
                        }
                    });
                }
            });
            
            // Test webhook configuration
            $('#test-webhook').on('click', function() {
                var $button = $(this);
                var $result = $('#test-result');
                
                $button.prop('disabled', true);
                $result.html('<em>Testing webhook...</em>');
                
                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'test_whatsapp_webhook',
                        security: '<?php echo wp_create_nonce('kwetu_pizza_whatsapp_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color:green;">✓ Success! Webhook is properly configured.</span>');
                        } else {
                            $result.html('<span style="color:red;">✗ Error: ' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color:red;">✗ Test failed. Check server logs.</span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
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
        
        // Check for greeting messages
        $greeting_patterns = array(
            '/^hi$/i', 
            '/^hello$/i', 
            '/^hey$/i', 
            '/^howdy$/i', 
            '/^start$/i',
            '/^menu$/i',
            '/^help$/i'
        );
        
        $is_greeting = false;
        foreach ($greeting_patterns as $pattern) {
            if (preg_match($pattern, trim($message))) {
                $is_greeting = true;
                break;
            }
        }
        
        if ($is_greeting) {
            // Send greeting message
            kwetupizza_send_greeting($from);
            
            // Reset conversation context
            kwetupizza_reset_conversation_context($from);
            
            return true;
        }
        
        // Get the conversation context
        $context = kwetupizza_get_conversation_context($from);
        
        // Handle based on context
        if (isset($context['awaiting']) && !empty($context['awaiting'])) {
            switch ($context['awaiting']) {
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
                    // For any other state, send the default response
                    kwetupizza_send_default_message($from);
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
        
        // Send a default response if nothing else matched
        kwetupizza_send_default_message($from);
        
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


