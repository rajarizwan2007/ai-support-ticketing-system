<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Context;

/**
 * The tenant. Its hasMany relations (users, tickets, ...) also pass through the
 * tenant scope, so they only return rows while this organization is current;
 * for any other organization they return nothing, and with none they throw.
 */
#[Fillable(['name', 'slug', 'settings'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * Hidden Context key holding the current organization's id. Context is
     * per request and is carried into queued jobs automatically.
     */
    private const CURRENT_KEY = 'organization_id';

    /**
     * Make this the organization that tenant-owned queries are scoped to.
     */
    public function makeCurrent(): static
    {
        Context::addHidden(self::CURRENT_KEY, $this->getKey());

        return $this;
    }

    public static function currentId(): ?int
    {
        return Context::getHidden(self::CURRENT_KEY);
    }

    public static function current(): ?self
    {
        $organizationId = static::currentId();

        return $organizationId === null ? null : static::find($organizationId);
    }

    public static function forgetCurrent(): void
    {
        Context::forgetHidden(self::CURRENT_KEY);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<SlaPolicy, $this>
     */
    public function slaPolicies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<KbArticle, $this>
     */
    public function kbArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class);
    }
}
