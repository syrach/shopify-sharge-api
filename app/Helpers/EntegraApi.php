<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\Setting;
use App\Models\Order;

class EntegraApi
{
    protected $baseUrl;
    protected $token;

    public function __construct()
    {
        $this->baseUrl = "https://apiv2.entegrabilisim.com/";
        $setting = Setting::firstOrCreate(['type' => 'entegra'], ['credentials' => null]);

        if ($setting->credentials) {
            $data = json_decode($setting->credentials, true);
            $expireAt = isset($data['expire_at']) ? Carbon::parse($data['expire_at']) : null;

            if ($expireAt && Carbon::now()->lt($expireAt)) {
                $this->token = 'JWT ' . $data['access'];
            } elseif (isset($data['refresh'])) {
                $this->refreshToken($data['refresh']);
            } else {
                $this->fetchAndAuthenticate();
            }
        } else {
            $this->fetchAndAuthenticate();
        }
    }

    protected function fetchAndAuthenticate()
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . "api/user/token/obtain/", [
            'email' => config('services.entegra.email'),
            'password' => config('services.entegra.password'),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Authentication failed: ' . $response->body());
        }

        $data = $response->json();
        $this->authenticate($data);
    }

    protected function refreshToken($refresh)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . "api/user/token/refresh/", [
            'refresh' => $refresh,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Token refresh failed: ' . $response->body());
        }

        $data = $response->json();
        $setting = Setting::where('type', 'entegra')->first();

        if (isset($data['access']) && isset($setting)) {
            $credentials = json_decode($setting->credentials, true);
            $credentials['access'] = $data['access'];
            $credentials['expire_at'] = Carbon::now()->addMinutes(60)->toISOString();
            $setting->credentials = json_encode($credentials);
            $setting->save();

            $this->token = 'JWT ' . $data['access'];
        } else {
            throw new \Exception('Token refresh failed: Invalid response data');
        }
    }

    public function authenticate($data)
    {
        $setting = Setting::where('type', 'entegra')->first();

        if (isset($data['access']) && isset($data['refresh'])) {
            $credentials = [
                'access' => $data['access'],
                'refresh' => $data['refresh'],
                'expire_at' => Carbon::now()->addMinutes(60)->toISOString(),
            ];

            if ($setting) {
                $setting->credentials = json_encode($credentials);
                $setting->save();
            }

            $this->token = 'JWT ' . $data['access'];
        } else {
            throw new \Exception('Authentication failed: Invalid response data');
        }
    }

    public function getOrders($startDate, $endDate, $page = 1)
    {
        if (!$this->token) {
            $this->fetchAndAuthenticate();
        }

        $url = $this->baseUrl . "order/page={$page}/?start_date={$startDate}&end_date={$endDate}";
        $response = Http::withHeaders(['Authorization' => $this->token])->get($url);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch orders: ' . $response->body());
        }

        return $response->json();
    }

    public function createOrder($orderData)
    {
        if (!$this->token) {
            $this->fetchAndAuthenticate();
        }

        $response = Http::withHeaders([
            'Authorization' => $this->token,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . "order/", [
            'list' => [$orderData]
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create order: ' . $response->body());
        }

        return $response->json();
    }

    public function updateOrder($order)
    {
        if (!$this->token) {
            $this->fetchAndAuthenticate();
        }

        $response = Http::withHeaders([
            'Authorization' => $this->token,
            'Content-Type' => 'application/json',
        ])->put($this->baseUrl . "order/", [
            'list' => [$order]
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to update order: ' . $response->body());
        }

        return $response->json();
    }

    public function updateOrderTracking($orderId, $trackingNumber)
    {
        if (!$this->token) {
            $this->fetchAndAuthenticate();
        }

        // Önce siparişi bul
        $order = Order::where('order_id', $orderId)->first();
        if (!$order) {
            throw new \Exception('Order not found');
        }

        // Shopify bilgilerini al
        $shop = Setting::where('type', 'shopify')->first();
        if (!$shop) {
            throw new \Exception('Shopify credentials not found');
        }

        $shopifyDomain = $shop->credentials['shopify_domain'];
        $accessToken = $shop->credentials['shopify_token'];

        // Shopify'da siparişi güncelle
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken
        ])->put("https://{$shopifyDomain}/admin/api/2024-01/orders/{$order->shopify_order_id}.json", [
            'order' => [
                'tracking_number' => $trackingNumber,
                'tracking_url' => "https://www.mngkargo.com.tr/tr/online-sorgu?code={$trackingNumber}"
            ]
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to update Shopify order: ' . $response->body());
        }

        return $response->json();
    }
}
