import { Form, Head, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, reply, update } from '@/routes/tickets';

type Ticket = {
    reference: string;
    subject: string;
    description: string;
    status: string;
    priority: string;
    created_at: string;
    requester: { name: string } | null;
    assignee: { name: string } | null;
    category: { name: string } | null;
};

type Message = {
    id: number;
    type: 'reply' | 'internal_note' | 'system';
    body: string;
    created_at: string;
    author: { name: string } | null;
};

type Props = {
    ticket: Ticket;
    messages: Message[];
    canUpdate: boolean;
    statuses: string[];
};

export default function TicketsShow({
    ticket,
    messages,
    canUpdate,
    statuses,
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
                            <select
                                aria-label="Status"
                                value={ticket.status}
                                onChange={(event) =>
                                    router.patch(
                                        update.url(ticket.reference),
                                        { status: event.target.value },
                                        { preserveScroll: true },
                                    )
                                }
                                className="rounded-md border bg-background px-2 py-0.5 text-xs"
                            >
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status.replace('_', ' ')}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <Badge variant="secondary">
                                {ticket.status.replace('_', ' ')}
                            </Badge>
                        )}
                        <Badge variant="outline">{ticket.priority}</Badge>
                        <span>Requester: {ticket.requester?.name}</span>
                        <span>Assignee: {ticket.assignee?.name ?? '—'}</span>
                        <span>Category: {ticket.category?.name ?? '—'}</span>
                        <span>
                            Created:{' '}
                            {new Date(ticket.created_at).toLocaleString()}
                        </span>
                    </div>
                    <p className="mt-4 whitespace-pre-line">
                        {ticket.description}
                    </p>
                </div>

                <div className="flex flex-col gap-3">
                    <h2 className="font-semibold">Messages</h2>
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
                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
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

TicketsShow.layout = {
    breadcrumbs: [{ title: 'Tickets', href: index() }],
};
