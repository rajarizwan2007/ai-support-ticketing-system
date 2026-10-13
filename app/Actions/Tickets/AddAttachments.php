<?php

namespace App\Actions\Tickets;

use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class AddAttachments
{
    /**
     * Store uploaded files on the private disk, in a folder per organization,
     * and record them on the ticket (and on the message, for replies).
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(Ticket $ticket, User $user, array $files, ?Message $message = null): void
    {
        foreach ($files as $file) {
            $ticket->attachments()->create([
                'message_id' => $message?->id,
                'user_id' => $user->id,
                'path' => $file->store('attachments/'.$ticket->organization_id),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
    }
}
