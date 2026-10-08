import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/tickets';

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

type Props = { ticket: Ticket; messages: Message[] };

export default function TicketsShow({ ticket, messages }: Props) {
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
                        <Badge variant="secondary">
                            {ticket.status.replace('_', ' ')}
                        </Badge>
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
            </div>
        </>
    );
}

TicketsShow.layout = {
    breadcrumbs: [{ title: 'Tickets', href: index() }],
};
