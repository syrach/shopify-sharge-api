<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'title',
        'variant_id',
        'product_id',
        'sku',
        'barcode',
        'price',
        'stock'
    ];
}
