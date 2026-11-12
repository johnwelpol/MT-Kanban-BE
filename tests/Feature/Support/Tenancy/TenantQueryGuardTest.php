<?php

namespace Tests\Feature\Support\Tenancy;

use App\Models\Board;
use App\Models\Card;
use App\Models\Tenant;
use App\Support\Tenancy\TenantIsolationViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\KanbanFixtures;
use Tests\TestCase;

class TenantQueryGuardTest extends TestCase
{
    use KanbanFixtures, RefreshDatabase;

    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->card = $this->boardWithCards($tenant, ['To do' => 1])->columns->first()->cards->first();

        tenancy()->initialize($tenant);
    }

    public function test_unscoped_query_builder_read_throws(): void
    {
        $this->expectException(TenantIsolationViolation::class);

        DB::table('cards')->get();
    }

    public function test_unscoped_query_builder_update_throws(): void
    {
        $this->expectException(TenantIsolationViolation::class);

        DB::table('cards')->update(['title' => 'Leaked']);
    }

    public function test_scoped_eloquent_queries_and_instance_writes_are_allowed(): void
    {
        $this->assertCount(1, Card::all());
        $this->assertTrue(Card::find($this->card->id)->is($this->card));
        $this->assertSame(1, Board::withCount('cards')->sole()->cards_count);

        $this->card->update(['title' => 'Renamed']);
        $this->assertSame('Renamed', Card::find($this->card->id)->title);

        $this->card->delete();
        $this->assertNull(Card::find($this->card->id));
    }

    /**
     * fresh()/refresh() reload without global scopes, so tenant code must re-query with find() instead.
     * The same applies to assertModelExists()/assertModelMissing() in tests.
     */
    public function test_fresh_bypasses_the_tenant_scope_and_throws(): void
    {
        $this->expectException(TenantIsolationViolation::class);

        $this->card->fresh();
    }

    public function test_queries_outside_tenancy_are_not_checked(): void
    {
        tenancy()->end();

        $this->assertSame(1, DB::table('cards')->count());
    }
}
