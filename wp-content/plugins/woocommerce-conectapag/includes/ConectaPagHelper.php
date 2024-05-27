<?php

class ConectaPagHelper
{
    private $clientId;
    private $clientSecret;

    public function __construct($_clientId, $_clientSecret)
    {
        $this->clientId = $_clientId;
        $this->clientSecret = $_clientSecret;

        $this->getPaymentMethods();
    }

    public function setCache()
    {
        // delete caches
        delete_transient('_gateway_token_cached');
        delete_transient('_gateway_payment_methods_cached');

        $token = $this->getToken();

        if (is_wp_error($token)) {
            // show error message
            echo '<div class="notice notice-error"><p>' . $token->get_error_message() . '</p></div>';
            return;
        }
    }

    private function getToken()
    {
        // delete_transient('_gateway_token_cached');
        $token = get_transient('_gateway_token_cached');

        if ($token === false) {
            $url = GATEWAY_URL_API . '/token';
            $params = [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if (!$response) {
                // delete cache if exist
                delete_transient('_gateway_token_cached');
                error_log('Clean token cache');

                return new WP_Error('payment_error', __('Gateway error [3387] ', 'conectapag-payment-woo') . $status_code);
            }

            $response_data = json_decode($response, true);

            if (!isset($response_data['token'])) {
                // delete cache if exist
                delete_transient('_gateway_token_cached');
                error_log('Clean token cache');

                return new WP_Error('payment_error', __('Unable to communicate with payment gateway.', 'conectapag-payment-woo'));
            }

            set_transient('_gateway_token_cached', $response_data['token'], $response_data['expires_in']);
            $token = $response_data['token'];
        }

        return $token;
    }

    public function getPaymentMethods()
    {
        $data = get_transient('_gateway_payment_methods_cached');

        if ($data === false) {
            $token = $this->getToken();

            if (is_wp_error($token)) {
                echo '<div class="notice notice-error"><p>' . $token->get_error_message() . '</p></div>';
                return;
            }

            error_log($token);
            // wp_die('foi');

            if (!$token)
                return null;

            $url = GATEWAY_URL_API . '/settings/payment-methods';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$token}"
            ]);

            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            error_log(json_encode($response));

            if (!$response) {
                // delete cache if exist
                delete_transient('_gateway_payment_methods_cached');

                return new WP_Error('payment_error', __('Gateway error [3388] ', 'conectapag-payment-woo') . $status_code);
            }

            $responseData = json_decode($response, true);

            if (!$responseData || !isset($responseData['success']) || !$responseData['success']) {
                // delete cache if exist
                delete_transient('_gateway_payment_methods_cached');

                return new WP_Error('payment_error', __('Error fetching payment methods.', 'conectapag-payment-woo'));
            }

            set_transient('_gateway_payment_methods_cached', $responseData, 12 * HOUR_IN_SECONDS);
            $data = $responseData;
        }

        return $data;
    }

    public function getHashByKey($key)
    {
        $methods = $this->getPaymentMethods();

        if (is_wp_error($methods)) {
            echo '<div class="notice notice-error"><p>' . $methods->get_error_message() . '</p></div>';
            return null;
        }

        if (isset($methods['data']) && array_key_exists($key, $methods['data'])) {
            foreach ($methods['data'][$key] as $elemento) {
                if (array_key_exists('hash', $elemento)) {
                    return $elemento['hash'];
                }
            }
        }

        return null;
    }

    public function sendTransaction($payload)
    {
        try {
            $token = $this->getToken();

            if (is_wp_error($token))
                throw new Exception("Error[{$token->get_error_code()}]: {$token->get_error_message()}");

            $url = GATEWAY_URL_API . '/transaction';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$token}"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            $response = json_decode($response, true);

            // error_log($status_code);
            // error_log(json_encode($response));

            if (!$response || !$response['success'])
                throw new Exception("Error status code: {$status_code}");

            return ['success' => true, 'data' => $response['data']];
        } catch (Exception $e) {
            error_log("{$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendWithdraw($payload)
    {
        try {
            $token = $this->getToken();

            if (is_wp_error($token))
                throw new Exception("Error[{$token->get_error_code()}]: {$token->get_error_message()}");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, GATEWAY_URL_API . '/withdraw');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                "Authorization: Bearer $token"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if (!$response)
                throw new Exception("Error status code: {$status_code}");

            return json_decode($response, true);
        } catch (Exception $e) {
            error_log("{$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
