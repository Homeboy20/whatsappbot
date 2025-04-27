<?php
/**
 * WhatsApp Inbox Admin Page
 * 
 * This file handles the admin interface for the WhatsApp inbox, allowing
 * admin users to view and respond to customer messages in real-time.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the WhatsApp Inbox admin page
 */
function kwetupizza_render_whatsapp_inbox() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Process reply form if submitted
    if (isset($_POST['kwetupizza_inbox_reply']) && isset($_POST['kwetupizza_customer_phone']) && isset($_POST['kwetupizza_reply_message'])) {
        $customer_phone = sanitize_text_field($_POST['kwetupizza_customer_phone']);
        $reply_message = sanitize_textarea_field($_POST['kwetupizza_reply_message']);
        
        // Send the reply via WhatsApp
        $sent = kwetupizza_send_whatsapp_message($customer_phone, $reply_message);
        
        // Log the admin reply in the conversation history
        if ($sent) {
            kwetupizza_log_whatsapp_conversation(array(
                'phone' => $customer_phone,
                'message' => $reply_message,
                'direction' => 'outgoing',
                'timestamp' => current_time('mysql'),
                'agent_id' => get_current_user_id()
            ));
            
            $success_message = "Reply sent successfully to $customer_phone.";
        } else {
            $error_message = "Failed to send reply to $customer_phone. Please try again.";
        }
    }
    
    // Get conversations
    global $wpdb;
    $conversations = $wpdb->get_results("
        SELECT DISTINCT phone, MAX(timestamp) as last_message
        FROM {$wpdb->prefix}kwetupizza_whatsapp_logs
        GROUP BY phone
        ORDER BY last_message DESC
    ");
    
    // Get customer details for each conversation
    $conversations_with_details = array();
    foreach ($conversations as $conversation) {
        $user_details = kwetupizza_get_customer_details($conversation->phone);
        $user_name = !empty($user_details['name']) ? $user_details['name'] : 'Unknown';
        
        // Get the last message
        $last_message = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}kwetupizza_whatsapp_logs
            WHERE phone = %s
            ORDER BY timestamp DESC
            LIMIT 1
        ", $conversation->phone));
        
        // Get the support ticket status if it exists
        $ticket_status = $wpdb->get_var($wpdb->prepare("
            SELECT status FROM {$wpdb->prefix}kwetupizza_support_tickets
            WHERE phone = %s
            ORDER BY created_at DESC
            LIMIT 1
        ", $conversation->phone));
        
        $conversations_with_details[] = array(
            'phone' => $conversation->phone,
            'name' => $user_name,
            'last_message_time' => $conversation->last_message,
            'last_message_text' => $last_message ? $last_message->message : '',
            'last_message_direction' => $last_message ? $last_message->direction : '',
            'has_active_ticket' => ($ticket_status == 'open' || $ticket_status == 'pending')
        );
    }
    
    // Get conversation history for selected user
    $selected_phone = isset($_GET['phone']) ? sanitize_text_field($_GET['phone']) : '';
    $conversation_history = array();
    $selected_user_details = array();
    
    if (!empty($selected_phone)) {
        $conversation_history = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}kwetupizza_whatsapp_logs
            WHERE phone = %s
            ORDER BY timestamp ASC
        ", $selected_phone));
        
        $selected_user_details = kwetupizza_get_customer_details($selected_phone);
    }
    
    // Display the inbox UI
    ?>
    <div class="wrap kwetupizza-whatsapp-inbox">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php if (isset($success_message)): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($success_message); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error_message); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="inbox-container">
            <div class="conversation-list">
                <h2>Recent Conversations</h2>
                <?php if (empty($conversations_with_details)): ?>
                    <p>No conversations found.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($conversations_with_details as $convo): ?>
                            <li class="<?php echo ($selected_phone == $convo['phone']) ? 'active' : ''; ?> <?php echo ($convo['has_active_ticket']) ? 'has-ticket' : ''; ?>">
                                <a href="?page=kwetupizza-whatsapp-inbox&phone=<?php echo esc_attr($convo['phone']); ?>">
                                    <div class="convo-header">
                                        <span class="convo-name"><?php echo esc_html($convo['name']); ?></span>
                                        <span class="convo-phone"><?php echo esc_html($convo['phone']); ?></span>
                                        <?php if ($convo['has_active_ticket']): ?>
                                            <span class="ticket-badge">Support</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="convo-preview">
                                        <span class="direction"><?php echo ($convo['last_message_direction'] == 'incoming') ? '←' : '→'; ?></span>
                                        <span class="message"><?php echo esc_html(substr($convo['last_message_text'], 0, 30)) . (strlen($convo['last_message_text']) > 30 ? '...' : ''); ?></span>
                                        <span class="time"><?php echo esc_html(human_time_diff(strtotime($convo['last_message_time']), current_time('timestamp')) . ' ago'); ?></span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <div class="conversation-details">
                <?php if (!empty($selected_phone)): ?>
                    <div class="conversation-header">
                        <h2>
                            Conversation with <?php echo esc_html(!empty($selected_user_details['name']) ? $selected_user_details['name'] : 'Unknown'); ?>
                            <span class="phone"><?php echo esc_html($selected_phone); ?></span>
                        </h2>
                        
                        <?php
                        // Check if there's an active ticket
                        $active_ticket = $wpdb->get_row($wpdb->prepare("
                            SELECT * FROM {$wpdb->prefix}kwetupizza_support_tickets
                            WHERE phone = %s AND (status = 'open' OR status = 'pending')
                            ORDER BY created_at DESC
                            LIMIT 1
                        ", $selected_phone));
                        
                        if ($active_ticket): ?>
                            <div class="ticket-info">
                                <h3>Support Ticket #<?php echo esc_html($active_ticket->id); ?></h3>
                                <p>Status: <?php echo esc_html(ucfirst($active_ticket->status)); ?></p>
                                <p>Created: <?php echo esc_html(human_time_diff(strtotime($active_ticket->created_at), current_time('timestamp')) . ' ago'); ?></p>
                                <p>Issue: <?php echo esc_html($active_ticket->issue); ?></p>
                                
                                <form method="post" action="">
                                    <?php wp_nonce_field('kwetupizza_update_ticket', 'kwetupizza_ticket_nonce'); ?>
                                    <input type="hidden" name="kwetupizza_ticket_id" value="<?php echo esc_attr($active_ticket->id); ?>">
                                    <select name="kwetupizza_ticket_status">
                                        <option value="open" <?php selected($active_ticket->status, 'open'); ?>>Open</option>
                                        <option value="pending" <?php selected($active_ticket->status, 'pending'); ?>>Pending</option>
                                        <option value="resolved" <?php selected($active_ticket->status, 'resolved'); ?>>Resolved</option>
                                        <option value="closed" <?php selected($active_ticket->status, 'closed'); ?>>Closed</option>
                                    </select>
                                    <button type="submit" name="kwetupizza_update_ticket" class="button">Update Status</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="create-ticket">
                                <form method="post" action="">
                                    <?php wp_nonce_field('kwetupizza_create_ticket', 'kwetupizza_ticket_nonce'); ?>
                                    <input type="hidden" name="kwetupizza_customer_phone" value="<?php echo esc_attr($selected_phone); ?>">
                                    <label for="kwetupizza_ticket_issue">Create Support Ticket:</label>
                                    <input type="text" id="kwetupizza_ticket_issue" name="kwetupizza_ticket_issue" placeholder="Describe the issue..." required>
                                    <button type="submit" name="kwetupizza_create_ticket" class="button">Create Ticket</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="conversation-messages">
                        <?php if (empty($conversation_history)): ?>
                            <p>No messages found for this conversation.</p>
                        <?php else: ?>
                            <div class="messages-container">
                                <?php foreach ($conversation_history as $message): ?>
                                    <div class="message <?php echo esc_attr($message->direction); ?>">
                                        <div class="message-content">
                                            <?php echo nl2br(esc_html($message->message)); ?>
                                        </div>
                                        <div class="message-meta">
                                            <?php echo esc_html(date_i18n('M j, Y g:i a', strtotime($message->timestamp))); ?>
                                            <?php if ($message->direction == 'outgoing' && !empty($message->agent_id)): ?>
                                                by <?php echo esc_html(get_user_by('id', $message->agent_id)->display_name); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="reply-form">
                        <form method="post" action="">
                            <input type="hidden" name="kwetupizza_customer_phone" value="<?php echo esc_attr($selected_phone); ?>">
                            <textarea name="kwetupizza_reply_message" placeholder="Type your reply here..." required></textarea>
                            <button type="submit" name="kwetupizza_inbox_reply" class="button button-primary">Send Reply</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="no-conversation-selected">
                        <p>Select a conversation from the list to view messages.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
    .kwetupizza-whatsapp-inbox .inbox-container {
        display: flex;
        border: 1px solid #ddd;
        border-radius: 4px;
        min-height: 600px;
        margin-top: 20px;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-list {
        width: 300px;
        border-right: 1px solid #ddd;
        overflow-y: auto;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-list ul {
        margin: 0;
        padding: 0;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-list li {
        margin: 0;
        padding: 0;
        border-bottom: 1px solid #eee;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-list li.active {
        background-color: #f0f0f1;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-list li.has-ticket {
        border-left: 3px solid #e44;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-list a {
        display: block;
        padding: 10px;
        text-decoration: none;
        color: #333;
    }
    
    .kwetupizza-whatsapp-inbox .convo-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }
    
    .kwetupizza-whatsapp-inbox .convo-name {
        font-weight: bold;
    }
    
    .kwetupizza-whatsapp-inbox .convo-phone {
        font-size: 0.8em;
        color: #777;
    }
    
    .kwetupizza-whatsapp-inbox .ticket-badge {
        background-color: #e44;
        color: white;
        font-size: 0.7em;
        padding: 2px 5px;
        border-radius: 3px;
    }
    
    .kwetupizza-whatsapp-inbox .convo-preview {
        display: flex;
        font-size: 0.9em;
        color: #555;
    }
    
    .kwetupizza-whatsapp-inbox .direction {
        margin-right: 5px;
    }
    
    .kwetupizza-whatsapp-inbox .time {
        margin-left: auto;
        font-size: 0.8em;
        color: #777;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-details {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 10px;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-header {
        padding-bottom: 10px;
        border-bottom: 1px solid #ddd;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-header h2 {
        display: flex;
        align-items: baseline;
    }
    
    .kwetupizza-whatsapp-inbox .phone {
        font-size: 0.8em;
        margin-left: 10px;
        color: #777;
    }
    
    .kwetupizza-whatsapp-inbox .ticket-info,
    .kwetupizza-whatsapp-inbox .create-ticket {
        background-color: #f9f9f9;
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
    }
    
    .kwetupizza-whatsapp-inbox .ticket-info h3 {
        margin-top: 0;
    }
    
    .kwetupizza-whatsapp-inbox .conversation-messages {
        flex: 1;
        overflow-y: auto;
        padding: 15px 0;
    }
    
    .kwetupizza-whatsapp-inbox .messages-container {
        display: flex;
        flex-direction: column;
    }
    
    .kwetupizza-whatsapp-inbox .message {
        max-width: 80%;
        margin-bottom: 15px;
        padding: 10px;
        border-radius: 8px;
    }
    
    .kwetupizza-whatsapp-inbox .message.incoming {
        align-self: flex-start;
        background-color: #f0f0f1;
    }
    
    .kwetupizza-whatsapp-inbox .message.outgoing {
        align-self: flex-end;
        background-color: #dcf8c6;
    }
    
    .kwetupizza-whatsapp-inbox .message-meta {
        font-size: 0.8em;
        color: #777;
        margin-top: 5px;
        text-align: right;
    }
    
    .kwetupizza-whatsapp-inbox .reply-form {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
    }
    
    .kwetupizza-whatsapp-inbox .reply-form textarea {
        width: 100%;
        min-height: 80px;
        margin-bottom: 10px;
    }
    
    .kwetupizza-whatsapp-inbox .no-conversation-selected {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #777;
    }
    </style>
    <?php
}

/**
 * Create database tables for support tickets and conversation logs
 */
function kwetupizza_create_support_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // WhatsApp conversation logs table
    $whatsapp_logs_table = $wpdb->prefix . 'kwetupizza_whatsapp_logs';
    $sql1 = "CREATE TABLE $whatsapp_logs_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        phone varchar(20) NOT NULL,
        message text NOT NULL,
        direction enum('incoming', 'outgoing') NOT NULL,
        timestamp datetime NOT NULL,
        agent_id bigint(20) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY phone (phone),
        KEY timestamp (timestamp)
    ) $charset_collate;";
    
    // Support tickets table
    $support_tickets_table = $wpdb->prefix . 'kwetupizza_support_tickets';
    $sql2 = "CREATE TABLE $support_tickets_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        phone varchar(20) NOT NULL,
        issue text NOT NULL,
        status enum('open', 'pending', 'resolved', 'closed') NOT NULL DEFAULT 'open',
        created_at datetime NOT NULL,
        resolved_at datetime DEFAULT NULL,
        agent_id bigint(20) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY phone (phone),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
}
register_activation_hook(__FILE__, 'kwetupizza_create_support_tables');

/**
 * Log WhatsApp conversations for record-keeping
 * 
 * @param array $data Array containing message details
 * @return bool Whether the log was saved successfully
 */
function kwetupizza_log_whatsapp_conversation($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_whatsapp_logs';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'phone' => $data['phone'],
            'message' => $data['message'],
            'direction' => $data['direction'],
            'timestamp' => $data['timestamp'],
            'agent_id' => isset($data['agent_id']) ? $data['agent_id'] : null
        )
    );
    
    return ($result !== false);
}

