<?php

namespace Tests\Feature;

use App\Models\CoinListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingBrowseApiTest extends TestCase
{
    use RefreshDatabase;

    private function createSeller(): User
    {
        return User::factory()->create([
            'role' => 'seller',
        ]);
    }

    private function createListing(
        User $seller,
        array $overrides = []
    ): CoinListing {
        return CoinListing::create(array_merge([
            'seller_id' => $seller->id,
            'title' => 'Koin Jepang 100 Yen Tahun 1967',
            'description' => 'Koin lama Jepang untuk kolektor.',
            'price' => 75000,
            'coin_origin' => 'Japan',
            'coin_year' => '1967',
            'material' => 'Nickel',
            'condition' => 'Very Fine',
            'status' => 'approved',
            'approved_at' => now(),
        ], $overrides));
    }

    public function test_public_can_browse_approved_listings(): void
    {
        $seller = $this->createSeller();

        $listing = $this->createListing($seller);

        $response = $this->getJson('/api/listings');

        $response
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $listing->id)
            ->assertJsonPath(
                'data.data.0.title',
                'Koin Jepang 100 Yen Tahun 1967'
            );
    }

    public function test_public_browse_only_shows_approved_listings(): void
    {
        $seller = $this->createSeller();

        $approved = $this->createListing($seller, [
            'title' => 'Approved Listing',
        ]);

        $this->createListing($seller, [
            'title' => 'Rejected Listing',
            'status' => 'rejected',
        ]);

        $this->createListing($seller, [
            'title' => 'Sold Listing',
            'status' => 'sold',
        ]);

        $this->createListing($seller, [
            'title' => 'Pending Listing',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/listings');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath(
                'data.data.0.id',
                $approved->id
            );
    }

    public function test_public_can_view_approved_listing_detail(): void
    {
        $seller = $this->createSeller();

        $listing = $this->createListing($seller);

        $response = $this->getJson(
            "/api/listings/{$listing->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $listing->id)
            ->assertJsonPath(
                'data.seller.id',
                $seller->id
            )
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'price',
                    'seller',
                    'images',
                ],
            ]);
    }

    public function test_public_cannot_view_rejected_listing(): void
    {
        $seller = $this->createSeller();

        $listing = $this->createListing($seller, [
            'status' => 'rejected',
        ]);

        $this->getJson("/api/listings/{$listing->id}")
            ->assertNotFound()
            ->assertJsonPath(
                'message',
                'Listing not found.'
            );
    }

    public function test_public_cannot_view_sold_listing(): void
    {
        $seller = $this->createSeller();

        $listing = $this->createListing($seller, [
            'status' => 'sold',
        ]);

        $this->getJson("/api/listings/{$listing->id}")
            ->assertNotFound()
            ->assertJsonPath(
                'message',
                'Listing not found.'
            );
    }

    public function test_public_can_search_listings(): void
    {
        $seller = $this->createSeller();

        $matchingListing = $this->createListing($seller, [
            'title' => 'Koin Inggris 10 Pence',
            'coin_origin' => 'United Kingdom',
        ]);

        $this->createListing($seller, [
            'title' => 'Koin Jepang 100 Yen',
            'coin_origin' => 'Japan',
        ]);

        $response = $this->getJson(
            '/api/listings?search=Inggris'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath(
                'data.data.0.id',
                $matchingListing->id
            );
    }

    public function test_public_can_filter_by_coin_origin(): void
    {
        $seller = $this->createSeller();

        $japanListing = $this->createListing($seller, [
            'title' => 'Japan Coin',
            'coin_origin' => 'Japan',
        ]);

        $this->createListing($seller, [
            'title' => 'Malaysia Coin',
            'coin_origin' => 'Malaysia',
        ]);

        $response = $this->getJson(
            '/api/listings?coin_origin=Japan'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath(
                'data.data.0.id',
                $japanListing->id
            );
    }

    public function test_public_can_filter_by_price_range(): void
    {
        $seller = $this->createSeller();

        $matchingListing = $this->createListing($seller, [
            'title' => 'Medium Price Coin',
            'price' => 75000,
        ]);

        $this->createListing($seller, [
            'title' => 'Cheap Coin',
            'price' => 25000,
        ]);

        $this->createListing($seller, [
            'title' => 'Expensive Coin',
            'price' => 200000,
        ]);

        $response = $this->getJson(
            '/api/listings?min_price=50000&max_price=100000'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath(
                'data.data.0.id',
                $matchingListing->id
            );
    }

    public function test_public_listing_results_are_paginated(): void
    {
        $seller = $this->createSeller();

        for ($number = 1; $number <= 13; $number++) {
            $this->createListing($seller, [
                'title' => "Coin Listing {$number}",
            ]);
        }

        $response = $this->getJson('/api/listings');

        $response
            ->assertOk()
            ->assertJsonCount(12, 'data.data')
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.per_page', 12)
            ->assertJsonPath('data.total', 13)
            ->assertJsonPath('data.last_page', 2);
    }
}