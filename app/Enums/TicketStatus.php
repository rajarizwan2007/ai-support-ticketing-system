<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
