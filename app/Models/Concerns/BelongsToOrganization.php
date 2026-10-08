<?php

namespace App\Models\Concerns;

use App\Exceptions\MissingOrganizationException;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes every query to the current organization and fills
 * organization_id on create. See docs/adr/0001-multi-tenancy.md.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute(
                    'organization_id',
                    Organization::currentId() ?? throw MissingOrganizationException::forModel($model::class),
                );
            }
        });
    }
}
