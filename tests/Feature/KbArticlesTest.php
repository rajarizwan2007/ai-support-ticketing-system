<?php

namespace Tests\Feature;

use App\Enums\KbArticleStatus;
use App\Models\KbArticle;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KbArticlesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inOrganization();
        $this->agent = User::factory()->agent()->create();
    }

    public function test_staff_list_and_search_articles(): void
    {
        KbArticle::factory()->create(['title' => 'Reset your password']);
        KbArticle::factory()->create(['title' => 'Update billing details']);

        $this->actingAs($this->agent)
            ->get(route('kb.index', ['search' => 'password']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('kb/index')
                ->where('auth.isStaff', true)
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'Reset your password'));
    }

    public function test_staff_create_an_article_with_a_unique_slug(): void
    {
        KbArticle::factory()->create(['slug' => 'reset-your-password']);

        $this->actingAs($this->agent)
            ->post(route('kb.store'), ['title' => 'Reset your password', 'body' => 'Click "Forgot password".', 'status' => 'draft'])
            ->assertRedirect();

        $article = KbArticle::latest('id')->first();
        $this->assertSame('reset-your-password-2', $article->slug);
        $this->assertTrue($article->author->is($this->agent));
        $this->assertNull($article->published_at);
    }

    public function test_publishing_stamps_the_date_once_and_draft_clears_it(): void
    {
        $article = KbArticle::factory()->create();
        $this->actingAs($this->agent);

        $this->put(route('kb.update', $article), ['title' => 'New title', 'body' => 'Body', 'status' => 'published']);
        $publishedAt = $article->fresh()->published_at;
        $this->assertNotNull($publishedAt);
        $this->assertSame('New title', $article->fresh()->title);

        $this->travel(1)->day();
        $this->put(route('kb.update', $article), ['title' => 'New title', 'body' => 'Edited', 'status' => 'published']);
        $this->assertTrue($publishedAt->equalTo($article->fresh()->published_at));

        $this->put(route('kb.update', $article), ['title' => 'New title', 'body' => 'Edited', 'status' => 'draft']);
        $this->assertNull($article->fresh()->published_at);
        $this->assertSame(KbArticleStatus::Draft, $article->fresh()->status);
    }

    public function test_the_slug_does_not_change_when_the_title_does(): void
    {
        $article = KbArticle::factory()->create(['slug' => 'old-title']);

        $this->actingAs($this->agent)->put(route('kb.update', $article), ['title' => 'New title', 'body' => 'Body', 'status' => 'draft']);

        $this->assertSame('old-title', $article->fresh()->slug);
    }

    public function test_staff_delete_articles(): void
    {
        $article = KbArticle::factory()->create();

        $this->actingAs($this->agent)->delete(route('kb.destroy', $article))->assertRedirect(route('kb.index'));

        $this->assertModelMissing($article);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs($this->agent)
            ->post(route('kb.store'), ['title' => '', 'body' => '', 'status' => 'secret'])
            ->assertSessionHasErrors(['title', 'body', 'status']);
    }

    public function test_customers_cannot_see_or_change_the_knowledge_base(): void
    {
        $article = KbArticle::factory()->create();
        $this->actingAs(User::factory()->customer()->create());

        $this->get(route('kb.index'))->assertForbidden();
        $this->get(route('kb.create'))->assertForbidden();
        $this->post(route('kb.store'), ['title' => 'X', 'body' => 'Y', 'status' => 'draft'])->assertForbidden();
        $this->get(route('kb.edit', $article))->assertForbidden();
        $this->put(route('kb.update', $article), ['title' => 'X', 'body' => 'Y', 'status' => 'draft'])->assertForbidden();
        $this->delete(route('kb.destroy', $article))->assertForbidden();
    }

    public function test_another_organizations_article_cannot_be_opened_or_deleted(): void
    {
        $current = Organization::current();
        $other = Organization::factory()->create()->makeCurrent();
        $article = KbArticle::factory()->create();
        $current->makeCurrent();

        $this->actingAs($this->agent);
        $this->get(route('kb.edit', $article))->assertNotFound();
        $this->delete(route('kb.destroy', $article))->assertNotFound();

        $other->makeCurrent();
        $this->assertModelExists($article);
    }
}
