<?php
/*
Plugin Name: KwetuPizza Plugin
Description: A pizza order management plugin with custom database structure, WhatsApp bot integration, and webhook callback URL auto-generation.
Version: 1.3
Author: Your Name
GitHub Plugin URI: https://github.com/YOURUSERNAME/kwetu-pizza-plugin
GitHub Branch: main
*/
date_default_timezone_set('Africa/Nairobi');
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('KWETUPIZZA_PLUGIN_FILE', __FILE__);
define('KWETUPIZZA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KWETUPIZZA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Plugin Update Checker
if (!class_exists('Puc_v4_Factory')) {
    require_once KWETUPIZZA_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
    $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
        'https://github.com/YOURUSERNAME/kwetu-pizza-plugin/',
        __FILE__,
        'kwetu-pizza-plugin'
    );
    
    // Set the branch that contains the stable release
    $myUpdateChecker->setBranch('main');
}

// Include logger functions first (for capturing errors in other includes)
if (file_exists(plugin_dir_path(__FILE__) . 'includes/kwetu-logger.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/kwetu-logger.php';
    // Log plugin initialization
    kwetu_log_event('plugin_init', 'Plugin initialization started');
}

// Include common functions
if (file_exists(plugin_dir_path(__FILE__) . 'includes/common-functions.php')) {
require_once plugin_dir_path(__FILE__) . 'includes/common-functions.php';
}

// Include WhatsApp handler
if (file_exists(plugin_dir_path(__FILE__) . 'includes/whatsapp-handler.php')) {
require_once plugin_dir_path(__FILE__) . 'includes/whatsapp-handler.php';
}

// Include Order Tracking
if (file_exists(plugin_dir_path(__FILE__) . 'includes/order-tracking.php')) {
require_once plugin_dir_path(__FILE__) . 'includes/order-tracking.php';
}

// Include Debug Functions (for development)
if (file_exists(plugin_dir_path(__FILE__) . 'includes/kwetu-debug.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/kwetu-debug.php';
}

// Define the database version
define('KWETUPIZZA_DB_VERSION', '1.1');

/**
 * Create or update the custom database tables upon plugin activation.
 */
/**
 * Create or update the custom database tables upon plugin activation.
 */
/**
 * Create or update the custom database tables upon plugin activation.
 */
/**
 * Create or update the custom database tables upon plugin activation.
 */
function kwetupizza_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Include the dbDelta function
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Define table names
    $users_table        = $wpdb->prefix . 'kwetupizza_users';
    $products_table     = $wpdb->prefix . 'kwetupizza_products';
    $orders_table       = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table  = $wpdb->prefix . 'kwetupizza_order_items';
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
    $addresses_table    = $wpdb->prefix . 'kwetupizza_addresses';
    $order_tracking_table = $wpdb->prefix . 'kwetupizza_order_tracking';

    // SQL for creating tables
    $sql_users = "CREATE TABLE {$users_table} (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        role VARCHAR(20) NOT NULL,
        state VARCHAR(255) DEFAULT 'greeting' NOT NULL,
        preferences TEXT,
        last_order_date DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY phone (phone),
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_products = "CREATE TABLE {$products_table} (
        id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_name VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        price FLOAT NOT NULL,
        currency VARCHAR(10) NOT NULL,
        category VARCHAR(50) NOT NULL,
        image_url VARCHAR(255) DEFAULT '',
        is_available TINYINT(1) DEFAULT 1,
        preparation_time INT DEFAULT 30,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_orders = "CREATE TABLE {$orders_table} (
        id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        tx_ref VARCHAR(255) DEFAULT NULL,
        order_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_email VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        delivery_address TEXT NOT NULL,
        delivery_phone VARCHAR(20) NOT NULL,
        status VARCHAR(50) NOT NULL,
        total FLOAT NOT NULL,
        currency VARCHAR(10) NOT NULL,
        scheduled_time DATETIME DEFAULT NULL,
        estimated_delivery_time DATETIME,
        actual_delivery_time DATETIME,
        delivery_notes TEXT,
        payment_status VARCHAR(50) DEFAULT 'pending',
        payment_method VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY customer_phone (customer_phone),
        KEY tx_ref (tx_ref)
    ) $charset_collate;";

    $sql_order_items = "CREATE TABLE {$order_items_table} (
        id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id MEDIUMINT(9) UNSIGNED NOT NULL,
        product_id MEDIUMINT(9) UNSIGNED NOT NULL,
        quantity INT NOT NULL,
        price FLOAT NOT NULL,
        special_instructions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY product_id (product_id)
    ) $charset_collate;";

    $sql_transactions = "CREATE TABLE {$transactions_table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id MEDIUMINT(9) UNSIGNED NOT NULL,
        tx_ref VARCHAR(255) NOT NULL,
        transaction_date DATETIME NOT NULL,
        payment_method VARCHAR(100) NOT NULL,
        payment_status VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        payment_provider VARCHAR(50) NOT NULL,
        provider_reference VARCHAR(255),
        response_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tx_ref (tx_ref),
        KEY order_id (order_id)
    ) $charset_collate;";

    $sql_addresses = "CREATE TABLE {$addresses_table} (
        id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id MEDIUMINT(9) UNSIGNED NOT NULL,
        address TEXT NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        latitude DECIMAL(10,8),
        longitude DECIMAL(11,8),
        address_type VARCHAR(50) DEFAULT 'home',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    $sql_order_tracking = "CREATE TABLE {$order_tracking_table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id MEDIUMINT(9) UNSIGNED NOT NULL,
        status VARCHAR(50) NOT NULL,
        description TEXT,
        location_update TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id)
    ) $charset_collate;";

    // Execute the SQL to create or update tables
    dbDelta($sql_users);
    dbDelta($sql_products);
    dbDelta($sql_orders);
    dbDelta($sql_order_items);
    dbDelta($sql_transactions);
    dbDelta($sql_addresses);
    dbDelta($sql_order_tracking);

    // Update the database version option
    update_option('kwetupizza_db_version', '2.0');
}

