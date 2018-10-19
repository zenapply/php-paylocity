<?php

namespace Zenapply\HRIS\Paylocity;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use Zenapply\Common\Exceptions\ZenapplyException;

class Paylocity
{
    protected $api_key;
    protected $base_uri;
    protected $client;
    protected $resource;

    public function __construct($api_key, $host = "api.paylocity.com", $secure = true)
    {
        $protocol = $secure ? "https" : "http";
        $this->api_key = $api_key;
        $this->base_uri = "{$protocol}://{$host}/api/v2/";
        $this->client = new Client([
            "base_uri" => $this->base_uri,
            "timeout" => 30,
            "headers" => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        ]);
    }

    public function __get($name)
    {
        $this->resource = $name;

        return $this;
    }

    public function get($id)
    {
        $uri = "{$this->resource}/{$id}";
        return $this->request('GET', $uri);
    }

    public function all(array $search = null)
    {
        $uri = $this->resource;
        return $this->request('GET', $uri, [
            'query' => $search
        ]);
    }

    public function post(array $data = null)
    {
        $uri = $this->resource;
        return $this->request('POST', $uri, [
            "json" => $data
        ]);
    }

    public function put($id, array $data = null)
    {
        $uri = "{$this->resource}/{$id}";
        return $this->request('PUT', $uri, [
            "json" => $data
        ]);
    }

    public function delete($id, array $data = null)
    {
        $uri = "{$this->resource}/{$id}";
        return $this->request('DELETE', $uri, [
            "json" => $data
        ]);
    }

    protected function request($method, $uri, $options = [])
    {
        try {
            return $this->transform($this->client->request($method, $uri, $options));
        } catch (BadResponseException $e) {
            return $this->handleBadResponseException($e);
        }
    }

    protected function transform(Response $response)
    {
        return json_decode($response->getBody());
    }

    protected function handleBadResponseException($e)
    {
        try {
            $r = $this->transform($e->getResponse());
            $message = @$r->error->message;
            $code = @$r->error->code;
        } catch (Exception $x) {
            $message = "An error occurred";
            $code = 500;
        }
        
        throw new ZenapplyException($message, $code, $e);
    }
}