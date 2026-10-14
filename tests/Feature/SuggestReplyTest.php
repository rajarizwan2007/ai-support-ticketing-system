<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SuggestReplyTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inOrganization();
        $this->agent = User::factory()->agent()->create();
        $this->ticket = Ticket::factory()->create([
            'reference' => 'TKT-1001',
            'subject' => 'Charged twice',
            'description' => 'Please refund one payment.',
            'embedding' => $this->vector([1, 0]),
        ]);
    }

    public function test_agents_get_a_draft_grounded_in_the_closest_published_articles(): void
    {
        $close = KbArticle::factory()->published()->create(['title' => 'Duplicate charges', 'embedding' => $this->vector([0.9, 0.1])]);
        $closest = KbArticle::factory()->published()->create(['title' => 'Refunds', 'embedding' => $this->vector([1, 0])]);
        KbArticle::factory()->create(['title' => 'Draft', 'embedding' => $this->vector([1, 0])]);
        KbArticle::factory()->published()->create(['title' => 'Unrelated', 'embedding' => $this->vector([0, 1])]);
        KbArticle::factory()->published()->for(Organization::factory())->create(['title' => 'Other org', 'embedding' => $this->vector([1, 0])]);
        $this->fakeAi(['reply' => 'We refund duplicates within 5 days.', 'source_ids' => [$closest->id, $close->id]]);

        $this->actingAs($this->agent)
            ->post(route('tickets.suggest-reply', 'TKT-1001'))
            ->assertRedirect()
            ->assertInertiaFlash('suggestion', [
                'body' => 'We refund duplicates within 5 days.',
                'sources' => [['id' => $closest->id, 'title' => 'Refunds'], ['id' => $close->id, 'title' => 'Duplicate charges']],
            ]);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://ai:8000/suggest-reply'
            && $request['subject'] === 'Charged twice'
            && array_column($request['articles'], 'title') === ['Refunds', 'Duplicate charges']);
    }

    public function test_a_ticket_without_an_embedding_is_drafted_without_articles(): void
    {
        $this->ticket->forceFill(['embedding' => null])->save();
        KbArticle::factory()->published()->create(['embedding' => $this->vector([1, 0])]);
        $this->fakeAi(['reply' => 'We are checking with the team.', 'source_ids' => []]);

        $this->actingAs($this->agent)
            ->post(route('tickets.suggest-reply', 'TKT-1001'))
            ->assertInertiaFlash('suggestion.sources', []);

        Http::assertSent(fn (Request $request) => $request['articles'] === []);
    }

    public function test_an_unavailable_ai_service_shows_an_error_and_no_draft(): void
    {
        Http::fake(['*/suggest-reply' => Http::response(['detail' => 'Chat model unavailable'], 503)]);

        $this->actingAs($this->agent)
            ->post(route('tickets.suggest-reply', 'TKT-1001'))
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlashMissing('suggestion');
    }

    public function test_customers_cannot_ask_for_a_draft(): void
    {
        $this->actingAs(User::factory()->customer()->create(['id' => $this->ticket->requester_id + 1000]))
            ->post(route('tickets.suggest-reply', 'TKT-1001'))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_drafts_are_limited_to_10_a_minute(): void
    {
        $this->fakeAi(['reply' => 'Hi', 'source_ids' => []]);
        $this->actingAs($this->agent);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('tickets.suggest-reply', 'TKT-1001'))->assertRedirect();
        }

        $this->post(route('tickets.suggest-reply', 'TKT-1001'))->assertTooManyRequests();
    }

    public function test_replies_sent_from_a_draft_are_marked_but_only_for_staff(): void
    {
        $this->actingAs($this->agent)->post(route('tickets.reply', 'TKT-1001'), ['body' => 'Edited draft', 'ai_generated' => true]);
        $this->actingAs($this->ticket->requester)->post(route('tickets.reply', 'TKT-1001'), ['body' => 'Thanks', 'ai_generated' => true]);

        $this->assertTrue($this->ticket->messages()->where('body', 'Edited draft')->sole()->is_ai_generated);
        $this->assertFalse($this->ticket->messages()->where('body', 'Thanks')->sole()->is_ai_generated);
    }

    /**
     * @param  array{reply: string, source_ids: list<int>}  $answer
     */
    private function fakeAi(array $answer): void
    {
        Http::fake(['*/suggest-reply' => Http::response([...$answer, 'model' => 'gemma3:1b', 'prompt' => 'suggest_reply_v1'])]);
    }
}