/**
 * Create a support ticket for a customer
 * 
 * @param string $phone Customer's phone number
 * @param string $issue Description of the issue
 * @param int $agent_id ID of the agent creating the ticket
 * @return int|false The ID of the created ticket or false on failure
 */
function kwetupizza_create_support_ticket($phone, $issue, $agent_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_support_tickets';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'phone' => $phone,
            'issue' => $issue,
            'status' => 'open',
            'created_at' => current_time('mysql'),
            'agent_id' => $agent_id
        )
    );
    
    if ($result !== false) {
        return $wpdb->insert_id;
    }
    
    return false;
}

/**
 * Update a support ticket status
 * 
 * @param int $ticket_id The ticket ID
 * @param string $status New status (open, pending, resolved, closed)
 * @return bool Whether the update was successful
 */
function kwetupizza_update_support_ticket($ticket_id, $status) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_support_tickets';
    
    $data = array('status' => $status);
    
    // Add resolved_at timestamp if resolving or closing
    if ($status == 'resolved' || $status == 'closed') {
        $data['resolved_at'] = current_time('mysql');
    }
    
    $result = $wpdb->update(
        $table_name,
        $data,
        array('id' => $ticket_id)
    );
    
    return ($result !== false);
}

/**
 * Process incoming WhatsApp messages and log them
 * 
 * @param string $phone Sender's phone number
 * @param string $message Message content
 * @return void
 */
