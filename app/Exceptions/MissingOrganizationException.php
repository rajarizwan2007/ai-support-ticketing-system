<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when tenant-owned data is queried or created with no current organization,
 * so a missing tenant fails loudly instead of exposing every tenant's rows.
 */
class MissingOrganizationException extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self("No current organization is set; cannot access [{$model}]. Call \$organization->makeCurrent() first, or use withoutGlobalScope(OrganizationScope::class) for deliberate cross-tenant work.");
    }
}
