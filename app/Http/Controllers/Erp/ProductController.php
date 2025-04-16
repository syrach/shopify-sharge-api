<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductController extends Controller
{
    public function getProducts()
    {
        try {
            $erp = new \App\Helpers\ErpApi();
            $response = $erp->getProducts();

            foreach ($response['productList'] as $item) {
                $product = Product::where('sku', $item['stockCode'])->first();

                if ($product) {
                    $product->stock = $item['stock'];
                    $product->price = $item['price'];
                    $product->save();
                }
            }
        } catch (\Exception $e) {
            \Log::error('ERP ürün senkronizasyon hatası: ' . $e->getMessage());
        }
    }
}
