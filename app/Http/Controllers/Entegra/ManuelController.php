<?php

namespace App\Http\Controllers\Entegra;

use App\Helpers\EntegraApi;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use function Pest\Laravel\json;

class ManuelController extends Controller
{
    public function index()
    {
        return view('entegra');
    }

    public function store(Request $request)
    {
        $order = Order::find($request->id);

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

        // Bank Deposit ve ödenmemiş siparişleri kontrol et
        if ($order->shopify_payment_gateway === 'Havale' && $order->financial_status !== 'paid') {
            return response()->json(['success' => true, 'message' => 'Bank Deposit ödenmemiş sipariş, Entegra\'ya gönderilmedi']);
        } else {
            // Entegra'ya siparişi gönder
            $entegraApi = new EntegraApi();
            $entegraResponse = $entegraApi->createOrder($entegraOrderData);

            // Entegra yanıtını kaydet
            $order->entegra_response = $entegraResponse;
            foreach ($entegraResponse['result'] as $entegraOrder) {
                $order->entegra_order_id = $entegraOrder['id'];
            }
            $order->markAsCompleted();
            $order->save();

            return redirect()->back();
        }
    }
}
