<?php

namespace Tests\Feature;

use App\Models\CoinListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerListingApiTest extends TestCase
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

    private function validListingPayload(): array
    {
        return [
            'title' => 'Koin Belanda 1 Gulden Tahun 1970',
            'description' => 'Koin Belanda lama untuk kolektor.',
            'price' => 125000,
            'coin_origin' => 'Netherlands',
            'coin_year' => '1970',
            'material' => 'Silver',
            'condition' => 'Fine',
        ];
    }

    public function test_guest_cannot_create_listing(): void
    {
        $this->postJson(
            '/api/seller/listings',
            $this->validListingPayload()
        )->assertUnauthorized();
    }

    public function test_buyer_cannot_create_seller_listing(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $this
            ->actingAs($buyer, 'sanctum')
            ->postJson(
                '/api/seller/listings',
                $this->validListingPayload()
            )
            ->assertStatus(403);
    }

    public function test_seller_can_create_auto_approved_listing(): void
    {
        $seller = $this->createSeller();

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->postJson(
                '/api/seller/listings',
                $this->validListingPayload()
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Coin listing created successfully'
            )
            ->assertJsonPath('data.seller_id', $seller->id)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath(
                'data.coin_origin',
                'Netherlands'
            );

        $this->assertNotNull(
            $response->json('data.approved_at')
        );

        $this->assertDatabaseHas('coin_listings', [
            'seller_id' => $seller->id,
            'title' => 'Koin Belanda 1 Gulden Tahun 1970',
            'status' => 'approved',
        ]);
    }

    public function test_seller_only_sees_their_own_listings(): void
    {
        $seller = $this->createSeller();
        $otherSeller = $this->createSeller();

        $ownListing = $this->createListing($seller);

        $this->createListing($otherSeller, [
            'title' => 'Other Seller Listing',
        ]);

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->getJson('/api/seller/listings');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownListing->id
            );
    }

    public function test_seller_cannot_view_another_sellers_listing(): void
    {
        $seller = $this->createSeller();
        $otherSeller = $this->createSeller();

        $otherListing = $this->createListing(
            $otherSeller
        );

        $this
            ->actingAs($seller, 'sanctum')
            ->getJson(
                "/api/seller/listings/{$otherListing->id}"
            )
            ->assertStatus(403)
            ->assertJsonPath(
                'message',
                'Forbidden. You can only view your own listings.'
            );
    }

    public function test_seller_can_update_own_listing_and_it_becomes_approved(): void
    {
        $seller = $this->createSeller();

        $listing = $this->createListing($seller, [
            'status' => 'rejected',
            'approved_at' => null,
            'rejection_reason' => 'Photos are unclear.',
        ]);

        $payload = $this->validListingPayload();
        $payload['title'] = 'Updated Coin Listing';

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->putJson(
                "/api/seller/listings/{$listing->id}",
                $payload
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Coin listing updated successfully'
            )
            ->assertJsonPath(
                'data.title',
                'Updated Coin Listing'
            )
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath(
                'data.rejection_reason',
                null
            );

        $this->assertDatabaseHas('coin_listings', [
            'id' => $listing->id,
            'title' => 'Updated Coin Listing',
            'status' => 'approved',
            'rejection_reason' => null,
        ]);
    }

    public function test_sold_listing_cannot_be_updated(): void
    {
        $seller = $this->createSeller();

        $listing = $this->createListing($seller, [
            'status' => 'sold',
        ]);

        $this
            ->actingAs($seller, 'sanctum')
            ->putJson(
                "/api/seller/listings/{$listing->id}",
                $this->validListingPayload()
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Sold listings cannot be updated.'
            );
    }

    public function test_seller_can_delete_own_unsold_listing(): void
    {
        $seller = $this->createSeller();
        $listing = $this->createListing($seller);

        $this
            ->actingAs($seller, 'sanctum')
            ->deleteJson(
                "/api/seller/listings/{$listing->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Coin listing deleted successfully'
            );

        $this->assertDatabaseMissing('coin_listings', [
            'id' => $listing->id,
        ]);
    }

    public function test_sold_listing_cannot_be_deleted(): void
    {
        $seller = $this->createSeller();

        $listing = $this->createListing($seller, [
            'status' => 'sold',
        ]);

        $this
            ->actingAs($seller, 'sanctum')
            ->deleteJson(
                "/api/seller/listings/{$listing->id}"
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Sold listings cannot be deleted.'
            );

        $this->assertDatabaseHas('coin_listings', [
            'id' => $listing->id,
            'status' => 'sold',
        ]);
    }
}