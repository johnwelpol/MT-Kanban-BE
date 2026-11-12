<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Facades\DB;

/**
 * Development safety net: while tenancy is initialized, any query on a tenant table must
 * mention tenant_id. Catches DB::table()/raw SQL that bypasses the BelongsToTenant scope.
 */
final class TenantQueryGuard
{
    public const TABLES = ['boards', 'board_columns', 'cards', 'card_comments', 'invitations'];

    public static function register(): void
    {
        DB::connection()->beforeExecuting(function (string $query): void {
            if (! tenancy()->initialized) {
                return;
            }

            $sql = strtolower(trim($query));

            foreach (self::TABLES as $table) {
                if (! str_contains($sql, "`{$table}`")) {
                    continue;
                }

                if (str_starts_with($sql, 'insert') || str_contains($sql, 'tenant_id')) {
                    return;
                }

                // Single-row update/delete of a model instance that was loaded through the scope.
                if (preg_match('/^(update|delete)\b.*\bwhere `id` = \?( limit 1)?$/s', $sql)) {
                    return;
                }

                throw new TenantIsolationViolation("Unscoped query on tenant table `{$table}`: {$query}");
            }
        });
    }
}
