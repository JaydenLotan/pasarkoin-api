<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinListing;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request)
    {
        $query = CoinListing::with(['seller', 'images'])
            ->where('status', 'approved')
            ->latest();

        if ($request->filled('search')) {
            $search = $request->query('search');

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('coin_origin', 'like', "%{$search}%")
                    ->orWhere('coin_year', 'like', "%{$search}%")
                    ->orWhere('material', 'like', "%{$search}%")
                    ->orWhere('condition', 'like', "%{$search}%");
            });
        }

        if ($request->filled('coin_origin')) {
            $query->where('coin_origin', $request->query('coin_origin'));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->query('max_price'));
        }

        $listings = $query->paginate(12);

        return response()->json([
            'data' => $listings,
        ]);
    }

    public function show(CoinListing $listing)
    {
        if ($listing->status !== 'approved') {
            return response()->json([
                'message' => 'Listing not found.',
            ], 404);
        }

        return response()->json([
            'data' => $listing->load(['seller', 'images']),
        ]);
    }
}