function kwetupizza_log_incoming_whatsapp($phone, $message) {
    kwetupizza_log_whatsapp_conversation(array(
        'phone' => $phone,
        'message' => $message,
        'direction' => 'incoming',
        'timestamp' => current_time('mysql')
    ));
}

// Hook into the WhatsApp message processor to log incoming messages
add_action('kwetupizza_incoming_whatsapp_message', 'kwetupizza_log_incoming_whatsapp', 10, 2);

// Process ticket creation from admin
add_action('admin_init', function() {
    if (isset($_POST['kwetupizza_create_ticket']) && isset($_POST['kwetupizza_ticket_nonce']) && wp_verify_nonce($_POST['kwetupizza_ticket_nonce'], 'kwetupizza_create_ticket')) {
        $phone = sanitize_text_field($_POST['kwetupizza_customer_phone']);
        $issue = sanitize_text_field($_POST['kwetupizza_ticket_issue']);
        
        $ticket_id = kwetupizza_create_support_ticket($phone, $issue, get_current_user_id());
        
        if ($ticket_id) {
            // Send confirmation to customer
            $message = "A support ticket (#$ticket_id) has been created for your request. Our team will assist you shortly.";
            kwetupizza_send_whatsapp_message($phone, $message);
            
            // Log the outgoing message
            kwetupizza_log_whatsapp_conversation(array(
                'phone' => $phone,
                'message' => $message,
                'direction' => 'outgoing',
                'timestamp' => current_time('mysql'),
                'agent_id' => get_current_user_id()
            ));
            
            // Add success message
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Support ticket created successfully.</p></div>';
            });
        }
        
        // Redirect to avoid form resubmission
        wp_redirect(add_query_arg(array('page' => 'kwetupizza-whatsapp-inbox', 'phone' => $phone), admin_url('admin.php')));
        exit;
    }
    
    // Process ticket status updates
    if (isset($_POST['kwetupizza_update_ticket']) && isset($_POST['kwetupizza_ticket_nonce']) && wp_verify_nonce($_POST['kwetupizza_ticket_nonce'], 'kwetupizza_update_ticket')) {
        $ticket_id = intval($_POST['kwetupizza_ticket_id']);
        $status = sanitize_text_field($_POST['kwetupizza_ticket_status']);
        
        $updated = kwetupizza_update_support_ticket($ticket_id, $status);
        
        if ($updated) {
            // Get the customer's phone number
            global $wpdb;
            $phone = $wpdb->get_var($wpdb->prepare("
                SELECT phone FROM {$wpdb->prefix}kwetupizza_support_tickets
                WHERE id = %d
            ", $ticket_id));
            
            if ($phone) {
                // Notify customer of status change
                $status_messages = array(
                    'open' => "Your support ticket (#$ticket_id) has been reopened. We'll assist you shortly.",
                    'pending' => "Your support ticket (#$ticket_id) is pending further information. Please provide any additional details requested.",
                    'resolved' => "Your support ticket (#$ticket_id) has been resolved. Please let us know if you need further assistance.",
                    'closed' => "Your support ticket (#$ticket_id) has been closed. Thank you for using our service."
                );
                
                $message = isset($status_messages[$status]) ? $status_messages[$status] : "Your support ticket (#$ticket_id) status has been updated to: $status.";
                kwetupizza_send_whatsapp_message($phone, $message);
                
                // Log the outgoing message
                kwetupizza_log_whatsapp_conversation(array(
                    'phone' => $phone,
                    'message' => $message,
                    'direction' => 'outgoing',
                    'timestamp' => current_time('mysql'),
                    'agent_id' => get_current_user_id()
                ));
            }
            
            // Add success message
            add_action('admin_notices', function() use ($status) {
                echo '<div class="notice notice-success is-dismissible"><p>Support ticket updated to ' . esc_html(ucfirst($status)) . '.</p></div>';
            });
        }
        
        // Redirect to avoid form resubmission
        wp_redirect(add_query_arg(array('page' => 'kwetupizza-whatsapp-inbox', 'phone' => $phone), admin_url('admin.php')));
        exit;
    }
});
