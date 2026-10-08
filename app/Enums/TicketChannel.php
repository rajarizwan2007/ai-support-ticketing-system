<?php

namespace App\Enums;

enum TicketChannel: string
{
    case Web = 'web';
    case Email = 'email';
    case Api = 'api';
}
