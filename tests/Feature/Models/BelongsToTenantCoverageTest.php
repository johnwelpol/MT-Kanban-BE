<?php

namespace Tests\Feature\Models;

use App\Models\LoginHandoff;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Tests\TestCase;

/**
 * A new tenant-owned model that forgets BelongsToTenant would be readable across tenants.
 */
class BelongsToTenantCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Central models that reference a tenant but are queried outside tenancy on purpose.
     */
    private const CENTRAL_MODELS = [LoginHandoff::class, Membership::class];

    public function test_every_model_with_a_tenant_id_column_uses_belongs_to_tenant(): void
    {
        $checkedModels = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! is_subclass_of($class, Model::class) || in_array($class, self::CENTRAL_MODELS, true)) {
                continue;
            }

            if (! Schema::hasColumn((new $class)->getTable(), 'tenant_id')) {
                continue;
            }

            $checkedModels[] = $class;
            $this->assertContains(BelongsToTenant::class, class_uses_recursive($class), "{$class} has a tenant_id column but does not use BelongsToTenant.");
        }

        $this->assertNotEmpty($checkedModels, 'No tenant models were found; the scan is broken.');
    }
}
