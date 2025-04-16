<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function getProducts()
    {
        try {
            $erp = new \App\Helpers\ErpApi();
            $response = $erp->getProducts();

            $updated = false;

            foreach ($response['productList'] as $item) {
                $product = Product::where('sku', $item['stockCode'])->first();

                if ($product) {
                    $stockChanged = $product->stock != $item['stock'];
                    $priceChanged = $product->price != $item['price'];

                    if ($stockChanged || $priceChanged) {
                        $product->stock = $item['stock'];
                        $product->price = $item['price'];
                        $product->save();

                        $updated = true;
                    }
                }
            }

            if ($updated) {
                Http::get(url('/shopify/update-products'));
            }

        } catch (\Exception $e) {
            Log::error('ERP ürün senkronizasyon hatası: ' . $e->getMessage());
        }
    }
}
