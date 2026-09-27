<?php

namespace App\Models\Scopes;

use App\Support\CurrentClan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * clan_id is MEMBERSHIP, not visibility (planning.md §2.1, §2.7).
 *
 * This scope is for membership lists only: the people list, generation filters, subclan
 * lists, member counts and the start-person dropdown. Descent is never scoped: a person's
 * own page, their children, the tree, the outline and print all run through
 * Person::unscoped(). A descendants query that silently inherits this scope draws a
 * smaller tree and never raises an error — that is the bug this split exists to prevent.
 */
class ClanScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $clanId = app(CurrentClan::class)->id();

        if ($clanId !== null) {
            $builder->where($model->qualifyColumn('clan_id'), $clanId);
        }
    }
}
