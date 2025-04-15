<?php

namespace App\Http\Controllers;

use App\Helpers\EntegraApi;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::all();

        return response()->json($orders);
    }

    public function update(Request $request, $id)
    {

    }
}
