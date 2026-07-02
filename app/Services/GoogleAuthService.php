<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthService
{
    public function isConfigured(): bool
    {
        return ! empty(Config::get('services.google.client_id'))
            && ! empty(Config::get('services.google.client_secret'));
    }

    public function getRedirectUrl(): string
    {
        return Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
    }

    public function findOrCreateUser(): User
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            if (! $user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }

            return $user;
        }

        $defaultRole = Role::where('slug', 'user')->first();

        return User::create([
            'role_id' => $defaultRole?->id,
            'name' => $googleUser->getName() ?? $googleUser->getEmail(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
    }
}
