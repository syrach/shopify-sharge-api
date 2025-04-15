<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ShopifyApi
{
    protected string $baseUrl;
    protected string $domain;
    protected string $adminApiKey;
    protected string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = "2024-07";
    }

    public function setPlatform(string $domain, string $apiKey): void
    {
        $this->domain = $domain;
        $this->adminApiKey = $apiKey;
        $this->baseUrl = "https://{$this->domain}/admin/api/{$this->apiVersion}/";
    }

    protected function request(string $method, string $endpoint, array $params = [])
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->adminApiKey,
        ])->$method($this->baseUrl . $endpoint, $params);

        return $response;
    }

    public function get(string $endpoint, array $params = [])
    {
        return $this->request('GET', $endpoint, $params);
    }

    public function post(string $endpoint, array $params = [])
    {
        return $this->request('POST', $endpoint, $params);
    }

    public function put(string $endpoint, array $params = [])
    {
        return $this->request('PUT', $endpoint, $params);
    }

    public function delete(string $endpoint, array $params = [])
    {
        return $this->request('DELETE', $endpoint, $params);
    }

    public function graphql(string $query, array $variables = [])
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->adminApiKey,
            'Content-Type' => 'application/json',
        ])->post("https://{$this->domain}/admin/api/{$this->apiVersion}/graphql.json", [
            'query' => $query,
            'variables' => $variables
        ]);

        return $response;
    }
}
