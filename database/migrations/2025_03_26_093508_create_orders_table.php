<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('shopify_order_id')->nullable();
            $table->string('platform_reference_no')->nullable();
            $table->string('tc_id')->nullable();
            $table->string('company')->nullable();
            $table->string('full_name');
            $table->string('email');
            $table->string('mobile_phone');
            $table->string('phone');

            // Fatura Bilgileri
            $table->string('invoice_address');
            $table->string('invoice_city');
            $table->string('invoice_district')->nullable();
            $table->string('invoice_fullname');
            $table->string('invoice_postcode')->nullable();
            $table->string('invoice_tel');
            $table->string('invoice_gsm');

            // Kargo Bilgileri
            $table->string('ship_address');
            $table->string('ship_city');
            $table->string('ship_district')->nullable();
            $table->string('ship_fullname');
            $table->string('ship_postcode')->nullable();
            $table->string('ship_tel');
            $table->string('ship_gsm');

            // Vergi Bilgileri
            $table->string('tax_office')->nullable();
            $table->string('tax_number')->nullable();
            // Sipariş Bilgileri
            $table->datetime('order_date');
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('cargo_code')->nullable();
            $table->string('cargo')->nullable();

            // Sipariş Detayları (JSON olarak saklanacak)
            $table->json('order_details');

            // Entegra API Yanıtı
            $table->json('erp_response')->nullable();

            // Senkronizasyon Durumu
            $table->string('sync_status')->default('waiting');
            $table->text('sync_error')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->string('shopify_payment_gateway')->nullable();
            $table->json('shipping_lines')->nullable();
            $table->string('financial_status')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
