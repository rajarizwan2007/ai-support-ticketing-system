<?php

namespace App\Enums;

/**
 * The two SLA deadlines on a ticket, and the columns that track each one.
 */
enum SlaTarget: string
{
    case FirstResponse = 'first_response';
    case Resolution = 'resolution';

    public function dueColumn(): string
    {
        return $this->value.'_due_at';
    }

    public function breachedColumn(): string
    {
        return $this->value.'_breached_at';
    }

    /**
     * The column that is set once the target is met.
     */
    public function metColumn(): string
    {
        return match ($this) {
            self::FirstResponse => 'first_responded_at',
            self::Resolution => 'resolved_at',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FirstResponse => 'First response',
            self::Resolution => 'Resolution',
        };
    }
}
