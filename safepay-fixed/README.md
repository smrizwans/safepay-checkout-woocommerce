<!-- === Safepay for WooCommerce ===
Contributors: safepay
Tags: safepay, payments, pakistan, woocommerce, ecommerce
Requires at least: 3.9.2
Tested up to: 6.5
Stable tag: 2.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows you to use Safepay Checkout with the WooCommerce plugin.

== Description ==

This is the official Safepay Checkout plugin for WooCommerce. It allows you to accept credit cards and debit cards with the WooCommerce plugin. It uses a seamless integration, allowing the customer to pay on your website. This works across all browsers, and is compatible with the latest WooCommerce.

== Installation ==

1. Install the plugin from the [Wordpress Plugin Directory](https://wordpress.org/plugins/safepay-woocommerce/).
2. To use this plugin correctly, you need to be able to make network requests. Please make sure that you have the php-curl extension installed.

== Dependencies ==

1. Wordpress v3.9.2 and later
2. Woocommerce v3.1 and later
3. PHP v7.4.0 and later
4. php-curl extension

== Configuration ==

1. Visit the WooCommerce settings page, and click on the Checkout/Payment Gateways tab.
2. Click on Safepay to edit the settings. If you do not see Safepay in the list at the top of the screen make sure you have activated the plugin in the WordPress Plugin Manager.
3. Enable the Payment Method, name it Pay with Credit & Debit Cards (this will show up on the payment page your customer sees), add in your API keys and Webhook Secrets for both Sandbox and Production environments.
4. Toggle between test payments and live payments by selecting the Environment dropdown (Development / Sandbox / Production).

== Upgrade Notice ==

= 2.3 =
Critical fix for checkout failure on affected stores. Update immediately.

== Changelog ==

= 2.3 — May 2026 =
* Fix: Replaced wp_die() with wc_add_notice() + return null inside generateSafepayRedirect(). wp_die() was terminating PHP execution on API failure, destroying the WooCommerce session and displaying a misleading "session expired" message to customers. WooCommerce now handles payment errors gracefully — session and nonce remain intact and customers can retry without refreshing.
* Fix: Removed WC()->session->destroy_session() from the failure path in process_payment(). Premature session destruction was causing genuine session expiry errors on retry attempts. WooCommerce now manages its own session lifecycle post-redirect.
* Fix: Removed ob_start() / ob_end_flush() from process_payment() and the webhook handler. Output buffering inside WooCommerce AJAX hooks can corrupt responses on certain hosting environments.
* Fix: Added is_wp_error() checks on all wp_remote_post() and wp_remote_get() calls in SafePayApiHandler. Metadata endpoint failure is now non-blocking and will not abort the checkout flow.
* Improvement: Added structured [Safepay] error logging across all API failure paths. Every failure now logs the HTTP status code and full response body to the WooCommerce log, making environment-specific issues diagnosable in minutes.
* Improvement: Changed merchant_secret_key and merchant_webhook_secret admin fields from type text to type password. API credentials are no longer visible in plain text in the WP Admin UI.
* Improvement: process_payment() now returns array('result' => 'failure') with a wc_add_notice() error on all failure paths instead of falling through silently.

= 2.2 =
* Added WooCommerce Blocks support for the new checkout experience.
* Added support for multiple currencies: PKR, USD, GBP, AED, EUR, CAD, SAR.
* Added store environment selector: Development, Sandbox, Production.
* Refactored gateway into SafepayGateway and SafepayAPIHandler classes.

= 1.0.7 =
* Tested with latest releases of Wordpress and Woocommerce.

= 1.0.6 =
* Added logging for more robust debugging.

= 1.0.5 =
* Added a fix to use the correct named method on payment complete.

= 1.0.2 =
* Added a fix for Woocommerce nonce checks.

= 1.0.1 =
* Added reference code to order meta data.

= 1.0.0 =
* Redirects customer to payments page on clicking Place Order, as per WooCommerce guidelines.
* Redirects customer to order details page, as per WooCommerce guidelines.

== Frequently Asked Questions ==

= Who can use this? =

Currently only merchants and store operators in Pakistan can use this plugin.

= Who can pay using this? =

Any customer with a valid Visa or MasterCard credit or debit card can make payments.

= What currencies are supported? =

PKR, USD, GBP, AED, EUR, CAD, and SAR are supported.

== Support ==

Visit our [knowledge center](https://safepay.helpscoutdocs.com/) for detailed guides on how to use Safepay as a merchant.

== Screenshots ==
1. Configuring your plugin
2. What a customer sees on checkout. -->
