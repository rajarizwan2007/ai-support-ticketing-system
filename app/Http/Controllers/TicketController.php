<?php

namespace App\Http\Controllers;

use App\Enums\MessageType;
use App\Models\Role;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('tickets/index', [
            'tickets' => Ticket::visibleTo($request->user())
                ->with('requester:id,name', 'assignee:id,name')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Request $request, string $reference): Response
    {
        $ticket = Ticket::where('reference', $reference)
            ->with('requester:id,name', 'assignee:id,name', 'category:id,name')
            ->firstOrFail();

        Gate::authorize('view', $ticket);

        $messages = $ticket->messages()
            ->with('author:id,name')
            ->when($request->user()->hasRole(Role::CUSTOMER), fn ($query) => $query->where('type', '!=', MessageType::InternalNote))
            ->oldest()
            ->get();

        return Inertia::render('tickets/show', [
            'ticket' => $ticket,
            'messages' => $messages,
        ]);
    }
}
