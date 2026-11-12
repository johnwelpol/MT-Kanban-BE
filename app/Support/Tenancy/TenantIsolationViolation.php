<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * Thrown in local/testing when a query touches a tenant table without a tenant_id condition.
 */
class TenantIsolationViolation extends RuntimeException {}
