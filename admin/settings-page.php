<?php
// Add the settings page for KwetuPizza
function kwetupizza_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>KwetuPizza Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('kwetupizza_settings_group');
            do_settings_sections('kwetupizza-settings');
            submit_button();
            ?>
        </form>
    </div>
    <style>
        /* Basic styling for two-column layout */
        .kwetu-settings-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .kwetu-settings-left, .kwetu-settings-right {
            width: 48%;
        }

        @media (max-width: 768px) {
            .kwetu-settings-left, .kwetu-settings-right {
                width: 100%;
            }
        }

        /* Styling for tabs in the right column */
        .kwetu-settings-right .nav-tabs {
            display: flex;
            border-bottom: 2px solid #ddd;
        }

        .kwetu-settings-right .nav-tabs li {
            margin-right: 10px;
            list-style: none;
        }

        .kwetu-settings-right .nav-tabs li a {
            display: block;
            padding: 10px;
            border: 1px solid #ddd;
            border-bottom: none;
            text-decoration: none;
        }

        .kwetu-settings-right .nav-tabs li.active a {
            background-color: #f1f1f1;
            border-color: #ddd;
            border-bottom: none;
        }

        .kwetu-settings-right .tab-content {
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: -1px;
        }
    </style>

    <div class="kwetu-settings-container">
        <!-- Left Column: Restaurant Configuration -->
        <div class="kwetu-settings-left">
            <h2>Restaurant Configurations</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Restaurant Location</th>
                    <td><input type="text" name="kwetupizza_location" value="<?php echo get_option('kwetupizza_location'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">Base Currency</th>
                    <td><input type="text" name="kwetupizza_currency" value="<?php echo get_option('kwetupizza_currency'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">Google Delivery Area</th>
                    <td><input type="text" name="kwetupizza_delivery_area" value="<?php echo get_option('kwetupizza_delivery_area'); ?>" /></td>
                </tr>
                <!-- Add more restaurant configurations as needed -->
            </table>
        </div>

        <!-- Right Column: Integrations with Tabs -->
        <div class="kwetu-settings-right">
            <h2>Integrations</h2>

            <ul class="nav-tabs">
                <li class="active"><a href="#whatsapp-tab">WhatsApp Provider</a></li>
                <li><a href="#payment-tab">Payment Gateway</a></li>
                <li><a href="#sms-tab">Bulk SMS</a></li>
            </ul>

            <div class="tab-content">
                <!-- WhatsApp Cloud API Integration -->
                <div id="whatsapp-tab" class="tab-pane active">
                    <h3>WhatsApp Cloud API Integration</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Access Token</th>
                            <td><input type="text" name="kwetupizza_whatsapp_token" value="<?php echo get_option('kwetupizza_whatsapp_token'); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Phone ID</th>
                            <td><input type="text" name="kwetupizza_whatsapp_phone_id" value="<?php echo get_option('kwetupizza_whatsapp_phone_id'); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Verify Token</th>
                            <td><input type="text" name="kwetupizza_whatsapp_verify_token" value="<?php echo get_option('kwetupizza_whatsapp_verify_token'); ?>" /></td>
                        </tr>
                        <!-- More WhatsApp settings if necessary -->
                    </table>
                </div>

                <!-- Flutterwave Payment Integration -->
                <div id="payment-tab" class="tab-pane">
                    <h3>Flutterwave Payment Gateway</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Public Key</th>
                            <td><input type="text" name="kwetupizza_flutterwave_public_key" value="<?php echo get_option('kwetupizza_flutterwave_public_key'); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Secret Key</th>
                            <td><input type="text" name="kwetupizza_flutterwave_secret_key" value="<?php echo get_option('kwetupizza_flutterwave_secret_key'); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Encryption Key</th>
                            <td><input type="text" name="kwetupizza_flutterwave_encryption_key" value="<?php echo get_option('kwetupizza_flutterwave_encryption_key'); ?>" /></td>
                        </tr>
                        <!-- Add callback URL or secret hash fields -->
                    </table>
                </div>

                <!-- BeemAfrica Bulk SMS Integration -->
                <div id="sms-tab" class="tab-pane">
                    <h3>BeemAfrica Bulk SMS Integration</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">API Key</th>
                            <td><input type="text" name="kwetupizza_beem_api_key" value="<?php echo get_option('kwetupizza_beem_api_key'); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Secret Key</th>
                            <td><input type="text" name="kwetupizza_beem_secret_key" value="<?php echo get_option('kwetupizza_beem_secret_key'); ?>" /></td>
                        </tr>
                        <!-- Add more BeemAfrica settings if necessary -->
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Register the settings
function kwetupizza_register_settings() {
    // Restaurant Configurations
    register_setting('kwetupizza_settings_group', 'kwetupizza_location');
    register_setting('kwetupizza_settings_group', 'kwetupizza_currency');
    register_setting('kwetupizza_settings_group', 'kwetupizza_delivery_area');

    // WhatsApp Cloud API settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_token');
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_phone_id');
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_verify_token');

    // Flutterwave settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_flutterwave_public_key');
    register_setting('kwetupizza_settings_group', 'kwetupizza_flutterwave_secret_key');
    register_setting('kwetupizza_settings_group', 'kwetupizza_flutterwave_encryption_key');

    // BeemAfrica SMS settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_beem_api_key');
    register_setting('kwetupizza_settings_group', 'kwetupizza_beem_secret_key');
}
add_action('admin_init', 'kwetupizza_register_settings');
?>
