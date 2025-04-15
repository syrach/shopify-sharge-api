<?php

use App\Models\Product;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shopify\AuthController;
use App\Http\Controllers\Shopify\WebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Shopify\ProductController;
use Illuminate\Http\Request;

# Shopify Routes
Route::prefix('shopify')->group(function () {
    # Shopify Private App Auth
    Route::get('auth', function (Request $request) {
        $shop = $request->get('shop');
        $apiKey = config('services.shopify.client');
        $scopes = 'read_orders,write_orders,read_customers,write_customers,read_products,write_products,write_inventory';
        $redirectUri = urlencode('https://scjdp5pyrk.sharedwithexpose.com/shopify/callback');

        $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}";

        return redirect()->away($installUrl);
    });

    # Shopify Private App Callback
    Route::get('callback', [AuthController::class, 'handleCallback']);

    Route::get('get-locations', [ProductController::class, 'getLocations']);
    Route::get('get-products', [ProductController::class, 'getProducts']);
    Route::get('update-products', [ProductController::class, 'updateProducts']); // 30 Min Updates

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

Route::get('test1', function () {
    $products = Product::find(1);

    $shopify = new \App\Helpers\ShopifyApi();
    $platform = \App\Models\Setting::find(1);

    $shopify->setPlatform($platform->credentials['shopify_domain'], $platform->credentials['shopify_token']);

    $response = $shopify->put('variants/' . '47711079563540' . '.json', [
        "variant" => [
            "id" => 47711079563540,
            "price" => "20.45",
            'sku' => "YENISKU",
            'barcode' => "0000005555555",
            'inventory_quantity' => "55"
        ]
    ]);

    dd($response->body());
});
