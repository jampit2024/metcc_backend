<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_can_list_users(): void
    {
        $admin = $this->createUser('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/users');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);
    }

    public function test_admin_cannot_update_super_admin(): void
    {
        $admin = $this->createUser('admin');
        $superAdmin = $this->createUser('super-admin');
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->putJson("/api/users/{$superAdmin->id}", [
            'name' => 'Blocked Update',
        ]);

        $response->assertForbidden();
    }

    private function createUser(string $roleSlug): User
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
