import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/tickets';
import type { Ticket } from '@/types';

type Props = {
    tickets: {
        data: Ticket[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function TicketsIndex({ tickets }: Props) {
    return (
        <>
            <Head title="Tickets" />
            <div className="p-4">
                <Button asChild className="mb-4">
                    <Link href={create()}>New ticket</Link>
                </Button>
                <table className="w-full text-left text-sm">
                    <thead className="border-b text-muted-foreground">
                        <tr>
                            <th className="p-2">Reference</th>
                            <th className="p-2">Subject</th>
                            <th className="p-2">Status</th>
                            <th className="p-2">Priority</th>
                            <th className="p-2">Requester</th>
                            <th className="p-2">Assignee</th>
                            <th className="p-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        {tickets.data.map((ticket) => (
                            <tr
                                key={ticket.id}
                                className="border-b hover:bg-muted/50"
                            >
                                <td className="p-2 font-medium">
                                    <Link href={show(ticket.reference)}>
                                        {ticket.reference}
                                    </Link>
                                </td>
                                <td className="p-2">
                                    <Link href={show(ticket.reference)}>
                                        {ticket.subject}
                                    </Link>
                                </td>
                                <td className="p-2">
                                    <Badge variant="secondary">
                                        {ticket.status.replace('_', ' ')}
                                    </Badge>
                                </td>
                                <td className="p-2">
                                    <Badge variant="outline">
                                        {ticket.priority}
                                    </Badge>
                                </td>
                                <td className="p-2">
                                    {ticket.requester?.name}
                                </td>
                                <td className="p-2">
                                    {ticket.assignee?.name ?? '—'}
                                </td>
                                <td className="p-2">
                                    {new Date(
                                        ticket.created_at,
                                    ).toLocaleDateString()}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="mt-4 flex items-center gap-4 text-sm">
                    {tickets.prev_page_url && (
                        <Link href={tickets.prev_page_url}>Previous</Link>
                    )}
                    <span className="text-muted-foreground">
                        Page {tickets.current_page} of {tickets.last_page}
                    </span>
                    {tickets.next_page_url && (
                        <Link href={tickets.next_page_url}>Next</Link>
                    )}
                </div>
            </div>
        </>
    );
}

TicketsIndex.layout = {
    breadcrumbs: [{ title: 'Tickets', href: index() }],
};
