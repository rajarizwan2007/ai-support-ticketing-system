<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'permissions'])]
class Role extends Model
{
    public const ADMIN = 'admin';

    public const AGENT = 'agent';

    public const CUSTOMER = 'customer';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    /**
     * Find a role by slug, creating it if it does not exist yet.
     */
    public static function findOrCreateBySlug(string $slug): self
    {
        return static::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
