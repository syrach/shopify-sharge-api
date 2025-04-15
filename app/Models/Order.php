<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Order extends Model
{
    protected $fillable = [
        'supplier',
        'order_id',
        'shopify_order_id',
        'platform_reference_no',
        'tc_id',
        'company',
        'full_name',
        'email',
        'mobile_phone',
        'phone',
        'invoice_address',
        'invoice_city',
        'invoice_district',
        'invoice_fullname',
        'invoice_postcode',
        'invoice_tel',
        'invoice_gsm',
        'ship_address',
        'ship_city',
        'ship_district',
        'ship_fullname',
        'ship_postcode',
        'ship_tel',
        'ship_gsm',
        'tax_office',
        'tax_number',
        'order_date',
        'discount',
        'cargo_code',
        'cargo',
        'order_details',
        'erp_response',
        'sync_status',
        'sync_error',
        'fulfillment_status',
        'shopify_payment_gateway',
        'shipping_lines',
        'financial_status',
        'erp_order_id'
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'discount' => 'decimal:2',
        'order_details' => 'array',
        'erp_response' => 'array',
        'installment' => 'integer',
        'shipping_lines' => 'array'
    ];

    // Senkronizasyon durumu sabitleri
    const STATUS_WAITING = 'waiting';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_ADRESSFAILED = 'address-failed';

    // Durum kontrol metodları
    public function isWaiting()
    {
        return $this->sync_status === self::STATUS_WAITING;
    }

    public function isProcessing()
    {
        return $this->sync_status === self::STATUS_PROCESSING;
    }

    public function isCompleted()
    {
        return $this->sync_status === self::STATUS_COMPLETED;
    }

    public function isFailed()
    {
        return $this->sync_status === self::STATUS_FAILED;
    }

    public function isAdressFailed()
    {
        return $this->sync_status === self::STATUS_ADRESSFAILED;
    }

    // Durum güncelleme metodları
    public function markAsProcessing()
    {
        $this->sync_status = self::STATUS_PROCESSING;
    }

    public function markAsCompleted()
    {
        $this->sync_status = self::STATUS_COMPLETED;
    }

    public function markAsFailed($error = null)
    {
        $this->sync_status = self::STATUS_FAILED;
        $this->sync_error = $error;
    }

    public function markAsAddressFailed($error = null)
    {
        $this->sync_status = self::STATUS_ADRESSFAILED;
        $this->sync_error = $error;
    }
}
