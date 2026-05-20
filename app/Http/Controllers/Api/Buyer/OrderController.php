<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\CoinListing;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['coinListing.images', 'coinListing.seller'])
            ->where('buyer_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'coin_listing_id' => ['required', 'exists:coin_listings,id'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['required', 'string', 'max:1000'],
        ]);

        $order = DB::transaction(function () use ($request, $validated) {
            $listing = CoinListing::where('id', $validated['coin_listing_id'])
                ->lockForUpdate()
                ->first();

            if (! $listing) {
                return response()->json([
                    'message' => 'Listing not found.',
                ], 404);
            }

            if ($listing->status !== 'approved') {
                return response()->json([
                    'message' => 'This listing is not available for order.',
                ], 422);
            }

            if ($listing->seller_id === $request->user()->id) {
                return response()->json([
                    'message' => 'You cannot order your own listing.',
                ], 422);
            }

            $order = Order::create([
                'buyer_id' => $request->user()->id,
                'coin_listing_id' => $listing->id,
                'total_price' => $listing->price,
                'status' => 'pending',
                'buyer_name' => $validated['buyer_name'],
                'buyer_phone' => $validated['buyer_phone'] ?? null,
                'shipping_address' => $validated['shipping_address'],
            ]);

            $listing->update([
                'status' => 'sold',
            ]);

            return $order;
        });

        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        return response()->json([
            'message' => 'Order placed successfully',
            'data' => $order->load(['coinListing.images', 'coinListing.seller']),
        ], 201);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only view your own orders.',
            ], 403);
        }

        return response()->json([
            'data' => $order->load(['coinListing.images', 'coinListing.seller']),
        ]);
    }
}