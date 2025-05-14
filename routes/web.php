<?php

use App\Models\Product;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shopify\AuthController;
use App\Http\Controllers\Shopify\WebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Shopify\ProductController as ShopifyProductController;
use App\Http\Controllers\Erp\ProductController as ErpProductController;
use Illuminate\Http\Request;

# Shopify Routes
Route::prefix('shopify')->group(function () {
    # Shopify Private App Auth
    Route::get('auth', function (Request $request) {
        $shop = $request->get('shop');
        $apiKey = config('services.shopify.client');
        $scopes = 'read_orders,write_orders,read_customers,write_customers,read_products,write_products,write_inventory';
        $redirectUri = urlencode('https://entegrasyon.shargeturkiye.com/shopify/callback');

        $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}";

        return redirect()->away($installUrl);
    });

    # Shopify Private App Callback
    Route::get('callback', [AuthController::class, 'handleCallback']);

    Route::get('get-locations', [ShopifyProductController::class, 'getLocations']);
    Route::get('get-products', [ShopifyProductController::class, 'getProducts']);
    Route::get('update-products', [ShopifyProductController::class, 'updateProducts']); // 30 Min Updates

});

Route::prefix('erp')->group(function () {
    Route::get('get-products', [ErpProductController::class, 'getProducts']);
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
   Route::post('update', [OrderController::class, 'update'])->name('order.update');
});

Route::get('test1', function () {
    $shopify = new \App\Helpers\ShopifyApi();
    $platform = \App\Models\Setting::find(1);

    $shopify->setPlatform($platform->credentials['shopify_domain'], $platform->credentials['shopify_token']);

    $response = json_decode($shopify->get('orders/11562781081673.json')->body());

    $orderDetails = collect($response->order->line_items)->map(function ($item) {
        $totalDiscount = collect($item->discount_allocations ?? [])->sum(function ($discount) {
            return floatval($discount->amount);
        });

        return [
            'sku' => $item->sku,
            'price' => $item->price,
            'quantity' => $item->quantity,
            'name' => $item->name,
            'variant_id' => $item->variant_id,
            'discount' => $totalDiscount,
            'total_price' => floatval($item->price) - $totalDiscount
        ];
    })->toArray();

    return $orderDetails;

});
