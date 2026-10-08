<?php

namespace Tests\Feature;

use App\Exceptions\MissingOrganizationException;
use App\Models\Category;
use App\Models\KbArticle;
use App\Models\Message;
use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://ticketing.test']);

        Route::middleware(['web', 'organization'])->get('/tenancy-probe', fn () => Organization::current()->slug);
    }

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function tenantModels(): array
    {
        return [
            'users' => [User::class],
            'categories' => [Category::class],
            'sla policies' => [SlaPolicy::class],
            'tickets' => [Ticket::class],
            'messages' => [Message::class],
            'kb articles' => [KbArticle::class],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('tenantModels')]
    public function test_queries_only_return_the_current_organizations_rows(string $model): void
    {
        $acme = Organization::factory()->create()->makeCurrent();
        $ownRecord = $model::factory()->create();

        Organization::factory()->create()->makeCurrent();
        $otherRecord = $model::factory()->create();

        $acme->makeCurrent();

        $this->assertNotNull($model::find($ownRecord->id));
        $this->assertNull($model::find($otherRecord->id));
        $this->assertSame([$acme->id], $model::query()->distinct()->pluck('organization_id')->all());
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('tenantModels')]
    public function test_queries_fail_closed_without_a_current_organization(string $model): void
    {
        $this->expectException(MissingOrganizationException::class);

        $model::query()->count();
    }

    public function test_creating_a_record_fills_the_current_organization(): void
    {
        $organization = Organization::factory()->create()->makeCurrent();

        $category = Category::create(['name' => 'Billing', 'slug' => 'billing']);

        $this->assertSame($organization->id, $category->organization_id);
    }

    public function test_creating_a_record_without_a_current_organization_fails(): void
    {
        $this->expectException(MissingOrganizationException::class);

        Category::create(['name' => 'Billing', 'slug' => 'billing']);
    }

    public function test_middleware_resolves_the_organization_from_the_subdomain(): void
    {
        Organization::factory()->create(['slug' => 'acme']);

        $this->get('http://acme.ticketing.test/tenancy-probe')->assertOk()->assertContent('acme');
    }

    public function test_middleware_rejects_unknown_missing_and_nested_subdomains(): void
    {
        Organization::factory()->create(['slug' => 'acme']);

        $this->get('http://globex.ticketing.test/tenancy-probe')->assertNotFound();
        $this->get('http://ticketing.test/tenancy-probe')->assertNotFound();
        $this->get('http://www.acme.ticketing.test/tenancy-probe')->assertNotFound();
    }

    public function test_middleware_lets_a_user_into_their_own_organization(): void
    {
        $acme = Organization::factory()->create(['slug' => 'acme'])->makeCurrent();
        $user = User::factory()->create();

        $this->actingAs($user)->get('http://acme.ticketing.test/tenancy-probe')->assertOk();
    }

    public function test_middleware_forbids_a_user_from_another_organization(): void
    {
        Organization::factory()->create(['slug' => 'acme']);
        Organization::factory()->create(['slug' => 'globex'])->makeCurrent();
        $globexUser = User::factory()->create();

        $this->actingAs($globexUser)->get('http://acme.ticketing.test/tenancy-probe')->assertForbidden();
    }

    public function test_exists_rule_rejects_ids_from_another_organization(): void
    {
        Organization::factory()->create()->makeCurrent();
        $otherCategory = Category::factory()->create();

        Organization::factory()->create()->makeCurrent();
        $ownCategory = Category::factory()->create();

        $rules = ['category_id' => [new ExistsInCurrentOrganization(Category::class)]];

        $this->assertTrue(Validator::make(['category_id' => $ownCategory->id], $rules)->passes());
        $this->assertTrue(Validator::make(['category_id' => $otherCategory->id], $rules)->fails());
        $this->assertTrue(Validator::make(['category_id' => 'abc'], $rules)->fails());
    }
}
