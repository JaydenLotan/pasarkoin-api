<?php

namespace Tests\Feature;

use App\Models\CoinListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminListingModerationApiTest extends TestCase
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
            'approved_by' => null,
            'approved_at' => now(),
            'rejection_reason' => null,
        ], $overrides));
    }

    public function test_guest_cannot_access_admin_listings(): void
    {
        $this->getJson('/api/admin/listings')
            ->assertUnauthorized();
    }

    public function test_seller_cannot_access_admin_listings(): void
    {
        $seller = $this->createUser('seller');

        $this
            ->actingAs($seller, 'sanctum')
            ->getJson('/api/admin/listings')
            ->assertForbidden();
    }

    public function test_admin_can_view_all_listings(): void
    {
        $admin = $this->createUser('admin');
        $seller = $this->createUser('seller');

        $approvedListing = $this->createListing($seller, [
            'title' => 'Approved Listing',
            'status' => 'approved',
        ]);

        $rejectedListing = $this->createListing($seller, [
            'title' => 'Rejected Listing',
            'status' => 'rejected',
            'approved_at' => null,
            'rejection_reason' => 'Incorrect information.',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/listings');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $approvedListing->id,
                'title' => 'Approved Listing',
            ])
            ->assertJsonFragment([
                'id' => $rejectedListing->id,
                'title' => 'Rejected Listing',
            ]);
    }

    public function test_pending_endpoint_only_returns_pending_listings(): void
    {
        $admin = $this->createUser('admin');
        $seller = $this->createUser('seller');

        $pendingListing = $this->createListing($seller, [
            'title' => 'Pending Listing',
            'status' => 'pending',
            'approved_at' => null,
        ]);

        $this->createListing($seller, [
            'title' => 'Approved Listing',
            'status' => 'approved',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/listings/pending');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $pendingListing->id
            )
            ->assertJsonPath(
                'data.0.status',
                'pending'
            );
    }

    public function test_admin_can_approve_rejected_listing(): void
    {
        $admin = $this->createUser('admin');
        $seller = $this->createUser('seller');

        $listing = $this->createListing($seller, [
            'status' => 'rejected',
            'approved_at' => null,
            'rejection_reason' => 'Photos are unclear.',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/admin/listings/{$listing->id}/approve"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Coin listing approved successfully'
            )
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath(
                'data.approved_by',
                $admin->id
            )
            ->assertJsonPath(
                'data.rejection_reason',
                null
            )
            ->assertJsonPath(
                'data.approver.id',
                $admin->id
            );

        $this->assertNotNull(
            $response->json('data.approved_at')
        );

        $this->assertDatabaseHas('coin_listings', [
            'id' => $listing->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
            'rejection_reason' => null,
        ]);
    }

    public function test_admin_can_reject_approved_listing(): void
    {
        $admin = $this->createUser('admin');
        $seller = $this->createUser('seller');

        $listing = $this->createListing($seller);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/admin/listings/{$listing->id}/reject",
                [
                    'rejection_reason' =>
                        'Listing information is misleading.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Coin listing rejected successfully'
            )
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath(
                'data.approved_by',
                $admin->id
            )
            ->assertJsonPath(
                'data.approved_at',
                null
            )
            ->assertJsonPath(
                'data.rejection_reason',
                'Listing information is misleading.'
            )
            ->assertJsonPath(
                'data.approver.id',
                $admin->id
            );

        $listing->refresh();

        $this->assertSame('rejected', $listing->status);
        $this->assertSame(
            $admin->id,
            $listing->approved_by
        );
        $this->assertNull($listing->approved_at);
    }

    public function test_rejection_reason_is_required(): void
    {
        $admin = $this->createUser('admin');
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/admin/listings/{$listing->id}/reject",
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'rejection_reason',
            ]);

        $this->assertDatabaseHas('coin_listings', [
            'id' => $listing->id,
            'status' => 'approved',
        ]);
    }

    public function test_sold_listing_cannot_be_approved_again(): void
    {
        $admin = $this->createUser('admin');
        $seller = $this->createUser('seller');

        $listing = $this->createListing($seller, [
            'status' => 'sold',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/admin/listings/{$listing->id}/approve"
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Sold listings cannot be approved again.'
            );

        $this->assertDatabaseHas('coin_listings', [
            'id' => $listing->id,
            'status' => 'sold',
        ]);
    }

    public function test_sold_listing_cannot_be_rejected(): void
    {
        $admin = $this->createUser('admin');
        $seller = $this->createUser('seller');

        $listing = $this->createListing($seller, [
            'status' => 'sold',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/admin/listings/{$listing->id}/reject",
                [
                    'rejection_reason' =>
                        'This should not be accepted.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Sold listings cannot be rejected.'
            );

        $this->assertDatabaseHas('coin_listings', [
            'id' => $listing->id,
            'status' => 'sold',
        ]);
    }
}