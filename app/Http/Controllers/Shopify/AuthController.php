<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function handleCallback(Request $request)
    {
        $shop = $request->get('shop');
        $code = $request->get('code');
        $hmac = $request->get('hmac');

        if (!$shop || !$code) {
            return response('Eksik parametre.', 400);
        }

        $apiKey = config('services.shopify.client');
        $apiSecret = config('services.shopify.secret');

        // Token alma isteği
        $response = Http::asForm()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => $apiKey,
            'client_secret' => $apiSecret,
            'code' => $code,
        ]);

        if ($response->successful()) {
            $accessToken = $response->json()['access_token'];

            $setting = Setting::where('type', 'shopify')->first();

            if ($setting) {
                $setting->credentials = [
                    'shopify_token' => $accessToken,
                    'shopify_domain' => $shop
                ];
                $setting->save();
            } else {
                Setting::create([
                    'type' => 'shopify',
                    'credentials' => [
                        'shopify_token' => $accessToken,
                        'shopify_domain' => $shop
                    ]
                ]);
            }

            return redirect("https://{$shop}/admin/apps/erp-entegrasyon");
        } else {
            return response('Token alınamadı.', 500);
        }
    }
}
