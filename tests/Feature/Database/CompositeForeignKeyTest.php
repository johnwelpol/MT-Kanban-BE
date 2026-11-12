<?php

namespace Tests\Feature\Database;

use App\Models\Card;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\KanbanFixtures;
use Tests\TestCase;

/**
 * Composite (tenant_id, parent_id) foreign keys stop rows pointing at another board or tenant.
 */
class CompositeForeignKeyTest extends TestCase
{
    use KanbanFixtures, RefreshDatabase;

    public function test_card_in_a_column_of_another_board_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $board = $this->boardWithCards($tenant, ['To do' => 0]);
        $otherBoard = $this->boardWithCards($tenant, ['To do' => 0]);

        $this->expectException(QueryException::class);

        Card::factory()->for($tenant)->for($board)->for($otherBoard->columns->first(), 'column')->create();
    }

    public function test_card_on_another_tenants_board_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenantBoard = $this->boardWithCards(Tenant::factory()->create(), ['To do' => 0]);

        $this->expectException(QueryException::class);

        Card::factory()->for($tenant)->for($otherTenantBoard)->for($otherTenantBoard->columns->first(), 'column')->create();
    }

    public function test_card_matching_its_board_and_tenant_is_accepted(): void
    {
        $tenant = Tenant::factory()->create();
        $board = $this->boardWithCards($tenant, ['To do' => 0]);

        $card = Card::factory()->for($tenant)->for($board)->for($board->columns->first(), 'column')->create();

        $this->assertModelExists($card);
    }
}
