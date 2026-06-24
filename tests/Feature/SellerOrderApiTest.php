<?php

namespace Tests\Feature;

use App\Models\CoinListing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerOrderApiTest extends TestCase
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
            'status' => 'sold',
            'approved_at' => now(),
        ], $overrides));
    }

    private function createOrder(
        User $buyer,
        CoinListing $listing,
        array $overrides = []
    ): Order {
        return Order::create(array_merge([
            'buyer_id' => $buyer->id,
            'coin_listing_id' => $listing->id,
            'total_price' => $listing->price,
            'status' => 'pending',
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '081234567890',
            'shipping_address' => 'Jakarta, Indonesia',
            'seller_note' => null,
        ], $overrides));
    }

    public function test_guest_cannot_view_seller_orders(): void
    {
        $this->getJson('/api/seller/orders')
            ->assertUnauthorized();
    }

    public function test_buyer_cannot_access_seller_orders(): void
    {
        $buyer = $this->createUser('buyer');

        $this
            ->actingAs($buyer, 'sanctum')
            ->getJson('/api/seller/orders')
            ->assertForbidden();
    }

    public function test_seller_only_sees_orders_for_their_own_listings(): void
    {
        $seller = $this->createUser('seller');
        $otherSeller = $this->createUser('seller');

        $buyer = $this->createUser('buyer');
        $otherBuyer = $this->createUser('buyer');

        $ownListing = $this->createListing($seller);

        $otherListing = $this->createListing($otherSeller, [
            'title' => 'Other Seller Listing',
        ]);

        $ownOrder = $this->createOrder(
            $buyer,
            $ownListing
        );

        $this->createOrder(
            $otherBuyer,
            $otherListing
        );

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->getJson('/api/seller/orders');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownOrder->id
            );
    }

    public function test_seller_can_view_order_for_their_own_listing(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller);
        $order = $this->createOrder($buyer, $listing);

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->getJson("/api/seller/orders/{$order->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath(
                'data.buyer.id',
                $buyer->id
            )
            ->assertJsonPath(
                'data.coin_listing.id',
                $listing->id
            );
    }

    public function test_seller_cannot_view_another_sellers_order(): void
    {
        $seller = $this->createUser('seller');
        $otherSeller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($otherSeller);
        $order = $this->createOrder($buyer, $listing);

        $this
            ->actingAs($seller, 'sanctum')
            ->getJson("/api/seller/orders/{$order->id}")
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden. You can only view orders for your own listings.'
            );
    }

    public function test_seller_can_confirm_pending_order_and_save_note(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller);
        $order = $this->createOrder($buyer, $listing);

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->patchJson(
                "/api/seller/orders/{$order->id}/status",
                [
                    'status' => 'confirmed',
                    'seller_note' =>
                        'Pesanan sedang dipersiapkan.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Order status updated successfully'
            )
            ->assertJsonPath(
                'data.status',
                'confirmed'
            )
            ->assertJsonPath(
                'data.seller_note',
                'Pesanan sedang dipersiapkan.'
            );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
            'seller_note' =>
                'Pesanan sedang dipersiapkan.',
        ]);
    }

    public function test_seller_cannot_skip_from_pending_to_shipped(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller);
        $order = $this->createOrder($buyer, $listing);

        $this
            ->actingAs($seller, 'sanctum')
            ->patchJson(
                "/api/seller/orders/{$order->id}/status",
                [
                    'status' => 'shipped',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Order status cannot be changed from pending to shipped.'
            );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_order_can_progress_from_confirmed_to_completed(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller);

        $order = $this->createOrder($buyer, $listing, [
            'status' => 'confirmed',
        ]);

        $this
            ->actingAs($seller, 'sanctum')
            ->patchJson(
                "/api/seller/orders/{$order->id}/status",
                [
                    'status' => 'shipped',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'shipped'
            );

        $this
            ->actingAs($seller, 'sanctum')
            ->patchJson(
                "/api/seller/orders/{$order->id}/status",
                [
                    'status' => 'completed',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'completed'
            );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }

    public function test_completed_order_cannot_be_changed(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller);

        $order = $this->createOrder($buyer, $listing, [
            'status' => 'completed',
        ]);

        $this
            ->actingAs($seller, 'sanctum')
            ->patchJson(
                "/api/seller/orders/{$order->id}/status",
                [
                    'status' => 'shipped',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Order status cannot be changed from completed to shipped.'
            );
    }

    public function test_seller_can_cancel_pending_order_and_restore_listing(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller, [
            'status' => 'sold',
        ]);

        $order = $this->createOrder($buyer, $listing);

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->patchJson(
                "/api/seller/orders/{$order->id}/status",
                [
                    'status' => 'cancelled',
                    'seller_note' =>
                        'Koin tidak tersedia.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'cancelled'
            )
            ->assertJsonPath(
                'data.coin_listing.status',
                'approved'
            );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('coin_listings', [
            'id' => $listing->id,
            'status' => 'approved',
        ]);
    }

    public function test_cancelled_order_cannot_be_changed(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller, [
            'status' => 'approved',
        ]);

        $order = $this->createOrder($buyer, $listing, [
            'status' => 'cancelled',
        ]);

        $this
            ->actingAs($seller, 'sanctum')
            ->patchJson(
                "/api/seller/orders/{$order->id}/status",
                [
                    'status' => 'confirmed',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Order status cannot be changed from cancelled to confirmed.'
            );
    }
}