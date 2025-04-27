# KwetuPizza WhatsApp Order System

A WordPress plugin that allows customers to order pizza through WhatsApp with integrated mobile payment processing through Flutterwave.

## Features

- **WhatsApp Integration**: Order directly through WhatsApp messages
- **Payment Processing**: Seamless payment through mobile money (M-Pesa, TigoPesa, Airtel Money, HaloPesa)
- **Order Management**: Track and manage orders through the WordPress admin panel
- **Webhook System**: Reliable webhooks for both WhatsApp and payment processing
- **Interactive Menus**: Display interactive menus with images and buttons

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- SSL certificate (for secure webhook communication)
- WhatsApp Business API account
- Flutterwave merchant account

## Installation

1. Upload the plugin files to the `/wp-content/plugins/kwetu-pizza-plugin` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings with your WhatsApp Business API and Flutterwave credentials
4. Set up the webhooks as instructed in the settings page

## Configuration

### WhatsApp API Setup

1. Go to the plugin settings page
2. Enter your WhatsApp Business API credentials
3. Configure the webhook URL and verification token
4. Test the connection using the built-in testing tool

### Flutterwave Setup

1. Enter your Flutterwave API keys
2. Configure the webhook secret
3. Set up your supported payment methods

## Usage

Once configured, customers can interact with your WhatsApp number to:
- Browse menu
- Add items to cart
- Specify delivery address
- Make payment
- Track order status

## Support

For support, please contact [your-support-email@example.com](mailto:your-support-email@example.com)

## License

GPL v2 or later 