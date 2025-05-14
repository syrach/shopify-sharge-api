<?php

namespace App\Http\Controllers;

use App\Helpers\EntegraApi;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // Token kontrolü
        $token = $request->header('X-API-Token');
        if ($token !== config('app.api_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = Order::query();

        // Tarih filtreleme
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::createFromFormat('d-m-Y', $request->start_date)->startOfDay();
            $endDate = Carbon::createFromFormat('d-m-Y', $request->end_date)->endOfDay();

            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            // Son 3 günlük siparişler
            $query->where('created_at', '>=', Carbon::now()->subDays(3));
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json($orders);
    }

    public function update(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['order_id'], $data['shipping'][0])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data received',
            ], 400);
        }

        $order_id = str_replace('gid://shopify/Order/', '', $data['order_id']);
        $order = Order::where('shopify_order_id', $order_id)->first();

        if ($order) {
            $order->cargo = $data['shipping'][0]['cargo_company'];
            $order->cargo_code = $data['shipping'][0]['barcode'];
            $order->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
        ]);
    }
}
