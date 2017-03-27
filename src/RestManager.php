<?php
namespace VseMayki;
/**
 * Class RestConnector
 */
class RestConnector
{
    private $url = 'http://rest.vsemayki.ru';
    private $clientId;
    private $clientSecret;
    private $lastHeaders;

    public function __construct($clientId, $clientSecret)
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param $clientId
     * @param $clientSecret
     */
    public function setClientData($clientId, $clientSecret)
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @return string
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @return array
     */
    public function getLastHeaders()
    {
        return $this->lastHeaders;
    }

    /**
     * Validate config connector
     *
     * @return bool
     */
    public function validateConnector()
    {
        return $this->url && $this->clientId && $this->clientSecret;
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

        if ($result['errno'] === CURLE_OPERATION_TIMEOUTED) {
            throw new Exception('API timeout.');
        }

        $info = $result['info'];

        if (!in_array($info['http_code'], [200, 201, 204], false)) {
            if (in_array($info['http_code'], [401, 403], false)) {
                $this->updateToken();

                return $this->sendRequest($url, $params, $method);
            }
        }

        return $result['data'] ?: [];
    }

    /**
     * @param string $url
     * @param array  $params
     * @param string $method
     * @param null   $token
     * @param array  $headers
     *
     * @return array
     */
    private function makeRequest($url, array $params = [], $method = 'GET', $token = null, $headers = [])
    {
        $query = [];

        if ($token) {
            $query['access-token'] = $token;
        }

        if ($query) {
            $url .= (strpos($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $httpHeaders = [];

        foreach ($headers as $key => $value) {
            $httpHeaders[] = $key . ': ' . $value;
        }

        $type         = CURLOPT_URL;
        $method       = strtoupper($method);
        $parsedParams = http_build_query($params);
        $url          = $this->url . $url;
        $ch           = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER         => false,
            CURLOPT_ENCODING       => 'gzip',
            CURLOPT_HTTPHEADER     => $httpHeaders,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                $type = CURLOPT_POSTFIELDS;
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                $type = CURLOPT_POSTFIELDS;
                break;
            case 'HEAD':
                curl_setopt_array($ch, [
                    CURLOPT_NOBODY      => true,
                    CURLOPT_HEADER      => true,
                    CURLOPT_VERBOSE     => true,
                    CURLINFO_HEADER_OUT => true,
                ]);
                $parsedParams = $url . '&' . $parsedParams;
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                $parsedParams = $url . '&' . $parsedParams;
                break;
        }

        if ($parsedParams) {
            curl_setopt($ch, $type, $parsedParams);
        }

        $response          = curl_exec($ch);
        $errno             = curl_errno($ch);
        $info              = curl_getinfo($ch);
        $this->lastHeaders = [];

        if (strtolower($method) === 'head' && $info['header_size'] > 0) {
            $response = substr($response, $info['header_size']);
        }

        curl_close($ch);

        return ['data' => json_decode($response, true), 'info' => $info, 'errno' => $errno];
    }

    /**
     * @return bool|string
     */
    public function getToken()
    {
        return $this->updateToken();
    }

    /**
     * @return bool|string
     */
    public function updateToken()
    {
        $token  = false;
        $result = $this->makeRequest('/oauth2/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials'
        ], 'POST');

        if (!empty($result['data']['access_token'])) {
            $token = $result['data']['access_token'];
        }

        return $token;
    }
}