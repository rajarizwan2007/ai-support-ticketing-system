import type { User } from '@/types/auth';

/**
 * Shapes of the ticket models as the server sends them (see app/Models).
 * Relations are only present when the controller loads them.
 */
export type UserSummary = Pick<User, 'id' | 'name'>;

export type TicketStatus =
    | 'open'
    | 'pending'
    | 'on_hold'
    | 'resolved'
    | 'closed';

export type Priority = 'low' | 'medium' | 'high' | 'urgent';

export type MessageType = 'reply' | 'internal_note' | 'system';

export type Category = {
    id: number;
    name: string;
};

export type Ticket = {
    id: number;
    reference: string;
    subject: string;
    description: string;
    status: TicketStatus;
    priority: Priority;
    requester_id: number;
    assignee_id: number | null;
    category_id: number | null;
    created_at: string;
    updated_at: string;
    first_response_due_at: string | null;
    resolution_due_at: string | null;
    first_responded_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    requester?: UserSummary | null;
    assignee?: UserSummary | null;
    category?: Category | null;
};

export type Message = {
    id: number;
    ticket_id: number;
    user_id: number | null;
    type: MessageType;
    body: string;
    created_at: string;
    author?: UserSummary | null;
};
