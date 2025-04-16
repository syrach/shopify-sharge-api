<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function getProducts()
    {
        $shopify = new \App\Helpers\ShopifyApi();
        $platform = \App\Models\Setting::find(1);

        $shopify->setPlatform($platform->credentials['shopify_domain'], $platform->credentials['shopify_token']);

        $response = json_decode($shopify->get('products.json')->body());

        foreach ($response->products as $item) {
            $product = new \App\Models\Product();
            $product->title = $item->title;

            foreach ($item->variants as $variant) {
                $product->variant_id = $variant->id;
                $product->product_id = $variant->product_id;
                $product->price = $variant->price;
                $product->sku = $variant->sku;
                $product->barcode = $variant->barcode;
                $product->inventory_item_id = $variant->inventory_item_id;
                $product->stock = $variant->inventory_quantity;
            }

            $product->save();
        }
    }

    public function getLocations()
    {
        $shopify = new \App\Helpers\ShopifyApi();
        $platform = \App\Models\Setting::find(1);

        $shopify->setPlatform($platform->credentials['shopify_domain'], $platform->credentials['shopify_token']);

        $response = json_decode($shopify->get('locations.json')->body());

        $item = $response->locations[0];

        $location = new Location();
        $location->location_id = $item->id;
        $location->name = $item->name;
        $location->save();
    }

    public function updateProducts()
    {
        $products = Product::all();

        $shopify = new \App\Helpers\ShopifyApi();
        $platform = \App\Models\Setting::find(1);

        $shopify->setPlatform($platform->credentials['shopify_domain'], $platform->credentials['shopify_token']);

        foreach ($products as $product) {
            $response = $shopify->put('variants/' . $product->variant_id . '.json', [
                "variant" => [
                    "id" => $product->variant_id,
                    "price" => $product->price,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                ]
            ]);

            $this->updateInventory($product->inventory_item_id, $product->stock);
        }

        return response()->json([
            'message' => 'Ürünler başarıyla güncellendi.'
        ], 200);
    }

    public function updateInventory($inventoryItemId, $quantity)
    {
        $location = Location::find(1);
        $locationId = $location->location_id;

        $shopify = new \App\Helpers\ShopifyApi();
        $platform = \App\Models\Setting::find(1);
        $shopify->setPlatform($platform->credentials['shopify_domain'], $platform->credentials['shopify_token']);

        $payload = [
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'available' => $quantity
        ];

        $response = $shopify->post('inventory_levels/set.json', $payload);

        return $response;
    }


}
