<?php
namespace VseMayki;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client as Guzzle;
/**
 * Class RestConnector
 */
class RestConnector
{
//    private $url = 'http://rest.vsemayki.ru';
    private $url = 'http://rest.staging.vsemayki.com';
    private $clientId;
    private $clientSecret;

    public static $token;

    public function __construct($clientId, $clientSecret)
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param string $url
     * @param array  $params
     * @param string $method
     *
     * @return mixed
     * @throws \Exception
     */
    public function sendRequest($url, array $params = [], $method = 'GET')
    {
        $result = $this->makeRequest($url, $params, $method, $this->getToken());

        if (!in_array($result['code'], [200, 201, 204], false)) {
            if (in_array($result['code'], [401, 403], false)) {
                $this->updateToken();

                return $this->sendRequest($url, $params, $method);
            }
        }

        return $result['body'] ?: [];
    }

    /**
     * @param string $url
     * @param array  $params
     * @param string $method
     * @param null   $token
     *
     * @return array
     */
    private function makeRequest($url, array $params = [], $method = 'GET', $token = null)
    {
        try {
            $options = [];
            $client  = new Guzzle(['base_uri' => $this->url]);

            switch ($method) {
                case 'POST':
                    if ($token) {
                        $options = ['query' => ['access-token' => $token]];
                    }
                    $options = array_merge($options, ['form_params' => $params]);
                    break;
                case 'GET':
                    $options = ['form_params' => $params];
                    break;
            }

            $requestResult = $client->request($method, $url, $options);
            $result = [
                'code' => $requestResult->getStatusCode(),
                'body' => json_decode($requestResult->getBody()),
            ];
        } catch (ClientException $e) {
            $result = [
                'code' => $e->getCode(),
                'body' => $e->getMessage(),
            ];
        }

        return $result;
    }

    /**
     * @return bool|string
     */
    public function getToken()
    {
        return (static::$token) ?: $this->updateToken();
    }

    /**
     * @return bool|string
     */
    public function updateToken()
    {
        static::$token = false;
        $result        = $this->makeRequest('/oauth2/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials'
        ], 'POST');

        if (!empty($result->access_token)) {
            static::$token = $result->access_token;
        }

        return static::$token;
    }
}