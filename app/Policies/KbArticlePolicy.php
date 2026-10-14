<?php

namespace App\Policies;

use App\Models\KbArticle;
use App\Models\Role;
use App\Models\User;

/**
 * Admins and agents manage the knowledge base; customers don't see it yet.
 * Other organizations' articles never load (tenant scope).
 */
class KbArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->hasRole(Role::CUSTOMER);
    }

    public function create(User $user): bool
    {
        return ! $user->hasRole(Role::CUSTOMER);
    }

    public function update(User $user, KbArticle $article): bool
    {
        return ! $user->hasRole(Role::CUSTOMER);
    }

    public function delete(User $user, KbArticle $article): bool
    {
        return ! $user->hasRole(Role::CUSTOMER);
    }
}
