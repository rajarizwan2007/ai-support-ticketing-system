import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AttachmentInput from '@/components/attachment-input';
import AttachmentList from '@/components/attachment-list';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, reply, show, update } from '@/routes/tickets';
import type {
    Attachment,
    Message,
    Priority,
    Ticket,
    TicketStatus,
    UserSummary,
} from '@/types';

type Props = {
    ticket: Ticket;
    attachments: Attachment[];
    messages: Message[];
    canUpdate: boolean;
    statuses: TicketStatus[];
    priorities: Priority[];
    staff: UserSummary[];
    similarTickets: Pick<Ticket, 'id' | 'reference' | 'subject' | 'status'>[];
};

export default function TicketsShow({
    ticket,
    attachments,
    messages,
    canUpdate,
    statuses,
    priorities,
    staff,
    similarTickets,
}: Props) {
    return (
        <>
            <Head title={ticket.reference} />
            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {ticket.reference}
                    </p>
                    <h1 className="text-xl font-semibold">{ticket.subject}</h1>
                    <div className="mt-2 flex flex-wrap gap-2 text-sm">
                        {canUpdate ? (
                            <>
                                <FieldSelect
                                    ticket={ticket}
                                    field="status"
                                    value={ticket.status}
                                    options={statuses.map((status) => [
                                        status,
                                        status.replace('_', ' '),
                                    ])}
                                />
                                <FieldSelect
                                    ticket={ticket}
                                    field="priority"
                                    value={ticket.priority}
                                    options={priorities.map((priority) => [
                                        priority,
                                        priority,
                                    ])}
                                />
                                <FieldSelect
                                    ticket={ticket}
                                    field="assignee_id"
                                    value={String(ticket.assignee_id ?? '')}
                                    options={[
                                        ['', 'Unassigned'],
                                        ...staff.map(
                                            (user): [string, string] => [
                                                String(user.id),
                                                user.name,
                                            ],
                                        ),
                                    ]}
                                />
                            </>
                        ) : (
                            <>
                                <Badge variant="secondary">
                                    {ticket.status.replace('_', ' ')}
                                </Badge>
                                <Badge variant="outline">
                                    {ticket.priority}
                                </Badge>
                                <span>
                                    Assignee: {ticket.assignee?.name ?? '—'}
                                </span>
                            </>
                        )}
                        <span>Requester: {ticket.requester?.name}</span>
                        <span>Category: {ticket.category?.name ?? '—'}</span>
                        <span>
                            Created:{' '}
                            {new Date(ticket.created_at).toLocaleString()}
                        </span>
                    </div>
                    <div className="mt-1 flex flex-wrap gap-x-4 text-sm text-muted-foreground">
                        {(
                            [
                                [
                                    'First response due',
                                    ticket.first_response_due_at,
                                ],
                                ['First response', ticket.first_responded_at],
                                ['Resolution due', ticket.resolution_due_at],
                                ['Resolved', ticket.resolved_at],
                                ['Closed', ticket.closed_at],
                            ] as const
                        ).map(
                            ([label, date]) =>
                                date && (
                                    <span key={label}>
                                        {label}:{' '}
                                        {new Date(date).toLocaleString()}
                                    </span>
                                ),
                        )}
                    </div>
                    <p className="mt-4 whitespace-pre-line">
                        {ticket.description}
                    </p>
                    <AttachmentList
                        ticketReference={ticket.reference}
                        attachments={attachments}
                    />
                </div>

                {similarTickets.length > 0 && (
                    <div className="flex flex-col gap-1 text-sm">
                        <h2 className="font-semibold">Similar tickets</h2>
                        {similarTickets.map((similar) => (
                            <Link
                                key={similar.id}
                                href={show(similar.reference)}
                                className="flex gap-2 hover:underline"
                            >
                                <span className="text-muted-foreground">
                                    {similar.reference}
                                </span>
                                <span>{similar.subject}</span>
                                <Badge variant="secondary">
                                    {similar.status.replace('_', ' ')}
                                </Badge>
                            </Link>
                        ))}
                    </div>
                )}

                <div className="flex flex-col gap-3">
                    <h2 className="font-semibold">Messages</h2>
                    {messages.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No messages yet.
                        </p>
                    )}
                    {messages.map((message) => (
                        <div
                            key={message.id}
                            className={`rounded-lg border p-3 text-sm ${message.type === 'internal_note' ? 'bg-yellow-50 dark:bg-yellow-950' : ''}`}
                        >
                            <p className="mb-1 text-muted-foreground">
                                {message.author?.name ?? 'System'}
                                {message.type === 'internal_note' &&
                                    ' · internal note'}
                                {' · '}
                                {new Date(message.created_at).toLocaleString()}
                            </p>
                            <p className="whitespace-pre-line">
                                {message.body}
                            </p>
                            <AttachmentList
                                ticketReference={ticket.reference}
                                attachments={message.attachments ?? []}
                            />
                        </div>
                    ))}
                </div>

                <Form
                    {...reply.form(ticket.reference)}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <textarea
                                name="body"
                                rows={4}
                                required
                                aria-label="Reply"
                                placeholder="Write a reply…"
                                className="rounded-md border bg-background p-2 text-sm"
                            />
                            <InputError message={errors.body} />
                            <AttachmentInput errors={errors} />
                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Send
                                </Button>
                                {canUpdate && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="internal"
                                            value="1"
                                        />
                                        Internal note
                                    </label>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

/**
 * A dropdown that saves one ticket field as soon as it changes.
 */
function FieldSelect({
    ticket,
    field,
    value,
    options,
}: {
    ticket: Ticket;
    field: 'status' | 'priority' | 'assignee_id';
    value: string;
    options: [string, string][];
}) {
    const [saving, setSaving] = useState(false);

    return (
        <select
            aria-label={field.replace('_id', '')}
            value={value}
            disabled={saving}
            onChange={(event) =>
                router.patch(
                    update.url(ticket.reference),
                    { [field]: event.target.value || null },
                    {
                        preserveScroll: true,
                        onStart: () => setSaving(true),
                        onFinish: () => setSaving(false),
                    },
                )
            }
            className="rounded-md border bg-background px-2 py-0.5 text-xs disabled:opacity-50"
        >
            {options.map(([optionValue, label]) => (
                <option key={optionValue} value={optionValue}>
                    {label}
                </option>
            ))}
        </select>
    );
}

TicketsShow.layout = {
    breadcrumbs: [{ title: 'Tickets', href: index() }],
};
