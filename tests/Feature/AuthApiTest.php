<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Buyer',
            'email' => 'buyer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('user.name', 'Test Buyer')
            ->assertJsonPath('user.email', 'buyer@example.com')
            ->assertJsonPath('user.role', 'buyer')
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'buyer@example.com',
            'role' => 'buyer',
        ]);
    }

    public function test_seller_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Seller',
            'email' => 'seller@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'seller',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.role', 'seller');

        $this->assertDatabaseHas('users', [
            'email' => 'seller@example.com',
            'role' => 'seller',
        ]);
    }

    public function test_public_registration_cannot_create_admin(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Fake Admin',
            'email' => 'fake-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', [
            'email' => 'fake-admin@example.com',
        ]);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Buyer',
            'email' => 'buyer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
            'role' => 'buyer',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'buyer@example.com',
            'password' => 'password123',
            'role' => 'buyer',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'buyer@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('user.email', 'buyer@example.com')
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_user_cannot_login_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'buyer@example.com',
            'password' => 'password123',
            'role' => 'buyer',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'buyer@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $user = User::factory()->create([
            'role' => 'seller',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'seller');
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'role' => 'buyer',
        ]);

        $token = $user
            ->createToken('test-token')
            ->plainTextToken;

        $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}