<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->inOrganization();
        $this->customer = User::factory()->customer()->create();
        $this->ticket = Ticket::factory()->create(['requester_id' => $this->customer->id]);
    }

    public function test_a_new_ticket_can_include_attachments(): void
    {
        $this->actingAs($this->customer)->post(route('tickets.store'), [
            'subject' => 'Invoice is wrong',
            'description' => 'See the attached invoice.',
            'attachments' => [UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $attachment = Attachment::sole();
        $this->assertNull($attachment->message_id);
        $this->assertSame('invoice.pdf', $attachment->original_name);
        $this->assertSame($this->customer->id, $attachment->user_id);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_a_reply_can_include_attachments(): void
    {
        $this->actingAs($this->customer)->post(route('tickets.reply', $this->ticket->reference), [
            'body' => 'Here is a screenshot.',
            'attachments' => [UploadedFile::fake()->create('screenshot.png', 50, 'image/png')],
        ])->assertRedirect();

        $attachment = Attachment::sole();
        $this->assertSame($this->ticket->messages()->sole()->id, $attachment->message_id);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_attachments_must_be_allowed_types_and_sizes(): void
    {
        $this->actingAs($this->customer);

        $this->post(route('tickets.reply', $this->ticket->reference), [
            'body' => 'Hi',
            'attachments' => [UploadedFile::fake()->create('tool.exe', 10)],
        ])->assertSessionHasErrors('attachments.0');

        $this->post(route('tickets.reply', $this->ticket->reference), [
            'body' => 'Hi',
            'attachments' => [UploadedFile::fake()->create('big.pdf', 6 * 1024, 'application/pdf')],
        ])->assertSessionHasErrors('attachments.0');

        $this->post(route('tickets.reply', $this->ticket->reference), [
            'body' => 'Hi',
            'attachments' => array_fill(0, 4, UploadedFile::fake()->create('note.txt', 1, 'text/plain')),
        ])->assertSessionHasErrors('attachments');

        $this->assertSame(0, Attachment::count());
        $this->assertSame(0, $this->ticket->messages()->count());
    }

    public function test_the_ticket_page_lists_ticket_and_message_attachments(): void
    {
        $message = Message::factory()->for($this->ticket)->create();
        Attachment::factory()->for($this->ticket)->create();
        Attachment::factory()->for($this->ticket)->create(['message_id' => $message->id]);

        $this->actingAs($this->customer)
            ->get(route('tickets.show', $this->ticket->reference))
            ->assertInertia(fn (Assert $page) => $page
                ->has('attachments', 1)
                ->has('messages.0.attachments', 1)
                ->missing('attachments.0.path'));
    }

    public function test_the_requester_and_staff_can_download_an_attachment(): void
    {
        $attachment = $this->storedAttachment();

        $this->actingAs($this->customer)
            ->get(route('tickets.attachments.show', [$this->ticket->reference, $attachment]))
            ->assertOk()
            ->assertDownload('invoice.pdf');

        $this->actingAs(User::factory()->agent()->create())
            ->get(route('tickets.attachments.show', [$this->ticket->reference, $attachment]))
            ->assertOk();
    }

    public function test_customers_cannot_download_files_from_someone_elses_ticket(): void
    {
        $attachment = $this->storedAttachment();

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('tickets.attachments.show', [$this->ticket->reference, $attachment]))
            ->assertForbidden();
    }

    public function test_customers_cannot_download_files_from_internal_notes(): void
    {
        $note = Message::factory()->for($this->ticket)->internalNote()->create();
        $attachment = $this->storedAttachment(['message_id' => $note->id]);

        $this->actingAs($this->customer)
            ->get(route('tickets.attachments.show', [$this->ticket->reference, $attachment]))
            ->assertNotFound();

        $this->actingAs(User::factory()->agent()->create())
            ->get(route('tickets.attachments.show', [$this->ticket->reference, $attachment]))
            ->assertOk();
    }

    public function test_an_attachment_must_belong_to_the_ticket_in_the_url(): void
    {
        $otherTicketAttachment = $this->storedAttachment(['ticket_id' => Ticket::factory()->create()->id]);

        $this->actingAs($this->customer)
            ->get(route('tickets.attachments.show', [$this->ticket->reference, $otherTicketAttachment]))
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storedAttachment(array $attributes = []): Attachment
    {
        Storage::disk('local')->put('attachments/invoice.pdf', 'PDF');

        return Attachment::factory()->for($this->ticket)->create([
            'path' => 'attachments/invoice.pdf',
            'original_name' => 'invoice.pdf',
            ...$attributes,
        ]);
    }
}
