<?php

namespace App\Filament;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Exists;

/**
 * Keeps relationship selects and filters inside the current tenant.
 *
 * Filament scopes a resource's own table and records to the tenant, but not
 * the options of a relationship select or filter, which would otherwise list
 * every tenant's records. Use both helpers on every relationship select:
 * query() limits what's offered, and exists() rejects another tenant's ID
 * when it's submitted directly.
 */
class TenantScope
{
    /**
     * For ->relationship(..., modifyQueryUsing: TenantScope::query()).
     */
    public static function query(): Closure
    {
        return fn (Builder $query): Builder => $query->where(
            $query->qualifyColumn('tenant_id'),
            Filament::getTenant()?->getKey(),
        );
    }

    /**
     * For ->rule(TenantScope::exists('servers')).
     */
    public static function exists(string $table): Exists
    {
        return (new Exists($table, 'id'))
            ->where('tenant_id', Filament::getTenant()?->getKey())
            ->whereNull('deleted_at');
    }
}
