<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\ReplyToTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\MessageType;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Category;
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
        $search = $request->string('search')->trim()->value();
        $status = TicketStatus::tryFrom($request->string('status')->value());
        $priority = Priority::tryFrom($request->string('priority')->value());
        $assignee = $request->string('assignee')->value();

        return Inertia::render('tickets/index', [
            'tickets' => Ticket::visibleTo($request->user())
                ->with('requester:id,name', 'assignee:id,name')
                ->when($search !== '', fn ($query) => $query->whereAny(['reference', 'subject'], 'ilike', '%'.$search.'%'))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($priority, fn ($query) => $query->where('priority', $priority))
                ->when($assignee === 'unassigned', fn ($query) => $query->whereNull('assignee_id'))
                ->when(ctype_digit($assignee), fn ($query) => $query->where('assignee_id', $assignee))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only('search', 'status', 'priority', 'assignee'),
            'statuses' => array_column(TicketStatus::cases(), 'value'),
            'priorities' => array_column(Priority::cases(), 'value'),
            'staff' => $request->user()->hasRole(Role::CUSTOMER) ? [] : User::staff()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tickets/create', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'priorities' => array_column(Priority::cases(), 'value'),
        ]);
    }

    public function store(Request $request, CreateTicket $createTicket): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'category_id' => ['nullable', Rule::in(Category::pluck('id'))],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
        ]);

        $ticket = $createTicket->handle($request->user(), $validated);

        return to_route('tickets.show', $ticket->reference);
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

    public function reply(Request $request, Ticket $ticket, ReplyToTicket $replyToTicket): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['boolean'],
        ]);

        $isInternal = $request->boolean('internal') && $request->user()->can('update', $ticket);

        $replyToTicket->handle($ticket, $request->user(), $validated['body'], $isInternal);

        return back();
    }

    public function update(Request $request, Ticket $ticket, UpdateTicket $updateTicket): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'assignee_id' => ['sometimes', 'nullable', Rule::in(User::staff()->pluck('id'))],
        ]);

        $updateTicket->handle($ticket, $request->user(), $validated);

        return back();
    }
}
