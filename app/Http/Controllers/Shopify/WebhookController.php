<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Helpers\EntegraApi;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\AddressValidator;

class WebhookController extends Controller
{

    public function handleOrderCreate(Request $request)
    {
        try {
            $shopifyOrder = $request->all();

            // Note attributes'ları diziye çevir (varsa)
            $noteAttributes = [];
            if (!empty($shopifyOrder['note_attributes'])) {
                $noteAttributes = collect($shopifyOrder['note_attributes'])->mapWithKeys(function ($item) {
                    return [$item['name'] => $item['value']];
                })->toArray();
            }

            // Sipariş detaylarını hazırla
            $orderDetails = collect($shopifyOrder['line_items'])->map(function ($item) {
                // Calculate total discount from discount_allocations
                $totalDiscount = collect($item['discount_allocations'] ?? [])->sum(function ($discount) {
                    return floatval($discount['amount']);
                });

                return [
                    'sku' => $item['sku'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'name' => $item['name'],
                    'variant_id' => $item['variant_id'],
                    'discount' => $totalDiscount,
                    'total_price' => floatval($item['price']) - $totalDiscount
                ];
            })->toArray();

            // Temel sipariş verilerini hazırla
            $orderData = [
                'order_id' => '10' . $shopifyOrder['order_number'],
                'platform_reference_no' => $shopifyOrder['order_number'],
                'full_name' => $shopifyOrder['customer']['first_name'] . ' ' . $shopifyOrder['customer']['last_name'],
                'email' => $shopifyOrder['email'],
                'mobile_phone' => $shopifyOrder['customer']['phone'] ?? $shopifyOrder['billing_address']['phone'] ?? '0000000000',
                'phone' => $shopifyOrder['customer']['phone'] ?? $shopifyOrder['billing_address']['phone'] ?? '0000000000',

                // Fatura Bilgileri
                'invoice_address' => $shopifyOrder['billing_address']['address1'] . ($shopifyOrder['billing_address']['address2'] ? ', ' . $shopifyOrder['billing_address']['address2'] : ''),
                'invoice_city' => $shopifyOrder['billing_address']['city'],
                'invoice_district' => $shopifyOrder['billing_address']['address2'],
                'invoice_fullname' => $shopifyOrder['billing_address']['first_name'] . ' ' . $shopifyOrder['billing_address']['last_name'],
                'invoice_postcode' => $shopifyOrder['billing_address']['zip'],
                'invoice_tel' => $shopifyOrder['billing_address']['phone'],
                'invoice_gsm' => $shopifyOrder['billing_address']['phone'],

                // Kargo Bilgileri
                'ship_address' => $shopifyOrder['shipping_address']['address1'] . ($shopifyOrder['shipping_address']['address2'] ? ', ' . $shopifyOrder['shipping_address']['address2'] : ''),
                'ship_city' => $shopifyOrder['shipping_address']['city'],
                'ship_district' => $shopifyOrder['shipping_address']['address2'],
                'ship_fullname' => $shopifyOrder['shipping_address']['first_name'] . ' ' . $shopifyOrder['shipping_address']['last_name'],
                'ship_postcode' => $shopifyOrder['shipping_address']['zip'],
                'ship_tel' => $shopifyOrder['shipping_address']['phone'],
                'ship_gsm' => $shopifyOrder['shipping_address']['phone'],

                // Sipariş Bilgileri
                'order_date' => $shopifyOrder['created_at'],
                'discount' => $shopifyOrder['total_discounts'],
                'cargo_code' => null,
                'cargo' => null,
                'total_amounts' => $shopifyOrder['total_price'],
                'order_details' => $orderDetails,
                'erp_response' => null,
                'sync_status' => Order::STATUS_WAITING,
                'sync_error' => null,
                'fulfillment_status' => $shopifyOrder['fulfillment_status'],
                'shopify_payment_gateway' => $shopifyOrder['payment_gateway_names'][0] ?? null,
                'shipping_lines' => $shopifyOrder['shipping_lines'],
                'financial_status' => $shopifyOrder['financial_status']
            ];

            // Note attributes'tan gelen özel alanları ekle (varsa)
            if (!empty($noteAttributes)) {
                $invoiceType = $noteAttributes['Fatura Türü'] ?? null;

                if ($invoiceType === 'Kurumsal') {
                    $orderData['company'] = $noteAttributes['Firma Adı'] ?? null;
                    $orderData['tax_office'] = $noteAttributes['Vergi Dairesi'] ?? null;
                    $orderData['tax_number'] = $noteAttributes['Vergi No'] ?? null;
                } elseif ($invoiceType === 'Bireysel') {
                    $orderData['tc_id'] = $noteAttributes['TC Kimlik No'] ?? null;
                }
            }

            // Siparişi oluştur veya güncelle
            $order = Order::firstOrCreate(
                ['shopify_order_id' => $shopifyOrder['id']],
                $orderData
            );

            // Eğer sipariş zaten varsa ve başarılı durumdaysa işlemi sonlandır
            if ($order->wasRecentlyCreated === false && !$order->isFailed()) {
                \Log::info('Sipariş zaten mevcut ve başarılı, tekrar işlenmeyecek', [
                    'shopify_order_id' => $shopifyOrder['id']
                ]);
                return response()->json(['success' => true, 'message' => 'Sipariş zaten mevcut']);
            }

            if (!$order->save()) {
                \Log::error('Sipariş durumu güncellenemedi', [
                    'order_id' => $order->id,
                    'errors' => $order->getErrors()
                ]);
                throw new \Exception('Sipariş durumu güncellenemedi');
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Sipariş işlenirken hata oluştu', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($order)) {
                if (str_contains($e->getMessage(), 'Adres doğrulama hatası')) {
                    $order->markAsAddressFailed($e->getMessage());
                } else {
                    $order->markAsFailed($e->getMessage());
                }

                if (!$order->save()) {
                    \Log::error('Sipariş durumu güncellenemedi', [
                        'order_id' => $order->id,
                        'errors' => $order->getErrors()
                    ]);
                }
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function handleOrderPaid(Request $request)
    {
        try {
            $shopifyOrder = $request->all();
            $order = Order::where('shopify_order_id', $shopifyOrder['id'])->first();

            if ($order->payment_type == "Havale") {
                $order->financial_status = $shopifyOrder['financial_status'];
                $order->save();

                if ($shopifyOrder['financial_status'] == 'paid') {
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

                    $order->markAsCompleted();
                    $order->save();

                    return response()->json(['success' => true]);
                }
            }

        } catch (\Exception $e) {
            \Log::error('Sipariş işlenirken hata oluştu', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($order)) {
                $order->markAsFailed($e->getMessage());
                if (!$order->save()) {
                    \Log::error('Sipariş failed durumuna geçirilemedi', [
                        'order_id' => $order->id,
                        'errors' => $order->getErrors()
                    ]);
                }
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function handleOrderFulfilled(Request $request)
    {
        try {
            $shopifyOrder = $request->all();
            $order = Order::where('shopify_order_id', $shopifyOrder['id'])->first();

            $order->sync_status = 'fulfilled';
            $order->save();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Log::error('Sipariş işlenirken hata oluştu', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'response' => $shopifyOrder,
            ]);

            return response()->json(['error' => $e->getMessage(), 'response' => $shopifyOrder], 500);
        }
    }

    public function handleOrderRefunds(Request $request)
    {
        try {
            $shopifyOrder = $request->all();
            $order = Order::where('shopify_order_id', $shopifyOrder['order']['id'])->first();

            $order->sync_status = 'refunded';
            $order->save();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Log::error('Sipariş işlenirken hata oluştu', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'response' => $shopifyOrder,
            ]);

            if (isset($order)) {
                $order->markAsFailed($e->getMessage());
                if (!$order->save()) {
                    \Log::error('Sipariş failed durumuna geçirilemedi', [
                        'order_id' => $order->id,
                        'errors' => $order->getErrors()
                    ]);
                }
            }

            return response()->json(['error' => $e->getMessage(), 'response' => $shopifyOrder], 500);
        }
    }

    public function handleOrderCancelled(Request $request)
    {
        $shopifyOrder = $request->all();
        $order = Order::where('shopify_order_id', $shopifyOrder['id'])->first();

        if ($order) {
            $order->sync_status = 'cancelled';
            $order->update();
        }
    }

    public function createOrderWebhook(Request $request)
    {
        $shop = Setting::where('type', 'shopify')->first();
        if (!$shop) {
            return response()->json(['error' => 'Shopify credentials not found'], 404);
        }

        $shopifyDomain = $shop->credentials['shopify_domain'];
        $accessToken = $shop->credentials['shopify_token'];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken
        ])->post("https://{$shopifyDomain}/admin/api/2025-01/webhooks.json", [
            'webhook' => [
                'topic' => 'orders/create',
                'address' => 'https://entegrasyon.shargeturkiye.com/webhook/shopify/order-create',
                'format' => 'json'
            ]
        ]);

        $response1 = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken
        ])->post("https://{$shopifyDomain}/admin/api/2025-01/webhooks.json", [
            'webhook' => [
                'topic' => 'orders/paid',
                'address' => 'https://entegrasyon.shargeturkiye.com/webhook/shopify/order-paid',
                'format' => 'json'
            ]
        ]);

        $response2 = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken
        ])->post("https://{$shopifyDomain}/admin/api/2025-01/webhooks.json", [
            'webhook' => [
                'topic' => 'orders/cancelled',
                'address' => 'https://entegrasyon.shargeturkiye.com/webhook/shopify/order-cancelled',
                'format' => 'json'
            ]
        ]);

