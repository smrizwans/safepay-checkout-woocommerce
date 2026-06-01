<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends API requests to Safepay.
 */
class SafepayAPIHandler
{

    public function buildRequestParams(object $data): array
    {
        if (!isset($data->securedKey)) {
            throw new InvalidArgumentException('Secured key is required.');
        }
        if (!isset($data->params)) {
            throw new InvalidArgumentException('Params are required.');
        }

        return array(
            'method' => $data->method ?? 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-SFPY-MERCHANT-SECRET' => $data->securedKey,
            ),
            'body' => json_encode($data->params),
        );
    }

    function build_metadata_payload($params, $securedKey)
    {
        if (empty($params['source']) || empty($params['order_id'])) {
            return new WP_Error('invalid_params', 'Source or Order ID missing', array('status' => 400));
        }

        return array(
            'method'  => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-SFPY-MERCHANT-SECRET' => $securedKey,
            ),
            'body' => json_encode([
                'data' => [
                    "source"   => (string) $params['source'],
                    "order_id" => (string) $params['order_id'],
                ]
            ]),
        );
    }

    function make_transaction_request($baseURL, $args)
    {
        return wp_remote_post(
            esc_url_raw($baseURL . SafepayEndpoints::TRANSACTION_ENDPOINT->value),
            array_merge($args, ['timeout' => 30])
        );
    }

    /**
     * FIX 5: Improved error logging on every failure branch.
     * Logs HTTP status code, raw response body, and wp_remote_post errors
     * so environment-specific API failures are diagnosable from wp-content/debug.log.
     */
    public function fetchToken($securedKey, $params, $baseURL)
    {
        $payload = (object) [
            'method'     => 'POST',
            'securedKey' => $securedKey,
            'params'     => $params,
        ];
        $args = self::buildRequestParams($payload);

        // --- Step 1: Fetch user token ---
        $tokenUrl = esc_url_raw($baseURL . SafepayEndpoints::TOKEN_ENDPOINT->value);
        $responseData = wp_remote_post($tokenUrl, array_merge($args, ['timeout' => 30]));

        if (is_wp_error($responseData)) {
            error_log(sprintf(
                '[Safepay] Token endpoint wp_error: %s | URL: %s',
                $responseData->get_error_message(),
                $tokenUrl
            ));
            return array(false, $responseData->get_error_message(), null);
        }

        $tokenHttpCode = wp_remote_retrieve_response_code($responseData);
        $userToken = json_decode(wp_remote_retrieve_body($responseData), true);

        if ($tokenHttpCode !== 200 && $tokenHttpCode !== 201) {
            error_log(sprintf(
                '[Safepay] Token endpoint returned HTTP %d | URL: %s | Body: %s',
                $tokenHttpCode,
                $tokenUrl,
                wp_remote_retrieve_body($responseData)
            ));
            return array(false, null, $tokenHttpCode);
        }

        // --- Step 2: Create transaction / tracker ---
        $transactionUrl = esc_url_raw($baseURL . SafepayEndpoints::TRANSACTION_ENDPOINT->value);
        $response = self::make_transaction_request($baseURL, $args);

        if (is_wp_error($response)) {
            error_log(sprintf(
                '[Safepay] Transaction endpoint wp_error: %s | URL: %s',
                $response->get_error_message(),
                $transactionUrl
            ));
            return array(false, $response->get_error_message(), null);
        }

        $txHttpCode = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($txHttpCode !== 201) {
            error_log(sprintf(
                '[Safepay] Transaction endpoint returned HTTP %d | URL: %s | Body: %s',
                $txHttpCode,
                $transactionUrl,
                wp_remote_retrieve_body($response)
            ));
            return array(false, null, $txHttpCode);
        }

        // --- Step 3: Post metadata (non-blocking; failure does not abort checkout) ---
        $trackerToken = $result['data']['tracker']['token'] ?? '';
        if (!empty($trackerToken)) {
            $metaPayload = self::build_metadata_payload($params, $securedKey);
            $metaDataEndpoint = esc_url_raw($baseURL . SafepayEndpoints::META_DATA_ENDPOINT->value);
            $metaResponse = self::make_metadata_request($metaDataEndpoint, $trackerToken, $metaPayload);

            if (is_wp_error($metaResponse)) {
                // Non-fatal: log and continue — metadata failure should not block payment
                error_log(sprintf(
                    '[Safepay] Metadata endpoint wp_error: %s',
                    $metaResponse->get_error_message()
                ));
            }
        }

        return array(true, $userToken, $result);
    }

    function make_metadata_request($metaDataEndpoint, $trackerToken, $meta_payload)
    {
        if (is_wp_error($meta_payload)) {
            error_log('[Safepay] Metadata payload error: ' . $meta_payload->get_error_message());
            return $meta_payload;
        }

        $sanitizedToken = sanitize_text_field($trackerToken);
        $endpointUrl    = esc_url_raw($metaDataEndpoint . '/' . $sanitizedToken . '/metadata');
        $response       = wp_remote_post($endpointUrl, array_merge($meta_payload, ['timeout' => 30]));

        if (is_wp_error($response)) {
            return $response;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
