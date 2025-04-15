<?php

use App\Helpers\EntegraApi;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shopify\AuthController;
use App\Http\Controllers\Shopify\WebhookController;
use App\Http\Controllers\Entegra\ManuelController;
use App\Http\Controllers\OrderController;
use App\Services\AddressValidator;
use Illuminate\Http\Request;

Route::get('test', function () {
    try {
        $api = new \App\Helpers\EntegraApi();

        $orderData = [
            'supplier' => 'shopify.entegra',
            'platform_reference_no' => '9991',
            'tc_id' => '11111111111',
            'order_id' => '109991',
            'full_name' => 'ali tezcan',
            'email' => 'fatih@gmail.com',
            'mobile_phone' => '+905302002020',
            'phone' => '+905302002020',
            'invoice_address' => 'Sirinevler',
            'invoice_city' => 'Istanbul',
            'invoice_district' => 'Sirinevler',
            'invoice_fullname' => 'Ali Tezcan',
            'invoice_postcode' => 34000,
            'invoice_tel' => 5302002020,
            'invoice_gsm' => 5302002020,
            'ship_address' => 'Sirinevler',
            'ship_city' => 'istanbul',
            'ship_district' => 'Sirinevler',
            'ship_fullname' => 'Ali Tezcan',
            'ship_postcode' => 34000,
            'ship_tel' => 5302002020,
            'ship_gsm' => 5302002020,
            'tax_office' => 'istanbul',
            'order_date' => now()->format('d.m.Y'),
            'discount' => 0,
            'payment_type' => 'Kapıda Nakit',
            'cargo' => 'MNG',
            'cargo_payment_method' => "snakit",
            'installment' => 0,
            'order_details' => [
                [
                    'product_code' => 'ADR-8104W024TTK10033XL',
                    'price' => 899.0 / 1.10,
                    'quantity' => 1
                ],
                [
                    'product_code' => 'shopify.krg01',
                    'price' => 74.9,
                    'quantity' => 1
                ],
            ]
        ];

        $response = $api->createOrder($orderData);
        dd($response);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::get('test2', function () {

    $order = \App\Models\Order::find(109);

    $entegraOrderData = [
        'supplier' => 'shopify.entegra',
        'platform_reference_no' => $order->platform_reference_no,
        'order_id' => $order->order_id,
        'full_name' => $order->full_name,
        'email' => $order->email,
        'mobile_phone' => $order->mobile_phone,
        'phone' => $order->phone,
        'invoice_address' => $order->invoice_address,
        'invoice_city' => $order->invoice_city,
        'invoice_district' => $order->invoice_district,
        'invoice_fullname' => $order->invoice_fullname,
        'invoice_tel' => $order->invoice_tel,
        'invoice_gsm' => $order->invoice_gsm,
        'ship_address' => $order->ship_address,
        'ship_city' => $order->ship_city,
        'ship_district' => $order->ship_district,
        'ship_fullname' => $order->ship_fullname,
        'ship_tel' => $order->ship_tel,
        'ship_gsm' => $order->ship_gsm,
        'order_date' => $order->order_date->format('d.m.Y H:i'),
        'discount' => $order->discount,
        'payment_type' => $order->payment_type,
        'cargo' => $order->cargo,
        'cargo_payment_method' => $order->cargo_payment_method,
        'installment' => $order->installment,
        'order_details' => $order->order_details,
    ];

    // Sadece null olanları silmek istiyorsan:
    $optionalFields = array_filter([
        'tax_office' => $order->tax_office,
        'tax_number' => $order->tax_number,
        'tc_id' => $order->tc_id,
        'invoice_postcode' => $order->invoice_postcode,
        'ship_postcode' => $order->ship_postcode,
    ], fn($val) => !is_null($val));

    // Bu iki kısmı birleştir:
    $entegraOrderData = array_merge($entegraOrderData, $optionalFields);

    if (!empty($order->shipping_lines)) {
        foreach ($order->shipping_lines as $shipping) {
            if (isset($shipping['price']) && $shipping['price'] > 0) {
                $entegraOrderData['order_details'][] = [
                    'product_code' => 'shopify.krg01',
                    'price' => $shipping['price']/1.10,
                    'quantity' => 1
                ];
            }
        }
    }


    dd($entegraOrderData);
});

Route::get('test3', function () {
    $response1 = Http::get('https://tradres.com.tr/api/iller');

    foreach ($response1->json() as $item) {
        $city = \App\Models\City::create([
            'plate_code' => $item['ilId'],
            'name' => $item['ilAdi']
        ]);
    }


    $cities = \App\Models\City::all();

    foreach ($cities as $city) {
        $response = Http::get('https://tradres.com.tr/api/ilceler?ilkod=' . $city->plate_code);

        foreach ($response->json() as $item) {
            $district = \App\Models\District::create([
                'city_id' => $city->id,
                'name' => $item['ilceAdi']
            ]);
        }

    }

});

Route::prefix('entegra')->group(function () {
   Route::get('manuel', [ManuelController::class, 'index']);
   Route::post('manuel-post', [ManuelController::class, 'store'])->name('manuel-post');
});

# Shopify Routes
Route::prefix('shopify')->group(function () {
    # Shopify Private App Auth
    Route::get('auth', function (Request $request) {
        $shop = $request->get('shop');
        $apiKey = config('services.shopify.client');
        $scopes = 'read_orders,write_orders,read_customers,write_customers,read_products,write_products';
        $redirectUri = urlencode('https://modaxlarge.codeven.co/shopify/callback');

        $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}";

        return redirect()->away($installUrl);
    });

    # Shopify Private App Callback
    Route::get('callback', [AuthController::class, 'handleCallback']);
});

# Webhook Route
Route::prefix('webhook')->group(function () {
    Route::prefix('shopify')->group(function () {
        Route::post('order-paid', [WebhookController::class, 'handleOrderPaid']);
        Route::post('order-create', [WebhookController::class, 'handleOrderCreate']);
        Route::post('order-cancelled', [WebhookController::class, 'handleOrderCancelled']);
        Route::get('register', [WebhookController::class, 'createOrderWebhook']);
        Route::get('delete', [WebhookController::class, 'deleteAllWebhooks']);
    });
});

# Order Management & Error Fix
Route::prefix('orders')->group(function () {
   Route::get('list', [OrderController::class, 'index'])->name('order.index');
   Route::put('update/{id}', [OrderController::class, 'update'])->name('order.update');
});
