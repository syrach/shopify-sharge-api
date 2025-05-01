<?php

namespace App\Http\Controllers;

use App\Helpers\EntegraApi;
use App\Models\Order;
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

    public function update(Request $request, $id)
    {

    }
}
