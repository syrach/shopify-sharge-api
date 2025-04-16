<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class ErpApi
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = "https://beta.drkaksesuar.com/api/shopify/products?token=01991b8b61f96de9ba9677637b8bd25ecf8f7d4f";
    }

    public function getProducts()
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl);

        return $response->json();
    }
}
