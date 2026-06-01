<?php

if (!defined('ABSPATH')) {
    exit;
}

class SafepayGateway extends WC_Payment_Gateway
{

    public $icon;
    public $has_fields;
    public $supports;
    public $method_title;
    public $method_description;
    public $title;
    public $description;
    public $instructions;
    public $hide_for_non_admin_users;
    public $merchantApiKey;
    public $merchantSecret;
    public $merchantWebhookSecretKey;
    public $storeId;
    public $baseUrl;
    public $appEnv;
    public $siteUrl;

    public function initialize_gateway_properties()
    {
        $this->icon = apply_filters('woocommerce_safepay_gateway_icon', '');
        $this->has_fields = false;
        $this->supports = ['products'];

        $this->method_title = _x('Safepay', 'Safepay Checkout', 'woocommerce-safepay-gateway');
        $this->method_description = __('Pay with your credit & debit cards', 'woocommerce-safepay-gateway');
    }

    public function initialize_options()
    {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions', $this->description);
        $this->hide_for_non_admin_users = $this->get_option('hide_for_non_admin_users');

        $this->merchantApiKey = $this->get_option('merchant_api_key');
        $this->merchantSecret = $this->get_option('merchant_secret_key');
        $this->merchantWebhookSecretKey = $this->get_option('merchant_webhook_secret');
        $this->storeId = $this->get_option('store_id');
        $this->appEnv = $this->get_option('app_env');
        $this->siteUrl = get_site_url();
    }

    public $id = 'safepay_gateway';

    public function __construct()
    {
        $this->initialize_options();
        $this->initialize_gateway_properties();
        $this->register_hooks();
        $this->register_rest_routes();
    }

    private function register_hooks()
    {
        add_filter('woocommerce_gateway_icon', [$this, 'safepay_display_woocommerce_icons'], 10, 2);
        add_filter('woocommerce_gateway_title', [$this, 'safepay_payment_method_title'], 10, 2);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'safepay_process_order_request']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    private function register_rest_routes()
    {
        $namespace = 'safepay/v1';
        $permission_callback = '__return_true';
        add_action('rest_api_init', function () use ($namespace, $permission_callback) {
            $routes = [
                [
                    'route'   => '/order-webhook',
                    'methods' => 'POST',
                    'callback' => [$this, 'safepay_handle_webhook'],
                ],
            ];
            foreach ($routes as $route) {
                register_rest_route($namespace, $route['route'], [
                    'methods'             => $route['methods'],
                    'callback'            => $route['callback'],
                    'permission_callback' => $permission_callback,
                ]);
            }
        });
    }

    function safepay_display_woocommerce_icons($icon, $id)
    {
        if ($this->id == $id) {
            $imagePath = sprintf("%s/assets/images/logo.svg", plugin_dir_url(dirname(__FILE__)));
            $icon = '<img width="25%" src="' . $imagePath . '" alt="safepay" />';
        }
        return $icon;
    }

    function safepay_payment_method_title($title, $id)
    {
        if ($this->id === $id) {
            $title = $this->title;
        }
        return $title;
    }

    private function get_env_url()
    {
        switch ($this->appEnv) {
            case 'development':
                return SafepayEndpoints::DEVELOPMENT_BASE_URL->value;
            case 'sandbox':
                return SafepayEndpoints::SANDBOX_BASE_URL->value;
            default:
                return SafepayEndpoints::PRODUCTION_BASE_URL->value;
        }
    }

    public function is_valid_for_use()
    {
        return in_array(
            get_woocommerce_currency(),
            apply_filters(
                'woocommerce_paypal_supported_currencies',
                array('PKR', 'USD', 'GBP', 'AED', 'EUR', 'CAD', 'SAR')
            ),
            true
        );
    }

    public function validate_webhook($payload)
    {
        if (!isset($_SERVER['HTTP_X_SFPY_SIGNATURE'])) {
            error_log('[Safepay] Missing signature in webhook request.');
            return false;
        }

        $hook_data = $payload ?? null;
        $signature = $_SERVER['HTTP_X_SFPY_SIGNATURE'];
        $secret = $this->merchantWebhookSecretKey;

        if (is_null($hook_data)) {
            error_log('[Safepay] Missing data in webhook payload.');
            return false;
        }

        $generated_signature = hash_hmac('sha512', json_encode($hook_data, JSON_UNESCAPED_SLASHES), $secret);

        if (hash_equals($generated_signature, $signature)) {
            return true;
        }

        error_log('[Safepay] Webhook signature validation failed.');
        return false;
    }

