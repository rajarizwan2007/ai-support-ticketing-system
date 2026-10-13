<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\ReplyToTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\MessageType;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ...$this->attachmentRules(),
        ]);

        unset($validated['attachments']);

        $ticket = $createTicket->handle($request->user(), $validated, $request->file('attachments', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket :reference created.', ['reference' => $ticket->reference])]);

        return to_route('tickets.show', $ticket->reference);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $messages = $ticket->messages()
            ->with('author:id,name', 'attachments')
            ->when($request->user()->hasRole(Role::CUSTOMER), fn ($query) => $query->where('type', '!=', MessageType::InternalNote))
            ->oldest()
            ->get();

        return Inertia::render('tickets/show', [
            'ticket' => $ticket->load('requester:id,name', 'assignee:id,name', 'category:id,name'),
            'attachments' => $ticket->attachments()->whereNull('message_id')->get(),
            'messages' => $messages,
            'canUpdate' => $canUpdate = $request->user()->can('update', $ticket),
            'statuses' => array_column(TicketStatus::cases(), 'value'),
            'priorities' => array_column(Priority::cases(), 'value'),
            'staff' => $canUpdate ? User::staff()->orderBy('name')->get(['id', 'name']) : [],
            'similarTickets' => $canUpdate && $ticket->embedding
                ? Ticket::whereKeyNot($ticket->id)
                    ->whereVectorSimilarTo('embedding', $ticket->embedding, minSimilarity: 0.5)
                    ->limit(5)
                    ->get(['id', 'reference', 'subject', 'status'])
                : [],
        ]);
    }

    public function reply(Request $request, Ticket $ticket, ReplyToTicket $replyToTicket): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['boolean'],
            ...$this->attachmentRules(),
        ]);

        $isInternal = $request->boolean('internal') && $request->user()->can('update', $ticket);

        $replyToTicket->handle($ticket, $request->user(), $validated['body'], $isInternal, $request->file('attachments', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => $isInternal ? __('Internal note added.') : __('Reply sent.')]);

        return back();
    }

    /**
     * Download a file from a ticket the user can view. Files on internal
     * notes are hidden from customers, like the notes themselves.
     */
    public function attachment(Request $request, Ticket $ticket, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $ticket);

        abort_if($request->user()->hasRole(Role::CUSTOMER) && $attachment->message?->type === MessageType::InternalNote, 404);

        return Storage::download($attachment->path, $attachment->original_name);
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket updated.')]);

        return back();
    }

    /**
     * Up to 3 files of 5 MB each, so a request stays under the 25 MB PHP and nginx limits.
     *
     * @return array<string, array<int, mixed>>
     */
    private function attachmentRules(): array
    {
        return [
            'attachments' => ['array', 'max:3'],
            'attachments.*' => [File::types(['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'csv', 'log', 'doc', 'docx', 'xls', 'xlsx', 'zip'])->max(5 * 1024)],
        ];
    }
}
