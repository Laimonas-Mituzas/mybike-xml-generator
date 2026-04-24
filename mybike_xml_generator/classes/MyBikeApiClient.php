<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeApiClient
{
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getProducts($page = 1, array $filters = [])
    {
        $params = array_merge(['page' => $page, 'limit' => MYBIKE_API_LIMIT], $filters);
        return $this->get('/api/v1/products', $params);
    }

    public function getProduct($id)
    {
        return $this->get('/api/v1/products/' . (int)$id);
    }

    public function getImages($id)
    {
        return $this->get('/api/v1/products/' . (int)$id . '/images');
    }

    private function get($endpoint, array $params = [])
    {
        $url = MYBIKE_API_BASE_URL . $endpoint;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $lastError = '';

        for ($attempt = 1; $attempt <= MYBIKE_API_RETRY; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => MYBIKE_API_TIMEOUT,
                CURLOPT_HTTPHEADER     => ['X-API-Key: ' . $this->apiKey],
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $lastError = 'cURL: ' . $curlError;
                if ($attempt < MYBIKE_API_RETRY) {
                    sleep(1);
                }
                continue;
            }

            $data = json_decode($response, true);

            if ($httpCode === 401) {
                throw new Exception('MyBike API: Invalid or expired API key.');
            }

            if ($httpCode >= 400) {
                $msg = isset($data['message']) ? $data['message'] : 'HTTP ' . $httpCode;
                throw new Exception('MyBike API error: ' . $msg);
            }

            if (!isset($data['data'])) {
                $lastError = 'Invalid response format';
                if ($attempt < MYBIKE_API_RETRY) {
                    sleep(1);
                }
                continue;
            }

            return $data;
        }

        throw new Exception('MyBike API failed after ' . MYBIKE_API_RETRY . ' attempts on ' . $endpoint . ': ' . $lastError);
    }
}
