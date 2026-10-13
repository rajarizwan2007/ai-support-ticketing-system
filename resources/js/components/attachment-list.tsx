import { Paperclip } from 'lucide-react';
import { show } from '@/routes/tickets/attachments';
import type { Attachment } from '@/types';

/**
 * Download links for a ticket's or a message's files.
 */
export default function AttachmentList({
    ticketReference,
    attachments,
}: {
    ticketReference: string;
    attachments: Attachment[];
}) {
    if (attachments.length === 0) {
        return null;
    }

    return (
        <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
            {attachments.map((attachment) => (
                <li key={attachment.id}>
                    <a
                        href={show.url([ticketReference, attachment.id])}
                        className="inline-flex items-center gap-1 underline underline-offset-4"
                    >
                        <Paperclip className="size-3.5" />
                        {attachment.original_name}
                    </a>{' '}
                    <span className="text-muted-foreground">
                        ({Math.ceil(attachment.size / 1024)} KB)
                    </span>
                </li>
            ))}
        </ul>
    );
}
