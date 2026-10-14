<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\ReplyToTicket;
use App\Enums\MessageType;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Resources\TicketResource;
use App\Models\Category;
use App\Models\Message;
use App\Models\Role;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * The same rules as the web app: customers only see their own tickets and
 * never see internal notes; staff see every ticket in the organization.
 */
#[Group('Tickets')]
class TicketController extends Controller
{
    /**
     * List tickets
     *
     * Newest first, 20 per page.
     */
    #[QueryParam('status', 'string', 'Only tickets with this status.', required: false, enum: TicketStatus::class)]
    #[ResponseFromApiResource(TicketResource::class, Ticket::class, collection: true, paginate: 20)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = TicketStatus::tryFrom($request->string('status')->value());

        return TicketResource::collection(
            Ticket::visibleTo($request->user())
                ->with('requester:id,name', 'assignee:id,name', 'category:id,name')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString()
        );
    }

    /**
     * Create a ticket
     */
    #[ResponseFromApiResource(TicketResource::class, Ticket::class, status: 201)]
    public function store(Request $request, CreateTicket $createTicket): TicketResource
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'category_id' => ['nullable', Rule::in(Category::pluck('id'))],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
        ]);

        return new TicketResource($createTicket->handle($request->user(), $validated)->refresh());
    }

    /**
     * Show a ticket
     *
     * With its messages, oldest first.
     */
    #[ResponseFromApiResource(TicketResource::class, Ticket::class, with: ['messages'])]
    public function show(Request $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($ticket->load([
            'requester:id,name',
            'assignee:id,name',
            'category:id,name',
            'messages' => fn ($query) => $query
                ->with('author:id,name')
                ->when($request->user()->hasRole(Role::CUSTOMER), fn ($query) => $query->where('type', '!=', MessageType::InternalNote))
                ->oldest(),
        ]));
    }

    /**
     * Reply to a ticket
     *
     * Staff can send `internal: true` to add an internal note instead.
     */
    #[ResponseFromApiResource(MessageResource::class, Message::class, status: 201)]
    public function reply(Request $request, Ticket $ticket, ReplyToTicket $replyToTicket): MessageResource
    {
        Gate::authorize('view', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['boolean'],
        ]);

        $isInternal = $request->boolean('internal') && $request->user()->can('update', $ticket);

        return new MessageResource($replyToTicket->handle($ticket, $request->user(), $validated['body'], $isInternal)->load('author:id,name'));
    }
}
