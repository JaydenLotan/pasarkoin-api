<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\CoinImage;
use App\Models\CoinListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CoinListingImageController extends Controller
{
    public function index(Request $request, CoinListing $listing)
    {
        if ($listing->seller_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only view images for your own listings.',
            ], 403);
        }

        $images = $listing->images()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($image) {
                return [
                    'id' => $image->id,
                    'coin_listing_id' => $image->coin_listing_id,
                    'image_path' => $image->image_path,
                    'image_url' => asset('storage/' . $image->image_path),
                    'is_primary' => $image->is_primary,
                    'sort_order' => $image->sort_order,
                    'created_at' => $image->created_at,
                    'updated_at' => $image->updated_at,
                ];
            });

        return response()->json([
            'data' => $images,
        ]);
    }

    public function store(Request $request, CoinListing $listing)
    {
        if ($listing->seller_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only upload images for your own listings.',
            ], 403);
        }

        if ($listing->status === 'sold') {
            return response()->json([
                'message' => 'Sold listings cannot be updated.',
            ], 422);
        }

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $path = $request->file('image')->store('coin-images', 'public');

        $shouldBePrimary = $request->boolean('is_primary');

        $hasPrimaryImage = $listing->images()
            ->where('is_primary', true)
            ->exists();

        if (! $hasPrimaryImage) {
            $shouldBePrimary = true;
        }

        if ($shouldBePrimary) {
            $listing->images()->update([
                'is_primary' => false,
            ]);
        }

        $image = CoinImage::create([
            'coin_listing_id' => $listing->id,
            'image_path' => $path,
            'is_primary' => $shouldBePrimary,
            'sort_order' => $listing->images()->count() + 1,
        ]);

        $listing->update([
            'status' => 'approved',
            'approved_at' => $listing->approved_at ?? now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'message' => 'Coin image uploaded successfully',
            'data' => [
                'id' => $image->id,
                'coin_listing_id' => $image->coin_listing_id,
                'image_path' => $image->image_path,
                'image_url' => asset('storage/' . $image->image_path),
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
                'created_at' => $image->created_at,
                'updated_at' => $image->updated_at,
            ],
        ], 201);
    }

    public function destroy(Request $request, CoinListing $listing, CoinImage $image)
    {
        if ($listing->seller_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only delete images for your own listings.',
            ], 403);
        }

        if ($image->coin_listing_id !== $listing->id) {
            return response()->json([
                'message' => 'Image does not belong to this listing.',
            ], 404);
        }

        Storage::disk('public')->delete($image->image_path);

        $wasPrimary = $image->is_primary;

        $image->delete();

        if ($wasPrimary) {
            $nextImage = $listing->images()->oldest()->first();

            if ($nextImage) {
                $nextImage->update([
                    'is_primary' => true,
                ]);
            }
        }

        $listing->update([
            'status' => 'approved',
            'approved_at' => $listing->approved_at ?? now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'message' => 'Coin image deleted successfully',
        ]);
    }
}