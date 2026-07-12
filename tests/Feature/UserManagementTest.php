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

    public function test_admin_can_disable_and_enable_user(): void
    {
        $admin = $this->createUser('admin');
        $proctor = $this->createUser('proctor');
        $token = $admin->createToken('test')->plainTextToken;

        $disableResponse = $this->withToken($token)->patchJson("/api/users/{$proctor->id}/status", [
            'status' => 'inactive',
        ]);

        $disableResponse->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $enableResponse = $this->withToken($token)->patchJson("/api/users/{$proctor->id}/status", [
            'status' => 'active',
        ]);

        $enableResponse->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_roles_endpoint_returns_admin_and_proctor_only(): void
    {
        $admin = $this->createUser('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/roles');

        $response->assertOk();
        $this->assertSame(['admin', 'proctor'], collect($response->json('data'))->pluck('slug')->all());
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
