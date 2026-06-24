<?php

namespace Tests\Feature;

use App\Models\CoinListing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
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

    private function validOrderPayload(
        CoinListing $listing
    ): array {
        return [
            'coin_listing_id' => $listing->id,
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '081234567890',
            'shipping_address' => 'Jakarta, Indonesia',
        ];
    }

    public function test_guest_cannot_place_order(): void
    {
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        $this
            ->postJson(
                '/api/buyer/orders',
                $this->validOrderPayload($listing)
            )
            ->assertUnauthorized();
    }

    public function test_seller_cannot_access_buyer_order_route(): void
    {
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        $this
            ->actingAs($seller, 'sanctum')
            ->postJson(
                '/api/buyer/orders',
                $this->validOrderPayload($listing)
            )
            ->assertForbidden();
    }

    public function test_buyer_can_place_order_for_approved_listing(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');
        $listing = $this->createListing($seller);

        $response = $this
            ->actingAs($buyer, 'sanctum')
            ->postJson(
                '/api/buyer/orders',
                $this->validOrderPayload($listing)
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Order placed successfully'
            )
            ->assertJsonPath('data.buyer_id', $buyer->id)
            ->assertJsonPath(
                'data.coin_listing_id',
                $listing->id
            )
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath(
                'data.total_price',
                '75000.00'
            )
            ->assertJsonPath(
                'data.coin_listing.status',
                'sold'
            );

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $buyer->id,
            'coin_listing_id' => $listing->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('coin_listings', [
            'id' => $listing->id,
            'status' => 'sold',
        ]);
    }

    public function test_buyer_cannot_order_unavailable_listing(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller, [
            'status' => 'sold',
        ]);

        $this
            ->actingAs($buyer, 'sanctum')
            ->postJson(
                '/api/buyer/orders',
                $this->validOrderPayload($listing)
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'This listing is not available for order.'
            );

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_buyer_cannot_order_their_own_listing(): void
    {
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($buyer);

        $this
            ->actingAs($buyer, 'sanctum')
            ->postJson(
                '/api/buyer/orders',
                $this->validOrderPayload($listing)
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'You cannot order your own listing.'
            );

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_uses_current_listing_price(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller, [
            'price' => 125000,
        ]);

        $response = $this
            ->actingAs($buyer, 'sanctum')
            ->postJson(
                '/api/buyer/orders',
                $this->validOrderPayload($listing)
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.total_price',
                '125000.00'
            );

        $this->assertDatabaseHas('orders', [
            'coin_listing_id' => $listing->id,
            'total_price' => 125000,
        ]);
    }

    public function test_buyer_only_sees_their_own_orders(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');
        $otherBuyer = $this->createUser('buyer');

        $firstListing = $this->createListing($seller);

        $secondListing = $this->createListing($seller, [
            'title' => 'Second Listing',
        ]);

        $ownOrder = Order::create([
            'buyer_id' => $buyer->id,
            'coin_listing_id' => $firstListing->id,
            'total_price' => $firstListing->price,
            'status' => 'pending',
            'buyer_name' => 'Buyer One',
            'buyer_phone' => '081111111111',
            'shipping_address' => 'Jakarta',
        ]);

        Order::create([
            'buyer_id' => $otherBuyer->id,
            'coin_listing_id' => $secondListing->id,
            'total_price' => $secondListing->price,
            'status' => 'pending',
            'buyer_name' => 'Buyer Two',
            'buyer_phone' => '082222222222',
            'shipping_address' => 'Bandung',
        ]);

        $response = $this
            ->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/orders');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownOrder->id
            );
    }

    public function test_buyer_can_view_their_own_order_detail(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');
        $listing = $this->createListing($seller);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'coin_listing_id' => $listing->id,
            'total_price' => $listing->price,
            'status' => 'pending',
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '081234567890',
            'shipping_address' => 'Jakarta',
        ]);

        $response = $this
            ->actingAs($buyer, 'sanctum')
            ->getJson("/api/buyer/orders/{$order->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath(
                'data.coin_listing.id',
                $listing->id
            )
            ->assertJsonPath(
                'data.coin_listing.seller.id',
                $seller->id
            );
    }

    public function test_buyer_cannot_view_another_buyers_order(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');
        $otherBuyer = $this->createUser('buyer');
        $listing = $this->createListing($seller);

        $order = Order::create([
            'buyer_id' => $otherBuyer->id,
            'coin_listing_id' => $listing->id,
            'total_price' => $listing->price,
            'status' => 'pending',
            'buyer_name' => 'Other Buyer',
            'buyer_phone' => '081234567890',
            'shipping_address' => 'Surabaya',
        ]);

        $this
            ->actingAs($buyer, 'sanctum')
            ->getJson("/api/buyer/orders/{$order->id}")
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden. You can only view your own orders.'
            );
    }

    public function test_order_requires_shipping_information(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');
        $listing = $this->createListing($seller);

        $this
            ->actingAs($buyer, 'sanctum')
            ->postJson('/api/buyer/orders', [
                'coin_listing_id' => $listing->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'buyer_name',
                'shipping_address',
            ]);
    }
}