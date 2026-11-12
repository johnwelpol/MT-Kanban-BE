<?php

namespace Tests\Feature\Actions\Boards;

use App\Actions\Boards\CreateBoard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CreateBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_board_in_current_tenant_with_contiguous_column_positions(): void
    {
        $tenant = Tenant::factory()->create();
        $creator = User::factory()->create();
        tenancy()->initialize($tenant);

        $board = app(CreateBoard::class)->handle($creator, 'Roadmap', 'Q3 plans', ['Backlog', 'Doing']);

        $this->assertSame($tenant->id, $board->tenant_id);
        $this->assertSame($creator->id, $board->created_by);
        $this->assertSame(['Backlog', 'Doing'], $board->columns->pluck('name')->all());
        $this->assertSame([0, 1], $board->columns->pluck('position')->all());
        $this->assertSame([$tenant->id], $board->columns->pluck('tenant_id')->unique()->values()->all());
    }

    public function test_refuses_to_run_without_a_tenant(): void
    {
        $this->expectException(LogicException::class);

        app(CreateBoard::class)->handle(User::factory()->create(), 'Roadmap');
    }
}
