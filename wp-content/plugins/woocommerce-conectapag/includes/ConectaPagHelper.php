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

    private function getToken()
    {
        $data = $this->getCache('_gateway_token');

        error_log(GATEWAY_URL_API);
        error_log($this->clientId);
        error_log($this->clientSecret);

        if ($data === false) {
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

            // error_log(json_encode($response));

            if (!$response) {
                error_log('Gateway error [3387] ' . $status_code);
                return null;
            }

            $response_data = json_decode($response, true);

            // $_SESSION["token_access"] = $response_data['token'];
            // $_SESSION["token_expires_in"] = time() + $response_data['expires_in'];
            // $_SESSION["token_type"] = $response_data['token_type'];

            $this->setCache('_gateway_token', $response_data['token'], $response_data['expires_in']);
        }

        return $data;
    }

    public function getPaymentMethods()
    {
        $data = $this->getCache('_gateway_payment_methods');

        if ($data === false) {
            $token = $this->getToken();

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
                error_log('Gateway error [3388] ' . $status_code);
                return null;
            }

            // error_log('Payments methods');
            // error_log($response);

            // $_SESSION["gateway_payment_methods"] = json_decode($response, true);

            $this->setCache('_gateway_payment_methods', json_decode($response, true));
        }

        return $data;
    }

    public function getHashByKey($key)
    {
        $array = $this->getPaymentMethods()['data'];

        error_log(json_encode($array));
        error_log(gettype($array));

        if (array_key_exists($key, $array)) {
            foreach ($array[$key] as $elemento) {
                if (array_key_exists('hash', $elemento)) {
                    return $elemento['hash'];
                }
            }
        }

        return null;
    }

    public function sendTransaction($payload)
    {
        $token = $this->getToken();

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

        if (!$response)
            return ['success' => false, 'message' => 'Error [3389]', 'code' => $status_code];

        return json_decode($response, true);
    }

    public function sendWithdraw($payload)
    {
        $token = $this->getToken();

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
            return ['success' => false, 'message' => 'Error [3390]', 'code' => $status_code];

        return json_decode($response, true);
    }

    private function setCache($key, $data, $duration = 86400)
    {
        $cacheDir = PLUGIN_PATH_GATEWAY . 'cache/';
        $cacheFile = $cacheDir . md5($key) . '.cache';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $expiryTime = time() + $duration;
        $cacheData = [
            'expiry' => $expiryTime,
            'data' => serialize($data)
        ];

        file_put_contents($cacheFile, json_encode($cacheData));
    }

    function getCache($key)
    {
        $cacheFile = PLUGIN_PATH_GATEWAY . 'cache/' . md5($key) . '.cache';

        if (!file_exists($cacheFile)) {
            return false;
        }

        $cacheData = json_decode(file_get_contents($cacheFile), true);

        if (time() > $cacheData['expiry']) {
            unlink($cacheFile);
            return false;
        }

        return unserialize($cacheData['data']);
    }
}