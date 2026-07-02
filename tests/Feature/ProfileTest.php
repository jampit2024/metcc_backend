<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_user_can_update_profile(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/profile/update', [
            'name' => 'Updated Name',
            'phone' => '1234567890',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    private function createUser(): User
    {
        $role = Role::where('slug', 'user')->first();

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
