<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === 'super-admin';
    }

    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    public function isAdminOrAbove(): bool
    {
        return in_array($this->slug, ['super-admin', 'admin'], true);
    }
}
