<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinListing;
use Illuminate\Http\Request;

class CoinListingApprovalController extends Controller
{
    public function index()
    {
        $listings = CoinListing::with(['seller', 'images'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $listings,
        ]);
    }

    public function pending()
    {
        $listings = CoinListing::with(['seller', 'images'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json([
            'data' => $listings,
        ]);
    }

    public function approve(Request $request, CoinListing $listing)
    {
        if ($listing->status === 'sold') {
            return response()->json([
                'message' => 'Sold listings cannot be approved again.',
            ], 422);
        }

        $listing->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'message' => 'Coin listing approved successfully',
            'data' => $listing->load(['seller', 'images', 'approver']),
        ]);
    }

    public function reject(Request $request, CoinListing $listing)
    {
        if ($listing->status === 'sold') {
            return response()->json([
                'message' => 'Sold listings cannot be rejected.',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $listing->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => null,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'message' => 'Coin listing rejected successfully',
            'data' => $listing->load(['seller', 'images', 'approver']),
        ]);
    }
}