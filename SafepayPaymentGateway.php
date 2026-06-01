<?php

/*
  Plugin Name:  Safepay for WooCommerce
  Plugin URI:   https://github.com/getsafepay/safepay-woocommerce
  Description:  Safepay Payment Gateway Integration for WooCommerce.
  Version:      2.3
  Author:       Team Safepay
  Author URI:   https://getsafepay.com
  License:      GPL-2.0+
  License URI:  http://www.gnu.org/licenses/gpl-2.0.txt
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Safepay WooCommerce Payment Integration
 * 
 * @class SafepayPaymentGateway
 */
class SafepayPaymentGateway {

    /**
     * Initialize the plugin.
     */
    public static function safepay_init() {
        add_action('plugins_loaded', array(__CLASS__, 'safepay_includes'), 0);
        add_action('woocommerce_blocks_loaded', array(__CLASS__, 'safepay_woocommerce_block_support'));
        add_filter('woocommerce_payment_gateways', array(__CLASS__, 'safepay_add_gateway'));
        add_action('safepay_wc_process_payment_order_status', array(__CLASS__, 'safepay_pending_order_status'));
    }

    /**
     * Add Safepay gateway to WooCommerce gateways.
     */
    public static function safepay_add_gateway($gateways) {
        $options = get_option('safepay_gateway_settings', array());

        $hide_for_non_admin_users = isset($options['hide_for_non_admin_users']) ? $options['hide_for_non_admin_users'] : 'no';

        if (( 'yes' === $hide_for_non_admin_users && current_user_can('manage_options')) || 'no' === $hide_for_non_admin_users) {
            $gateways[] = 'SafepayGateway';
        }

        return $gateways;
    }

    /**
     * Plugin includes.
     */
    public static function safepay_includes() {
        if (class_exists('WC_Payment_Gateway')) {
            require_once 'includes/enums/SafepayEndpoints.php';
            require_once 'includes/SafepayGateway.php';
            require_once 'includes/SafePayApiHandler.php';
        }
    }

    /**
     * Get plugin URL.
     *
     * @return string
     */
    public static function safepay_plugin_url() {
        return untrailingslashit(plugins_url('/', __FILE__));
    }

    /**
     * Get plugin absolute path.
     *
     * @return string
     */
    public static function safepay_plugin_abspath() {
        return trailingslashit(plugin_dir_path(__FILE__));
    }

    public static function safepay_woocommerce_block_support() {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once 'includes/blocks/SafepayPaymentsBlocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new SafepayGatewayBlocksSupport());
                }
            );
        }
    }

}


// Initialize Safepay Plugin
SafepayPaymentGateway::safepay_init();
