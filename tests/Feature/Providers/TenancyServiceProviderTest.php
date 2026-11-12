<?php

namespace Tests\Feature\Providers;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenancyServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_tenant_does_not_create_a_database(): void
    {
        $databaseCountBefore = count(DB::select('SHOW DATABASES'));

        Tenant::factory()->create();

        $this->assertCount($databaseCountBefore, DB::select('SHOW DATABASES'));
    }
}
