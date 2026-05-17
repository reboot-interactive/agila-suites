<?php

namespace Extensions\pedallion\Controllers;

use App\Http\Controllers\Controller;

use Extensions\pedallion\Models\PedallionOrder;
use Illuminate\Http\Request;

class PedallionOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PedallionOrder::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from')) {
            $query->where('order_date', '>=', $request->input('from') . ' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('order_date', '<=', $request->input('to') . ' 23:59:59');
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('buyer_name', 'like', "%{$q}%");
            });
        }

        $orders = $query->orderByDesc('order_date')->paginate(50)->withQueryString();

        return view('ext-pedallion::orders.index', compact('orders'));
    }

    public function show(int $id)
    {
        $order = PedallionOrder::with('products')->findOrFail($id);
        return view('ext-pedallion::orders.show', compact('order'));
    }
}
