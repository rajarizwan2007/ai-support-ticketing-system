<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;

/**
 * Admins and agents work on every ticket in their organization; customers
 * only see their own. Other organizations' tickets never load (tenant scope).
 */
class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return ! $user->hasRole(Role::CUSTOMER) || $ticket->requester_id === $user->id;
    }

    /**
     * Change status, priority or assignee.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return ! $user->hasRole(Role::CUSTOMER);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::ADMIN);
    }
}
