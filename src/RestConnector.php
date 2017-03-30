<?php

namespace VseMayki;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client as Guzzle;

/**
 * Class RestConnector
 */
class RestConnector
{
    private $url = 'http://rest.vsemayki.ru';
    private $clientId;
    private $clientSecret;

    public $token = false;

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

            if ($token) {
                $options = ['query' => ['access-token' => $token]];
            }

            switch ($method) {
                case 'POST':
                    $options = array_merge($options, ['form_params' => $params]);
                    break;
                case 'GET':
                    $options = ['query' => array_merge($options['query'], $params)];
                    break;
            }

            $requestResult = $client->request($method, $url, $options);
            $result        = [
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
        return ($this->token) ?: $this->updateToken();
    }

    /**
     * @return bool|string
     */
    public function updateToken()
    {
        $this->token = false;
        $result      = $this->makeRequest('/oauth2/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials'
        ], 'POST');

        if (!empty($result['body']->access_token)) {
            $this->token = $result['body']->access_token;
        }

        return $this->token;
    }
}