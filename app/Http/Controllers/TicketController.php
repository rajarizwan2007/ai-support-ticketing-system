<?php

namespace App\Http\Controllers;

use App\Enums\MessageType;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $messages = $ticket->messages()
            ->with('author:id,name')
            ->when($request->user()->hasRole(Role::CUSTOMER), fn ($query) => $query->where('type', '!=', MessageType::InternalNote))
            ->oldest()
            ->get();

        return Inertia::render('tickets/show', [
            'ticket' => $ticket->load('requester:id,name', 'assignee:id,name', 'category:id,name'),
            'messages' => $messages,
            'canUpdate' => $canUpdate = $request->user()->can('update', $ticket),
            'statuses' => array_column(TicketStatus::cases(), 'value'),
            'priorities' => array_column(Priority::cases(), 'value'),
            'staff' => $canUpdate ? User::staff()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['boolean'],
        ]);

        $isInternal = $request->boolean('internal') && $request->user()->can('update', $ticket);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'type' => $isInternal ? MessageType::InternalNote : MessageType::Reply,
            'body' => $validated['body'],
        ]);

        return back();
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'assignee_id' => ['sometimes', 'nullable', Rule::in(User::staff()->pluck('id'))],
        ]);

        $ticket->update($validated);

        $changes = [];

        if ($ticket->wasChanged('status')) {
            $changes[] = 'Status changed to '.str_replace('_', ' ', $validated['status']);
        }

        if ($ticket->wasChanged('priority')) {
            $changes[] = 'Priority changed to '.$validated['priority'];
        }

        if ($ticket->wasChanged('assignee_id')) {
            $changes[] = $ticket->assignee ? 'Assigned to '.$ticket->assignee->name : 'Unassigned';
        }

        foreach ($changes as $change) {
            $ticket->messages()->create([
                'type' => MessageType::System,
                'body' => $change.' by '.$request->user()->name.'.',
            ]);
        }

        return back();
    }
}