    function safepay_handle_webhook($request)
    {
        $parameters = $request->get_json_params();

        if (!$this->validate_webhook($parameters)) {
            return new WP_Error('invalid_signature', 'Invalid signature', array('status' => 403));
        }

        if (empty($parameters) || !isset($parameters['data'])) {
            return new WP_Error('no_data', 'No data provided', array('status' => 400));
        }

        $data = $parameters['data'];
        $tracker = sanitize_text_field($data['tracker'] ?? '');
        $state = sanitize_text_field($data['state'] ?? '');
        $metadata = $data['metadata'] ?? array();
        $OrderId = absint($metadata['order_id'] ?? 0);
        $order = wc_get_order($OrderId);

        if (!$order) {
            return new WP_Error('invalid_order', 'Order not found', array('status' => 400));
        }

        if ($state === 'TRACKER_ENDED') {
            $order_note_message = 'Payment has been received successfully. Transaction reference ID: ' . $tracker;
            $order->payment_complete();
        } else {
            $order->update_status('failed');
            $order_note_message = 'Payment has failed. Transaction reference ID: ' . $tracker;
        }

        $order->add_order_note($order_note_message);
        $order->save();

        return array(
            'result' => ($state === 'TRACKER_ENDED') ? 'success' : 'failed',
            'redirect' => $this->get_return_url($order)
        );
    }

    public function prepareApiArguments($order)
    {
        return [
            "amount" => (int) ($order->get_total() * 100),
            "intent" => "CYBERSOURCE",
            "mode" => "payment",
            "currency" => get_woocommerce_currency() ?? 'PKR',
            "merchant_api_key" => $this->merchantApiKey,
            "order_id" => $order->get_id(),
            "source" => 'woocommerce'
        ];
    }

    public function prepareRedirectUrl($order, $userToken, $tracker)
    {
        $siteUrl = get_site_url();
        $order_id = $order->get_id();
        $redirect_url = esc_url_raw($order->get_checkout_order_received_url());
        $cancel_url = esc_url_raw($order->get_cancel_order_url());

        return sprintf(
            '%s/embedded/?tbt=%s&tracker=%s&order_id=%s&environment=%s&source=woocommerce&redirect_url=%s&cancel_url=%s',
            esc_url($this->appEnv == 'production' ? SafepayEndpoints::PRODUCTION_URL->value : $this->get_env_url()),
            esc_html($userToken),
            esc_html($tracker),
            esc_html($order_id),
            esc_html($this->appEnv),
            urlencode($redirect_url),
            urlencode($cancel_url)
        );
    }

    /**
     * FIX 1: Replace wp_die() with proper WooCommerce error notices.
     * FIX 2: Log the actual API failure reason for diagnostics.
     * FIX 3: Return null on failure — caller checks for null and returns 'failure' result.
     */
    public function generateSafepayRedirect($order)
    {
        $safepayApiHandler = new SafepayAPIHandler();
        $args = self::prepareApiArguments($order);
        $baseURL = self::get_env_url();

        list($success, $userToken, $result) = $safepayApiHandler->fetchToken($this->merchantSecret, $args, $baseURL);
        $tracker = $result['data']['tracker']['token'] ?? null;

        if ($success && !empty($tracker)) {
            $userToken = $userToken['data'] ?? null;
            return self::prepareRedirectUrl($order, $userToken, $tracker);
        }

        // Log the actual failure detail for server-side diagnosis
        $error_detail = is_array($result) ? json_encode($result) : (string) $result;
        error_log(sprintf(
            '[Safepay] API call failed for order #%d. Env: %s. Detail: %s',
            $order->get_id(),
            $this->appEnv,
            $error_detail
        ));

        // FIX 1: Use wc_add_notice() instead of wp_die() so WooCommerce
        // handles the error gracefully — session and nonce remain intact.
        wc_add_notice(
            __('Payment could not be initiated. Please try again or contact support.', 'woocommerce-safepay-gateway'),
            'error'
        );

        return null;
    }

