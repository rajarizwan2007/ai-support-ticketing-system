<?php

namespace App\Models\Scopes;

use App\Exceptions\MissingOrganizationException;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class OrganizationScope implements Scope
{
    /**
     * Limit the query to the current organization, failing closed when none is set.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = Organization::currentId() ?? throw MissingOrganizationException::forModel($model::class);

        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }
}
