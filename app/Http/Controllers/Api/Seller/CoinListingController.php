<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\CoinListing;
use Illuminate\Http\Request;

class CoinListingController extends Controller
{
    public function index(Request $request)
    {
        $listings = CoinListing::where('seller_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $listings,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'coin_origin' => ['nullable', 'string', 'max:255'],
            'coin_year' => ['nullable', 'string', 'max:50'],
            'material' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
        ]);

        $listing = CoinListing::create([
            'seller_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'coin_origin' => $validated['coin_origin'] ?? null,
            'coin_year' => $validated['coin_year'] ?? null,
            'material' => $validated['material'] ?? null,
            'condition' => $validated['condition'] ?? null,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Coin listing created successfully',
            'data' => $listing,
        ], 201);
    }

    public function show(Request $request, CoinListing $listing)
    {
        if ($listing->seller_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only view your own listings.',
            ], 403);
        }

        return response()->json([
            'data' => $listing,
        ]);
    }

    public function update(Request $request, CoinListing $listing)
    {
        if ($listing->seller_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only update your own listings.',
            ], 403);
        }

        if ($listing->status === 'sold') {
            return response()->json([
                'message' => 'Sold listings cannot be updated.',
            ], 422);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'coin_origin' => ['nullable', 'string', 'max:255'],
            'coin_year' => ['nullable', 'string', 'max:50'],
            'material' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
        ]);

        $listing->update(array_merge($validated, [
            'status' => 'approved',
            'approved_at' => $listing->approved_at ?? now(),
            'rejection_reason' => null,
        ]));

        return response()->json([
            'message' => 'Coin listing updated successfully',
            'data' => $listing,
        ]);
    }

    public function destroy(Request $request, CoinListing $listing)
    {
        if ($listing->seller_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only delete your own listings.',
            ], 403);
        }

        if ($listing->status === 'sold') {
            return response()->json([
                'message' => 'Sold listings cannot be deleted.',
            ], 422);
        }

        $listing->delete();

        return response()->json([
            'message' => 'Coin listing deleted successfully',
        ]);
    }
}