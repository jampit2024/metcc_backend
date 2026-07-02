<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['user', 'token']]);
    }

    public function test_user_can_login(): void
    {
        $role = Role::where('slug', 'user')->first();
        User::create([
            'role_id' => $role->id,
            'name' => 'Test User',
            'email' => 'login@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['user', 'token']]);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    private function createUser(string $roleSlug = 'user'): User
    {
        $role = Role::where('slug', $roleSlug)->first();

        return User::create([
            'role_id' => $role->id,
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }
}