// Registration of the activation hook
function kwetupizza_activate() {
    try {
        // Create logs directory for debug logs
        $logs_dir = plugin_dir_path(__FILE__) . 'logs';
        if (!file_exists($logs_dir) && !is_dir($logs_dir)) {
            mkdir($logs_dir, 0755, true);
        }

        // Create an activation log
        $activation_log = $logs_dir . '/activation.log';
        file_put_contents($activation_log, "Activation started: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        
        // Create or update database tables
        kwetupizza_create_tables();
        
        file_put_contents($activation_log, "Activation completed successfully\n", FILE_APPEND);
    } catch (Exception $e) {
        // Log any errors during activation
        $error_log = plugin_dir_path(__FILE__) . 'logs/activation-error.log';
        $error_message = "Activation error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
        file_put_contents($error_log, $error_message, FILE_APPEND);
        
        // Re-throw the exception so WordPress can handle it
        throw $e;
    }
}
register_activation_hook(__FILE__, 'kwetupizza_activate');


 /* Check and update the database version if necessary.
 */
function kwetupizza_update_db_check() {
    if (get_option('kwetupizza_db_version') != KWETUPIZZA_DB_VERSION) {
        kwetupizza_create_tables();
    }
}
add_action('plugins_loaded', 'kwetupizza_update_db_check');

// Generate callback URLs for webhooks
function kwetupizza_get_callback_url($service) {
    return esc_url(home_url('/wp-json/kwetupizza/v1/' . $service . '-webhook'));
}

// Create menu in the WordPress dashboard
function kwetupizza_create_menu() {
    add_menu_page(
        'KwetuPizza Dashboard',
        'KwetuPizza',
        'manage_options',
        'kwetupizza-dashboard',
        'kwetupizza_render_dashboard',
        'dashicons-store',
        20
    );
    add_submenu_page('kwetupizza-dashboard', 'Menu Management', 'Menu Management', 'manage_options', 'kwetupizza-menu', 'kwetupizza_render_menu_management');
    add_submenu_page('kwetupizza-dashboard', 'Order Management', 'Order Management', 'manage_options', 'kwetupizza-orders', 'kwetupizza_render_order_management');
    add_submenu_page('kwetupizza-dashboard', 'Transaction Management', 'Transaction Management', 'manage_options', 'kwetupizza-transactions', 'kwetupizza_render_transaction_management');
    add_submenu_page('kwetupizza-dashboard', 'User Management', 'User Management', 'manage_options', 'kwetupizza-users', 'kwetupizza_render_user_management');
    add_submenu_page('kwetupizza-dashboard', 'Settings', 'Settings', 'manage_options', 'kwetupizza-settings', 'kwetupizza_render_settings_page');
    add_submenu_page('kwetupizza-dashboard', 'WhatsApp Inbox', 'WhatsApp Inbox', 'manage_options', 'kwetupizza-whatsapp-inbox', 'kwetupizza_render_whatsapp_inbox');
}
add_action('admin_menu', 'kwetupizza_create_menu');

// Render the settings page
function kwetupizza_render_settings_page() {
    ?>
    <div class="wrap">
        <h2 class="page-title">KwetuPizza Settings</h2>
        <div class="settings-section">
            <div class="tabs-container">
                <!-- Tabs navigation -->
                <ul class="horizontal-tabs">
                    <li><a href="#tab1" class="tab-link active">General Settings</a></li>
                    <li><a href="#tab2" class="tab-link">WhatsApp API</a></li>
                    <li><a href="#tab3" class="tab-link">Flutterwave</a></li>
                    <li><a href="#tab4" class="tab-link">NextSMS</a></li>
                    <li><a href="#tab5" class="tab-link">Admin Notifications</a></li>
                </ul>

                <!-- Tabs content -->
                <div id="tab1" class="tab-content active">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('kwetupizza_settings_group_general');
                        do_settings_sections('kwetupizza-settings-general');
                        submit_button();
                        ?>
                    </form>
                </div>

                <div id="tab2" class="tab-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('kwetupizza_settings_group_whatsapp');
                        do_settings_sections('kwetupizza-settings-whatsapp');
                        echo '<p>Callback URL: ' . kwetupizza_get_callback_url('whatsapp') . '</p>';
                        submit_button();
                        ?>
                    </form>
                </div>

                <div id="tab3" class="tab-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('kwetupizza_settings_group_flutterwave');
                        do_settings_sections('kwetupizza-settings-flutterwave');
                        echo '<p>Callback URL: ' . kwetupizza_get_callback_url('flutterwave') . '</p>';
                        submit_button();
                        ?>
                    </form>
                </div>

                <div id="tab4" class="tab-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('kwetupizza_settings_group_nextsms');
                        do_settings_sections('kwetupizza-settings-nextsms');
                        submit_button();
                        ?>
                    </form>
                </div>

                <div id="tab5" class="tab-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('kwetupizza_settings_group_notifications');
                        do_settings_sections('kwetupizza-settings-notifications');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Styles and scripts for the settings page -->
    <style>
        /* Modern Dashboard Styles */
        .wrap {
            max-width: 1200px;
            margin: 20px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .page-title {
            font-size: 32px;
            font-weight: 600;
            color: #1e1e1e;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Modern Card Styles */
        .settings-section {
            background: #fff;
            border-radius: 12px;
            margin-top: 25px;
        }

        /* Modern Tabs */
        .horizontal-tabs {
            list-style: none;
            padding: 0;
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .horizontal-tabs li {
            margin: 0;
        }

        .horizontal-tabs a {
            text-decoration: none;
            padding: 12px 24px;
            display: inline-block;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-bottom: 3px solid transparent;
            border-radius: 8px 8px 0 0;
            color: #495057;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .horizontal-tabs a:hover {
            background-color: #e9ecef;
            color: #228be6;
        }

        .horizontal-tabs a.active {
            background-color: #fff;
            border-bottom: 3px solid #228be6;
            color: #228be6;
            font-weight: 600;
        }

        /* Modern Form Elements */
        .form-table {
            border-collapse: separate;
            border-spacing: 0 15px;
            width: 100%;
        }

        .form-table th {
            font-weight: 600;
            color: #1e1e1e;
            padding: 20px;
            width: 200px;
            text-align: left;
        }

        .form-table td {
            padding: 20px;
        }

        input[type="text"],
        input[type="password"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: none;
            background-color: #f8f9fa;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        textarea:focus,
        select:focus {
            border-color: #228be6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(34, 139, 230, 0.1);
        }

        /* Modern Buttons */
        .button-primary {
            background-color: #228be6;
            border: none;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 14px;
        }

        .button-primary:hover {
            background-color: #1c7ed6;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Tab Content */
        .tab-content {
            display: none;
            background-color: #fff;
            border-radius: 12px;
            padding: 30px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* Status Messages */
        .notice {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
        }

        .notice-success {
            background-color: #d3f9d8;
            border-left-color: #37b24d;
            color: #2b9a3e;
        }

        .notice-error {
            background-color: #ffe3e3;
            border-left-color: #fa5252;
            color: #e03131;
        }

        /* Description Text */
        .description {
            color: #868e96;
            font-size: 13px;
            margin-top: 8px;
            font-style: italic;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media screen and (max-width: 782px) {
            .wrap {
                padding: 20px;
                margin: 10px;
            }

            .form-table th {
                padding: 10px;
                width: 100%;
                display: block;
            }

            .form-table td {
                padding: 10px;
                display: block;
            }

            .horizontal-tabs {
                flex-direction: column;
                gap: 5px;
            }

            .horizontal-tabs a {
                width: 100%;
                text-align: center;
                border-radius: 8px;
            }
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .dashboard-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .dashboard-card h3 {
            font-size: 18px;
            color: #1e1e1e;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-card .dashicons {
            color: #228be6;
            font-size: 24px;
            width: 24px;
            height: 24px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 600;
            color: #228be6;
            margin: 15px 0;
        }

        .stat-label {
            color: #868e96;
            font-size: 14px;
        }

        /* Data Tables */
        .wp-list-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            margin: 20px 0;
        }

        .wp-list-table th {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #1e1e1e;
            border-bottom: 2px solid #e9ecef;
        }

        .wp-list-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .wp-list-table tr:hover {
            background-color: #f8f9fa;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fff3bf;
            color: #fab005;
        }

        .status-completed {
            background-color: #d3f9d8;
            color: #37b24d;
        }

        .status-cancelled {
            background-color: #ffe3e3;
            color: #fa5252;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.tab-link');
            const contents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function (e) {
                    e.preventDefault();

                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    contents.forEach(content => content.classList.remove('active'));

                    // Add active class to the clicked tab and corresponding content
                    this.classList.add('active');
                    const contentId = this.getAttribute('href');
                    document.querySelector(contentId).classList.add('active');
                });
            });
        });
    </script>
    <?php
}

// Register and initialize settings
function kwetupizza_register_settings() {
    // General settings
    register_setting('kwetupizza_settings_group_general', 'kwetupizza_currency', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_general', 'kwetupizza_location', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_general', 'kwetupizza_delivery_area', 'sanitize_text_field');

    // WhatsApp API settings
    register_setting('kwetupizza_settings_group_whatsapp', 'kwetupizza_whatsapp_token', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_whatsapp', 'kwetupizza_whatsapp_phone_id', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_whatsapp', 'kwetupizza_whatsapp_verify_token', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_whatsapp', 'kwetupizza_whatsapp_api_version', 'sanitize_text_field');

    // Flutterwave API settings
    register_setting('kwetupizza_settings_group_flutterwave', 'kwetupizza_flw_public_key', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_flutterwave', 'kwetupizza_flw_secret_key', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_flutterwave', 'kwetupizza_flw_encryption_key', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_flutterwave', 'kwetupizza_flw_webhook_secret', 'sanitize_text_field');

    // NextSMS settings
    register_setting('kwetupizza_settings_group_nextsms', 'kwetupizza_nextsms_username', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_nextsms', 'kwetupizza_nextsms_password', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_nextsms', 'kwetupizza_nextsms_sender_id', 'sanitize_text_field');
    
    // Admin notification settings
    register_setting('kwetupizza_settings_group_notifications', 'kwetupizza_admin_whatsapp', 'sanitize_text_field');
    register_setting('kwetupizza_settings_group_notifications', 'kwetupizza_admin_sms_number', 'sanitize_text_field');

    // Add settings fields for each section
    // General Settings Section
    add_settings_section('kwetupizza_general_settings', 'General Settings', null, 'kwetupizza-settings-general');
    add_settings_field('kwetupizza_currency', 'Currency', 'kwetupizza_currency_callback', 'kwetupizza-settings-general', 'kwetupizza_general_settings');
    add_settings_field('kwetupizza_location', 'Restaurant Location', 'kwetupizza_location_callback', 'kwetupizza-settings-general', 'kwetupizza_general_settings');
    add_settings_field('kwetupizza_delivery_area', 'Delivery Area', 'kwetupizza_delivery_area_callback', 'kwetupizza-settings-general', 'kwetupizza_general_settings');

    // WhatsApp API Settings Section
    add_settings_section('kwetupizza_whatsapp_settings', 'WhatsApp API Settings', null, 'kwetupizza-settings-whatsapp');
    add_settings_field('kwetupizza_whatsapp_token', 'WhatsApp Access Token', 'kwetupizza_whatsapp_token_callback', 'kwetupizza-settings-whatsapp', 'kwetupizza_whatsapp_settings');
    add_settings_field('kwetupizza_whatsapp_phone_id', 'WhatsApp Phone ID', 'kwetupizza_whatsapp_phone_id_callback', 'kwetupizza-settings-whatsapp', 'kwetupizza_whatsapp_settings');
    add_settings_field('kwetupizza_whatsapp_verify_token', 'WhatsApp Verify Token', 'kwetupizza_whatsapp_verify_token_callback', 'kwetupizza-settings-whatsapp', 'kwetupizza_whatsapp_settings');
    add_settings_field('kwetupizza_whatsapp_api_version', 'WhatsApp API Version', 'kwetupizza_whatsapp_api_version_callback', 'kwetupizza-settings-whatsapp', 'kwetupizza_whatsapp_settings');

    // Flutterwave API Settings Section
    add_settings_section('kwetupizza_flutterwave_settings', 'Flutterwave API Settings', null, 'kwetupizza-settings-flutterwave');
    add_settings_field('kwetupizza_flw_public_key', 'Flutterwave Public Key', 'kwetupizza_flw_public_key_callback', 'kwetupizza-settings-flutterwave', 'kwetupizza_flutterwave_settings');
    add_settings_field('kwetupizza_flw_secret_key', 'Flutterwave Secret Key', 'kwetupizza_flw_secret_key_callback', 'kwetupizza-settings-flutterwave', 'kwetupizza_flutterwave_settings');
    add_settings_field('kwetupizza_flw_encryption_key', 'Flutterwave Encryption Key', 'kwetupizza_flw_encryption_key_callback', 'kwetupizza-settings-flutterwave', 'kwetupizza_flutterwave_settings');
    add_settings_field('kwetupizza_flw_webhook_secret', 'Webhook Secret Hash', 'kwetupizza_flw_webhook_secret_callback', 'kwetupizza-settings-flutterwave', 'kwetupizza_flutterwave_settings');

    // NextSMS Settings Section
    add_settings_section('kwetupizza_nextsms_settings', 'NextSMS Settings', null, 'kwetupizza-settings-nextsms');
    add_settings_field('kwetupizza_nextsms_username', 'NextSMS Username', 'kwetupizza_nextsms_username_callback', 'kwetupizza-settings-nextsms', 'kwetupizza_nextsms_settings');
    add_settings_field('kwetupizza_nextsms_password', 'NextSMS Password', 'kwetupizza_nextsms_password_callback', 'kwetupizza-settings-nextsms', 'kwetupizza_nextsms_settings');
    add_settings_field('kwetupizza_nextsms_sender_id', 'NextSMS Sender ID', 'kwetupizza_nextsms_sender_id_callback', 'kwetupizza-settings-nextsms', 'kwetupizza_nextsms_settings');

    // Admin Notification Settings Section
    add_settings_section('kwetupizza_notification_settings', 'Admin Notification Settings', null, 'kwetupizza-settings-notifications');
    add_settings_field('kwetupizza_admin_whatsapp', 'Admin WhatsApp Number', 'kwetupizza_admin_whatsapp_callback', 'kwetupizza-settings-notifications', 'kwetupizza_notification_settings');
    add_settings_field('kwetupizza_admin_sms_number', 'Admin SMS Number', 'kwetupizza_admin_sms_callback', 'kwetupizza-settings-notifications', 'kwetupizza_notification_settings');
}
add_action('admin_init', 'kwetupizza_register_settings');

// Callback functions for settings fields
function kwetupizza_currency_callback() {
    $currency = get_option('kwetupizza_currency', 'TZS');
    echo "<input type='text' name='kwetupizza_currency' value='" . esc_attr($currency) . "' />";
}

function kwetupizza_location_callback() {
    $location = get_option('kwetupizza_location', '');
    echo "<input type='text' name='kwetupizza_location' value='" . esc_attr($location) . "' />";
}

function kwetupizza_delivery_area_callback() {
    $delivery_area = get_option('kwetupizza_delivery_area', '');
    echo "<input type='text' name='kwetupizza_delivery_area' value='" . esc_attr($delivery_area) . "' />";
}

function kwetupizza_whatsapp_token_callback() {
    $token = get_option('kwetupizza_whatsapp_token', '');
    echo "<input type='text' name='kwetupizza_whatsapp_token' value='" . esc_attr($token) . "' />";
}

function kwetupizza_whatsapp_phone_id_callback() {
    $phone_id = get_option('kwetupizza_whatsapp_phone_id', '');
    echo "<input type='text' name='kwetupizza_whatsapp_phone_id' value='" . esc_attr($phone_id) . "' />";
}

function kwetupizza_whatsapp_verify_token_callback() {
    $verify_token = get_option('kwetupizza_whatsapp_verify_token', '');
    echo '<label for="kwetupizza_whatsapp_verify_token">Enter your WhatsApp Verify Token:</label><br />';
    echo "<input type='text' id='kwetupizza_whatsapp_verify_token' name='kwetupizza_whatsapp_verify_token' value='" . esc_attr($verify_token) . "' />";
    echo '<p class="description">This token must match the one you set in your WhatsApp Business API webhook configuration.</p>';
}

function kwetupizza_whatsapp_api_version_callback() {
    $api_version = get_option('kwetupizza_whatsapp_api_version', 'v15.0');
    echo "<input type='text' name='kwetupizza_whatsapp_api_version' value='" . esc_attr($api_version) . "' />";
}

function kwetupizza_flw_public_key_callback() {
    $public_key = get_option('kwetupizza_flw_public_key', '');
    echo "<input type='text' name='kwetupizza_flw_public_key' value='" . esc_attr($public_key) . "' />";
}

function kwetupizza_flw_secret_key_callback() {
    $secret_key = get_option('kwetupizza_flw_secret_key', '');
    echo "<input type='text' name='kwetupizza_flw_secret_key' value='" . esc_attr($secret_key) . "' />";
}

function kwetupizza_flw_encryption_key_callback() {
    $encryption_key = get_option('kwetupizza_flw_encryption_key', '');
    echo "<input type='text' name='kwetupizza_flw_encryption_key' value='" . esc_attr($encryption_key) . "' />";
}

function kwetupizza_flw_webhook_secret_callback() {
    $secret_hash = get_option('kwetupizza_flw_webhook_secret', '');
    echo "<input type='text' name='kwetupizza_flw_webhook_secret' value='" . esc_attr($secret_hash) . "' />";
    echo "<p>Please ensure this matches the 'Secret Hash' set in your Flutterwave Dashboard under Webhook settings.</p>";
}

function kwetupizza_nextsms_username_callback() {
    $username = get_option('kwetupizza_nextsms_username', '');
    echo "<input type='text' name='kwetupizza_nextsms_username' value='" . esc_attr($username) . "' />";
}

function kwetupizza_nextsms_password_callback() {
    $password = get_option('kwetupizza_nextsms_password', '');
    echo "<input type='password' name='kwetupizza_nextsms_password' value='" . esc_attr($password) . "' />";
}

function kwetupizza_nextsms_sender_id_callback() {
    $sender_id = get_option('kwetupizza_nextsms_sender_id', 'KwetuPizza');
    echo "<input type='text' name='kwetupizza_nextsms_sender_id' value='" . esc_attr($sender_id) . "' />";
}

function kwetupizza_admin_whatsapp_callback() {
    $admin_whatsapp = get_option('kwetupizza_admin_whatsapp', '');
    echo "<input type='text' name='kwetupizza_admin_whatsapp' value='" . esc_attr($admin_whatsapp) . "' />";
}

function kwetupizza_admin_sms_callback() {
    $admin_sms = get_option('kwetupizza_admin_sms_number', '');
    echo "<input type='text' name='kwetupizza_admin_sms_number' value='" . esc_attr($admin_sms) . "' />";
}

// Include other admin pages and functions
include_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
include_once plugin_dir_path(__FILE__) . 'admin/menu-management.php';
include_once plugin_dir_path(__FILE__) . 'admin/order-management.php';
include_once plugin_dir_path(__FILE__) . 'admin/transaction-management.php';
include_once plugin_dir_path(__FILE__) . 'admin/user-management.php';
include_once plugin_dir_path(__FILE__) . 'includes/user-detail.php'; // Ensure correct path
include_once plugin_dir_path(__FILE__) . 'admin/whatsapp-inbox.php';
include_once plugin_dir_path(__FILE__) . 'admin/log-viewer.php'; // Add log viewer

// Function to process successful payments
function kwetupizza_process_successful_payment($data) {
    global $wpdb;

    // Extract transaction details
    $tx_ref = $data['data']['tx_ref'];
    $amount = $data['data']['amount'];
    $currency = $data['data']['currency'];

    // Log transaction details
    $log_file = plugin_dir_path(__FILE__) . 'flutterwave-webhook.log';
    file_put_contents($log_file, "Processing successful payment for tx_ref: $tx_ref, Amount: $amount $currency" . PHP_EOL, FILE_APPEND);

    // Update the order in the database to "completed"
    $wpdb->update(
        $wpdb->prefix . 'kwetupizza_orders',
        ['status' => 'completed'],
        ['tx_ref' => $tx_ref]
    );

    // Save the transaction details
    $transaction_data = [
        'tx_ref'    => $tx_ref,
        'amount'    => $amount,
        'currency'  => $currency,
        'status'    => $data['data']['status'],
        'payment_type' => $data['data']['payment_type'],
        'network'   => $data['data']['network'],
    ];
    kwetupizza_save_transaction($tx_ref, $transaction_data);

    // Notify the customer and admin
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));
    if ($order) {
        $message = "Your payment of {$currency} {$amount} for order ID {$order->id} has been successfully completed. Thank you for choosing KwetuPizza!";
        kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        kwetupizza_notify_admin($order->id, 'successful');
    }
}

// Function to process failed payment
function kwetupizza_process_failed_payment($data) {
    global $wpdb;

    // Extract transaction reference
    $tx_ref = $data['data']['tx_ref'];

    // Log failure details
    $log_file = plugin_dir_path(__FILE__) . 'flutterwave-webhook.log';
    file_put_contents($log_file, "Processing failed payment for tx_ref: $tx_ref" . PHP_EOL, FILE_APPEND);

    // Update order status to "failed"
    $wpdb->update(
        $wpdb->prefix . 'kwetupizza_orders',
        ['status' => 'failed'],
        ['tx_ref' => $tx_ref]
    );

    // Handle payment failure (e.g., notify customer)
    kwetupizza_handle_failed_payment($tx_ref);
}

// Function to handle failed payment and notify customer/admin
function kwetupizza_handle_failed_payment($tx_ref) {
    global $wpdb;

    // Fetch the order using tx_ref
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));

    if ($order) {
        // Notify the customer
        kwetupizza_send_payment_failed_notification($order->id);

        // Notify the admin
        kwetupizza_notify_admin($order->id, 'failed');
    } else {
        error_log("Order not found for transaction reference: $tx_ref");
    }
}

// Function to send payment failed notification via WhatsApp and SMS
function kwetupizza_send_payment_failed_notification($order_id) {
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d", $order_id));

    if ($order) {
        $message = "Your payment for order ID {$order->id} could not be completed. Please try again by following this link: [Payment Link].";

        // Send WhatsApp notification
        kwetupizza_send_whatsapp_message($order->customer_phone, $message);

        // Optionally, send SMS notification using NextSMS
         kwetupizza_send_sms($order->customer_phone, $message);
    }
}

// Function to send WhatsApp message using the WhatsApp API
if (!function_exists('kwetupizza_send_whatsapp_message')) {
    function kwetupizza_send_whatsapp_message($phone_number, $message_type, $template_name = '', $template_parameters = []) {
        $token = get_option('kwetupizza_whatsapp_token');
        $phone_id = get_option('kwetupizza_whatsapp_phone_id');
        $api_version = get_option('kwetupizza_whatsapp_api_version', 'v15.0');
        $enable_logging = get_option('kwetupizza_enable_logging', false);

        if (!$token || !$phone_id) {
            if ($enable_logging) {
                error_log('WhatsApp token or phone ID not set.');
            }
            return false;
        }

        // Validate phone number
        $phone_number = preg_replace('/\D/', '', $phone_number);
        if (!preg_match('/^\d{7,15}$/', $phone_number)) {
            if ($enable_logging) {
                error_log('Invalid phone number format: ' . $phone_number);
            }
            return false;
        }

        $url = "https://graph.facebook.com/{$api_version}/{$phone_id}/messages";

        if ($message_type === 'template') {
            // Prepare data for template message
            $data = [
                "messaging_product" => "whatsapp",
                "to" => $phone_number,
                "type" => "template",
                "template" => [
                    "name" => $template_name,
                    "language" => [
                        "code" => "en_US" // Change if your template is in a different language
                    ],
                    "components" => [
                        [
                            "type" => "body",
                            "parameters" => $template_parameters
                        ]
                    ]
                ]
            ];
        } else {
            // Prepare data for text message
            $data = [
                "messaging_product" => "whatsapp",
                "to" => $phone_number,
                "type" => "text",
                "text" => [
                    "body" => $message_type // In this case, $message_type holds the text message
                ]
            ];
        }

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($data),
            'timeout' => 45,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            if ($enable_logging) {
                error_log('Failed to send WhatsApp message: ' . $response->get_error_message());
            }
            return false;
        } else {
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($response_body['error'])) {
                if ($enable_logging) {
                    error_log('WhatsApp API Error: ' . print_r($response_body['error'], true));
                }
                return false;
            } else {
                if ($enable_logging) {
                    error_log('WhatsApp message sent successfully. Response: ' . print_r($response_body, true));
                }
                return true;
            }
        }
    }
}