        $response3 = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken
        ])->post("https://{$shopifyDomain}/admin/api/2025-01/webhooks.json", [
            'webhook' => [
                'topic' => 'orders/fulfilled',
                'address' => 'https://entegrasyon.shargeturkiye.com/webhook/shopify/order-fulfilled',
                'format' => 'json'
            ]
        ]);

        $response4 = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken
        ])->post("https://{$shopifyDomain}/admin/api/2025-01/webhooks.json", [
            'webhook' => [
                'topic' => 'returns/approve',
                'address' => 'https://entegrasyon.shargeturkiye.com/webhook/shopify/returns-approve',
                'format' => 'json'
            ]
        ]);

        return response()->json([
            'orders_create_response' => $response->json(),
            'orders_paid_response' => $response1->json(),
            'orders_cancelled_response' => $response2->json(),
            'orders_fulfilled_response' => $response3->json(),
            'orders_returns_approve' => $response4->json(),
        ]);
    }

    public function deleteAllWebhooks(Request $request)
    {
        $shop = Setting::where('type', 'shopify')->first();
        if (!$shop) {
            return response()->json(['error' => 'Shopify credentials not found'], 404);
        }

        $shopifyDomain = $shop->credentials['shopify_domain'];
        $accessToken = $shop->credentials['shopify_token'];

        // Önce tüm webhook'ları al
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken
        ])->get("https://{$shopifyDomain}/admin/api/2025-01/webhooks.json");

        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to fetch webhooks'], 500);
        }

        $webhooks = $response->json()['webhooks'];

        // Her webhook'u sil
        foreach ($webhooks as $webhook) {
            Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken
            ])->delete("https://{$shopifyDomain}/admin/api/2025-01/webhooks/{$webhook['id']}.json");
        }

        return response()->json(['success' => true, 'message' => 'All webhooks deleted']);
    }
}
