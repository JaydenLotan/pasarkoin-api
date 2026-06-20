<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with([
            'buyer',
            'coinListing.images',
        ])
            ->whereHas('coinListing', function ($query) use ($request) {
                $query->where('seller_id', $request->user()->id);
            })
            ->latest()
            ->get();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $order->load([
            'buyer',
            'coinListing.images',
        ]);

        if ($order->coinListing->seller_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only view orders for your own listings.',
            ], 403);
        }

        return response()->json([
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $order->load('coinListing');

        if ($order->coinListing->seller_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only update orders for your own listings.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([
                    'confirmed',
                    'shipped',
                    'completed',
                    'cancelled',
                ]),
            ],
            'seller_note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['shipped', 'cancelled'],
            'shipped' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];

        if (! in_array(
            $validated['status'],
            $allowedTransitions[$order->status] ?? [],
            true
        )) {
            return response()->json([
                'message' => "Order status cannot be changed from {$order->status} to {$validated['status']}.",
            ], 422);
        }

        $order->update([
            'status' => $validated['status'],
            'seller_note' => $validated['seller_note'] ?? $order->seller_note,
        ]);

        if ($validated['status'] === 'cancelled') {
            $order->coinListing->update([
                'status' => 'approved',
            ]);
        }

        return response()->json([
            'message' => 'Order status updated successfully',
            'data' => $order->fresh()->load([
                'buyer',
                'coinListing.images',
            ]),
        ]);
    }
}