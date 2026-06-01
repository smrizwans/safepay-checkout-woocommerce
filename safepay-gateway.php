<?php

/*
  Plugin Name:  Safepay for WooCommerce
  Plugin URI:   https://github.com/getsafepay/safepay-woocommerce
  Description:  Safepay Payment Gateway Integration for WooCommerce.
  Version:      3.0
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
 * 
 *
 * @class WC_SafePay_Payments
 */
class WC_SafePay_Payments
{

	/**
	 * Plugin bootstrapping.
	 */
	public static function init()
	{
		add_action('plugins_loaded', array(__CLASS__, 'includes'), 0);
		add_filter('woocommerce_payment_gateways', array(__CLASS__, 'add_gateway'));
		add_action('woocommerce_blocks_loaded', array(__CLASS__, 'woocommerce_safepay_gateway_woocommerce_block_support'));
		add_action('woocommerce_safepay_gateway_process_payment_order_status', 'pending');
		add_action('init', 'custom_register_order_status');
		add_filter('wc_order_statuses', 'safepay_custom_order_status');
	}


	public static function add_gateway($gateways)
	{

		$options = get_option('safepay_gateway_settings', array());

		if (isset($options['hide_for_non_admin_users'])) {
			$hide_for_non_admin_users = $options['hide_for_non_admin_users'];
		} else {
			$hide_for_non_admin_users = 'no';
		}

		if (('yes' === $hide_for_non_admin_users && current_user_can('manage_options'))
			|| 'no' === $hide_for_non_admin_users
		) {
			$gateways[] = 'WC_safepay_Gateway';
		}
		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes()
	{

		if (class_exists('WC_Payment_Gateway')) {
			require_once 'includes/class-wc-safepay-gateway.php';
			require_once 'includes/class-wc-safepay-request.php';
			require_once 'includes/class-wc-safepay-response.php';
		}
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url()
	{
		return untrailingslashit(plugins_url('/', __FILE__));
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_abspath()
	{
		return trailingslashit(plugin_dir_path(__FILE__));
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public static function woocommerce_safepay_gateway_woocommerce_block_support()
	{
		if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
			require_once 'includes/blocks/class-wc-safepay-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
					$payment_method_registry->register(new WC_safepay_Gateway_Blocks_Support());
				}
			);
		}
	}
}

function custom_register_order_status()
{
	register_post_status('wc-payment-received', array(
		'label'                     => 'Payment Received',
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop('Payment Received <span class="count">(%s)</span>', 'Payment Received <span class="count">(%s)</span>')
	));
}

function safepay_custom_order_status($order_statuses)
{
	$order_statuses['wc-payment-received'] = 'Payment Received';
	return $order_statuses;
}

WC_safepay_Payments::init();
