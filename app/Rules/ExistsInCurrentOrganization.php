<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that an id refers to a record in the current organization.
 *
 * Use it instead of Rule::exists() for foreign keys from requests: Rule::exists()
 * queries the table directly and would accept another tenant's id. Records in
 * other tenants get the same generic message as missing ones.
 */
class ExistsInCurrentOrganization implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model  A model using BelongsToOrganization.
     */
    public function __construct(private string $model) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id === false || ! $this->model::query()->whereKey($id)->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