    /**
     * FIX 2: Remove premature WC()->session->destroy_session() calls.
     *   - Session must NOT be destroyed on failure — doing so invalidates
     *     the nonce, which causes "session expired" on customer retry.
     *   - Session destruction on success path is also removed: WooCommerce
     *     manages session lifecycle internally after redirect.
     * FIX 3: Remove ob_start()/ob_end_flush() — not appropriate here and
     *   can interfere with WooCommerce's own output handling.
     * FIX 4: Return array('result'=>'failure') on null redirect instead of
     *   falling through silently.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            error_log(sprintf('[Safepay] process_payment: Order #%d not found.', $order_id));
            wc_add_notice(__('Order not found. Please try again.', 'woocommerce-safepay-gateway'), 'error');
            return array('result' => 'failure');
        }

        $payment_method = $order->get_payment_method();

        if ('safepay_gateway' !== $payment_method) {
            error_log(sprintf('[Safepay] process_payment: Unexpected payment method "%s" for order #%d.', $payment_method, $order_id));
            wc_add_notice(__('Payment method mismatch. Please try again.', 'woocommerce-safepay-gateway'), 'error');
            return array('result' => 'failure');
        }

        if ($order->get_status() === 'failed') {
            // Re-open a failed order so the customer can retry
            $order->update_status('pending', 'Customer retrying payment via Safepay.');
            $order->save();
        }

        $requestRedirectUrl = self::generateSafepayRedirect($order);

        // FIX 4: If API failed, generateSafepayRedirect returns null.
        // Return 'failure' so WooCommerce keeps session alive for retry.
        if (null === $requestRedirectUrl) {
            return array('result' => 'failure');
        }

        $order->update_status('pending', 'Order is awaiting Safepay payment.');
        $order->save();

        WC()->cart->empty_cart();
        // FIX 2: Do NOT call WC()->session->destroy_session() here.
        // WooCommerce will handle session cleanup after the redirect
        // and subsequent order-received page load.

        return array(
            'result'   => 'success',
            'redirect' => $requestRedirectUrl,
        );
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-safepay-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Safepay Checkout', 'woocommerce-safepay-gateway'),
                'description' => __('Enable or disable Safepay Checkout.', 'woocommerce-safepay-gateway'),
                'desc_tip' => false,
                'default' => 'yes'
            ),
            'app_env' => array(
                'title' => __('Environment', 'woocommerce-safepay-gateway'),
                'type' => 'select',
                'label' => __('Environment', 'woocommerce-safepay-gateway'),
                'description' => __('Choose the environment.', 'woocommerce-safepay-gateway'),
                'desc_tip' => false,
                'default' => 'production',
                'options' => array(
                    'development' => __('Development', 'woocommerce-safepay-gateway'),
                    'sandbox' => __('Sandbox', 'woocommerce-safepay-gateway'),
                    'production' => __('Production', 'woocommerce-safepay-gateway')
                )
            ),
            'title' => array(
                'title' => __('Title at checkout', 'woocommerce-safepay-gateway'),
                'type' => 'text',
                'description' => __('Title at checkout', 'woocommerce-safepay-gateway'),
                'desc_tip' => true,
                'default' => 'Safepay Checkout'
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-safepay-gateway'),
                'type' => 'text',
                'description' => __('Description', 'woocommerce-safepay-gateway'),
                'desc_tip' => true,
                'default' => 'Pay using your credit or debit card. Safepay supports all Visa and MasterCard credit and debit cards'
            ),
            'merchant_api_key' => array(
                'title' => __('Merchant API key', 'woocommerce-safepay-gateway'),
                'type' => 'text',
                'description' => __('Registered Merchant API Key at Safepay.', 'woocommerce-safepay-gateway'),
                'desc_tip' => true,
                'default' => ''
            ),
            'merchant_secret_key' => array(
                'title' => __('Merchant secret key', 'woocommerce-safepay-gateway'),
                'type' => 'password',
                'description' => __('Merchant\'s secret key.', 'woocommerce-safepay-gateway'),
                'desc_tip' => true,
                'default' => ''
            ),
            'merchant_webhook_secret' => array(
                'title' => __('Merchant webhook secret key', 'woocommerce-safepay-gateway'),
                'type' => 'password',
                'description' => __('Using merchant webhook secret keys allows Safepay to verify each payment.', 'woocommerce-safepay-gateway'),
                'desc_tip' => false
            ),
            'production_webhook_secret' => array(
                'title' => __('Webhook URL', 'woocommerce-safepay-gateway'),
                'type' => 'text',
                'description' =>
                    __('Using webhook secret keys allows Safepay to verify each payment. To get your live webhook key:')
                    . '<br /><br />' .
                    __('1. Navigate to your Live Safepay dashboard by clicking <a target="_blank" href="https://getsafepay.com/dashboard/webhooks">here</a>')
                    . '<br />' .
                    sprintf(__('2. Click \'Add an endpoint\' and paste the following URL: %s', 'Safepay'), add_query_arg('/wp-json/safepay/v1/order-webhook?', '', get_site_url()))
                    . '<br />' .
                    __('3. Make sure to select "Send me all events", to receive all payment updates.', 'Safepay')
                    . '<br />' .
                    __('4. Click "Show shared secret" and paste into the box above.', 'Safepay'),
                'desc_tip' => false,
                'default' => sprintf("%s/wp-json/safepay/v1/order-webhook", get_site_url()),
            ),
        );
    }
}