// Notify Admin on Order Creation or Update
function kwetupizza_notify_admin_on_order($order_id, $status = 'new') {
    global $wpdb;

    // Retrieve the order details
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d", $order_id));

    if (!$order) {
        error_log("Order ID {$order_id} not found for admin notification.");
        return;
    }

    // Prepare the message
    $order_items = kwetupizza_get_order_items_summary($order_id);
    $message = "🚨 Admin Alert:\n";
    $message .= "Order ID: {$order_id}\n";
    $message .= "Status: {$status}\n";
    $message .= "Customer: {$order->customer_name}\n";
    $message .= "Phone: {$order->customer_phone}\n";
    $message .= "Total: " . number_format($order->total, 2) . " TZS\n";
    $message .= "Delivery Address: {$order->delivery_address}\n";
    $message .= "Items:\n{$order_items}";

    // Get admin contact details
    $admin_whatsapp = get_option('kwetupizza_admin_whatsapp');
    $admin_sms = get_option('kwetupizza_admin_sms_number');

    // Send WhatsApp notification
    if (!empty($admin_whatsapp)) {
        $whatsapp_sent = kwetupizza_send_whatsapp_message($admin_whatsapp, $message);
        if (!$whatsapp_sent) {
            error_log("Failed to send WhatsApp notification to admin for Order ID {$order_id}.");
        }
    }

    // Send SMS notification
    if (!empty($admin_sms)) {
        $sms_sent = kwetupizza_send_sms($admin_sms, $message);
        if (!$sms_sent) {
            error_log("Failed to send SMS notification to admin for Order ID {$order_id}.");
        }
    }
}

// Hook into order creation or update to notify admin
add_action('kwetupizza_order_created', 'kwetupizza_notify_admin_on_order', 10, 2);
add_action('kwetupizza_order_updated', 'kwetupizza_notify_admin_on_order', 10, 2);


// Include WhatsApp handler
include_once plugin_dir_path(__FILE__) . 'includes/whatsapp-handler.php';
include_once plugin_dir_path(__FILE__) . 'admin/whatsapp-inbox.php';
// kwetu-pizza-plugin.php

// Include necessary admin pages

