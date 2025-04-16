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
                        Log::info('Ürün güncellendi', [
                            'sku' => $item['stockCode'],
                            'old_stock' => $product->stock,
                            'new_stock' => $item['stock'],
                            'old_price' => $product->price,
                            'new_price' => $item['price'],
                        ]);

                        $product->stock = $item['stock'];
                        $product->price = $item['price'];
                        $product->save();

                        $updated = true;
                    }
                }
            }

            if ($updated) {
                Http::get(url('/shopify/update-products'));
                Log::info('Shopify update-products tetiklendi');
            } else {
                Log::info('Stok veya fiyat değişikliği yok, Shopify update yapılmadı.');
            }

        } catch (\Exception $e) {
            Log::error('ERP ürün senkronizasyon hatası: ' . $e->getMessage());
        }
    }
}
