<?php

namespace Zenapply\HRIS\Paylocity;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Sainsburys\Guzzle\Oauth2\GrantType\ClientCredentials;
use Sainsburys\Guzzle\Oauth2\GrantType\RefreshToken;
use Sainsburys\Guzzle\Oauth2\Middleware\OAuthMiddleware;

class Paylocity
{
    const VERSION_1 = 'v1';
    const VERSION_2 = 'v2';
    const SANDBOX_URL = "apisandbox.paylocity.com";
    const PRODUCTION_URL = "api.paylocity.com";

    protected $api_key;
    protected $base_uri;
    protected $client;
    protected $resource;
    protected $version;

    public function __construct($client_id, $client_secret, $public_key_path, $host = self::PRODUCTION_URL, $version = self::VERSION_2)
    {
        $base = 'https://'.$host;
        $this->version = $version;
        $config = [
            ClientCredentials::CONFIG_CLIENT_ID => $client_id,
            ClientCredentials::CONFIG_CLIENT_SECRET  => $client_secret,
            ClientCredentials::CONFIG_TOKEN_URL => '/IdentityServer/connect/token',
            'scope' => 'WebLinkAPI',
        ];

        $oauthClient = new Client([
            'base_uri' => $base,
            'verify' => false
        ]);
        $grantType = new ClientCredentials($oauthClient, $config);
        $refreshToken = new RefreshToken($oauthClient, $config);
        $middleware = new OAuthMiddleware($oauthClient, $grantType, $refreshToken);

        $handlerStack = HandlerStack::create();
        $handlerStack->push($middleware->onBefore());
        $handlerStack->push($middleware->onFailure(5));

        $this->client = new Client([
            'handler'=> $handlerStack,
            'base_uri' => "{$base}/api/{$this->version}/",
            'auth' => 'oauth2',
            'verify' => false,
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);
        $this->resource = [];
        $this->public_key_path = $public_key_path;
    }

    public function __get($name)
    {
        $this->resource[] = $name;
        return $this;
    }

    public function __call($method, $args)
    {
        $this->resource[] = $method;
        foreach($args as $arg) {
            $this->resource[] = $arg;
        }
        return $this;
    }

    public function get(array $search = null)
    {
        return $this->all($search);
    }

    public function all(array $search = null)
    {
        $uri = implode("/", $this->resource);
        return $this->request('GET', $uri, [
            'query' => $search
        ]);
    }

    public function post(array $data = null)
    {
        // var_dump($this->encrypt($data));die();
        $uri = implode("/", $this->resource);
        return $this->request('POST', $uri, [
            "body" => $this->encrypt($data)
        ]);
    }

    public function put(array $data = null)
    {
        $uri = implode("/", $this->resource);
        return $this->request('PUT', $uri, [
            "body" => $this->encrypt($data)
        ]);
    }

    public function delete(array $data = null)
    {
        $uri = implode("/", $this->resource);
        return $this->request('DELETE', $uri, [
            "json" => $data
        ]);
    }

    protected function request($method, $uri, $options = [])
    {
        $this->resource = [];
        try {
            return $this->transform($this->client->request($method, $uri, $options));
        } catch (BadResponseException $e) {
            return $this->handleBadResponseException($e);
        }
    }

    protected function transform(Response $response)
    {
        return $response;
        return json_decode($response->getBody(true));
    }

    protected function handleBadResponseException($e)
    {
        try {
            $message = $e->getResponse()->getBody(true);
            $code = 500;
        } catch (Exception $x) {
            $message = $x->getMessage();
            $code = 500;
        }
        
        throw new Exception($message, $code, $e);
    }

    protected function encrypt(array $data)
    {
        $envelope = new SecureContent($data, $this->public_key_path);
        return (string) $envelope;
    }
}