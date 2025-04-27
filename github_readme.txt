=== KwetuPizza Plugin ===
Contributors: yourusername
Tags: pizza, whatsapp, ordering, food, delivery
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A pizza order management plugin with custom database structure, WhatsApp bot integration, and webhook callback URL auto-generation.

== Description ==

KwetuPizza Plugin enables businesses to accept and manage pizza orders through WhatsApp with integrated mobile payment processing through Flutterwave.

**Key Features:**

* **WhatsApp Integration:** Allow customers to order directly through WhatsApp messages
* **Payment Processing:** Seamless payment through mobile money (M-Pesa, TigoPesa, Airtel Money, HaloPesa)
* **Order Management:** Track and manage orders through the WordPress admin panel
* **Webhook System:** Reliable webhooks for both WhatsApp and payment processing
* **Interactive Menus:** Display interactive menus with images and buttons
* **Automatic Updates:** Plugin updates automatically from GitHub repository

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/kwetu-pizza-plugin` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings with your WhatsApp Business API and Flutterwave credentials
4. Set up the webhooks as instructed in the settings page

== Frequently Asked Questions ==

= Does this plugin require a WhatsApp Business API account? =

Yes, you need a WhatsApp Business API account to use this plugin. The plugin integrates with the WhatsApp Business API for sending and receiving messages.

= Which payment methods are supported? =

The plugin currently supports mobile money payments through Flutterwave, including M-Pesa, TigoPesa, Airtel Money, and HaloPesa.

= Can I customize the menu items? =

Yes, you can add, edit, and remove menu items through the WordPress admin panel.

== Changelog ==

= 1.3 =
* Added GitHub updater functionality
* Fixed WhatsApp webhook verification issues
* Improved order tracking notifications

= 1.2 =
* Added integration with NextSMS for SMS notifications
* Improved payment processing reliability
* Fixed bug in order tracking

= 1.1 =
* Enhanced WhatsApp menu navigation
* Added support for multiple delivery addresses
* Fixed error in payment confirmation flow
* Improved logging system

= 1.0 =
* Initial release
* Basic WhatsApp ordering flow
* Payment processing with Flutterwave
* Order management dashboard

== Upgrade Notice ==

= 1.3 =
This version adds automatic updates from GitHub and fixes several critical issues with WhatsApp webhook verification.

== Screenshots ==

1. Order management dashboard
2. WhatsApp conversation flow
3. Payment processing interface
4. Webhook configuration screen 