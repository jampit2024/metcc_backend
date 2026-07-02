<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function createToken(User $user, string $name = 'auth-token'): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function deleteAccount(User $user, string $password): bool
    {
        if (! Hash::check($password, $user->password)) {
            return false;
        }

        $user->tokens()->delete();
        $user->delete();

        return true;
    }
}
