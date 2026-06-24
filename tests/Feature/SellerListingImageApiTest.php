<?php

namespace Tests\Feature;

use App\Models\CoinImage;
use App\Models\CoinListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerListingImageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

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

    private function fakeImage(
        string $filename = 'coin.jpg'
    ): UploadedFile {
        return UploadedFile::fake()
            ->image($filename, 600, 600)
            ->size(500);
    }

    private function createImage(
        CoinListing $listing,
        array $overrides = []
    ): CoinImage {
        return CoinImage::create(array_merge([
            'coin_listing_id' => $listing->id,
            'image_path' => 'coin-images/test-coin.jpg',
            'is_primary' => false,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_guest_cannot_upload_listing_image(): void
    {
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        $this->post(
            "/api/seller/listings/{$listing->id}/images",
            [
                'image' => $this->fakeImage(),
            ],
            [
                'Accept' => 'application/json',
            ]
        )->assertUnauthorized();
    }

    public function test_buyer_cannot_upload_listing_image(): void
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('buyer');

        $listing = $this->createListing($seller);

        $this
            ->actingAs($buyer, 'sanctum')
            ->post(
                "/api/seller/listings/{$listing->id}/images",
                [
                    'image' => $this->fakeImage(),
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertForbidden();
    }

    public function test_seller_cannot_upload_image_to_another_sellers_listing(): void
    {
        $seller = $this->createUser('seller');
        $otherSeller = $this->createUser('seller');

        $listing = $this->createListing($otherSeller);

        $this
            ->actingAs($seller, 'sanctum')
            ->post(
                "/api/seller/listings/{$listing->id}/images",
                [
                    'image' => $this->fakeImage(),
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden. You can only upload images for your own listings.'
            );
    }

    public function test_seller_can_upload_first_listing_image(): void
    {
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->post(
                "/api/seller/listings/{$listing->id}/images",
                [
                    'image' => $this->fakeImage(),
                ],
                [
                    'Accept' => 'application/json',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Coin image uploaded successfully'
            )
            ->assertJsonPath(
                'data.coin_listing_id',
                $listing->id
            )
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.sort_order', 1)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'coin_listing_id',
                    'image_path',
                    'image_url',
                    'is_primary',
                    'sort_order',
                ],
            ]);

        $image = CoinImage::first();

        $this->assertNotNull($image);
        $this->assertTrue($image->is_primary);

        Storage::disk('public')
            ->assertExists($image->image_path);
    }

    public function test_second_image_is_not_primary_by_default(): void
    {
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        $this
            ->actingAs($seller, 'sanctum')
            ->post(
                "/api/seller/listings/{$listing->id}/images",
                [
                    'image' => $this->fakeImage('first.jpg'),
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertCreated();

        $this
            ->actingAs($seller, 'sanctum')
            ->post(
                "/api/seller/listings/{$listing->id}/images",
                [
                    'image' => $this->fakeImage('second.jpg'),
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.is_primary', false)
            ->assertJsonPath('data.sort_order', 2);

        $images = CoinImage::orderBy('id')->get();

        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
    }

    public function test_seller_can_make_new_image_primary(): void
    {
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        $this
            ->actingAs($seller, 'sanctum')
            ->post(
                "/api/seller/listings/{$listing->id}/images",
                [
                    'image' => $this->fakeImage('first.jpg'),
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertCreated();

        $this
            ->actingAs($seller, 'sanctum')
            ->post(
                "/api/seller/listings/{$listing->id}/images",
                [
                    'image' => $this->fakeImage('second.jpg'),
                    'is_primary' => 1,
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.is_primary', true);

        $images = CoinImage::orderBy('id')->get();

        $this->assertFalse($images[0]->is_primary);
        $this->assertTrue($images[1]->is_primary);
    }

    public function test_seller_can_view_images_for_their_listing(): void
    {
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        $normalImage = $this->createImage($listing, [
            'image_path' => 'coin-images/normal.jpg',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $primaryImage = $this->createImage($listing, [
            'image_path' => 'coin-images/primary.jpg',
            'is_primary' => true,
            'sort_order' => 2,
        ]);

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->getJson(
                "/api/seller/listings/{$listing->id}/images"
            );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.id',
                $primaryImage->id
            )
            ->assertJsonPath(
                'data.1.id',
                $normalImage->id
            )
            ->assertJsonPath(
                'data.0.is_primary',
                true
            );
    }

    public function test_seller_cannot_view_another_sellers_listing_images(): void
    {
        $seller = $this->createUser('seller');
        $otherSeller = $this->createUser('seller');

        $listing = $this->createListing($otherSeller);

        $this
            ->actingAs($seller, 'sanctum')
            ->getJson(
                "/api/seller/listings/{$listing->id}/images"
            )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden. You can only view images for your own listings.'
            );
    }

    public function test_seller_can_delete_own_listing_image(): void
    {
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        Storage::disk('public')->put(
            'coin-images/delete-me.jpg',
            'fake image content'
        );

        $image = $this->createImage($listing, [
            'image_path' => 'coin-images/delete-me.jpg',
            'is_primary' => true,
        ]);

        $this
            ->actingAs($seller, 'sanctum')
            ->deleteJson(
                "/api/seller/listings/{$listing->id}/images/{$image->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Coin image deleted successfully'
            );

        $this->assertDatabaseMissing('coin_images', [
            'id' => $image->id,
        ]);

        Storage::disk('public')
            ->assertMissing('coin-images/delete-me.jpg');
    }

    public function test_deleting_primary_image_promotes_next_image(): void
    {
        $seller = $this->createUser('seller');
        $listing = $this->createListing($seller);

        $primaryImage = $this->createImage($listing, [
            'image_path' => 'coin-images/primary.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $nextImage = $this->createImage($listing, [
            'image_path' => 'coin-images/next.jpg',
            'is_primary' => false,
            'sort_order' => 2,
        ]);

        $this
            ->actingAs($seller, 'sanctum')
            ->deleteJson(
                "/api/seller/listings/{$listing->id}/images/{$primaryImage->id}"
            )
            ->assertOk();

        $this->assertTrue(
            $nextImage->fresh()->is_primary
        );
    }

    public function test_sold_listing_cannot_receive_new_image(): void
    {
        $seller = $this->createUser('seller');

        $listing = $this->createListing($seller, [
            'status' => 'sold',
        ]);

        $this
            ->actingAs($seller, 'sanctum')
            ->post(
                "/api/seller/listings/{$listing->id}/images",
                [
                    'image' => $this->fakeImage(),
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Sold listings cannot be updated.'
            );

        $this->assertDatabaseCount('coin_images', 0);
    }

    public function test_image_cannot_be_deleted_through_wrong_listing(): void
    {
        $seller = $this->createUser('seller');

        $firstListing = $this->createListing($seller);

        $secondListing = $this->createListing($seller, [
            'title' => 'Second Listing',
        ]);

        $image = $this->createImage($secondListing);

        $this
            ->actingAs($seller, 'sanctum')
            ->deleteJson(
                "/api/seller/listings/{$firstListing->id}/images/{$image->id}"
            )
            ->assertNotFound()
            ->assertJsonPath(
                'message',
                'Image does not belong to this listing.'
            );

        $this->assertDatabaseHas('coin_images', [
            'id' => $image->id,
            'coin_listing_id' => $secondListing->id,
        ]);
    }
}