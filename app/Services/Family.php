<?php

namespace App\Services;

use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Facts about descent. EVERYTHING here is unscoped: a person's parents, spouses and
 * children are facts about that person, whatever clan each of them is a member of
 * (planning.md §2.7). Never add the clan scope to these queries.
 */
class Family
{
    /** Marriages in marriage order: date ascending, NULLs last, then id. */
    public function marriagesOf(int $personId): Collection
    {
        return Marriage::of($personId)->inMarriageOrder()->get();
    }

    /** Spouses in marriage order (a spouse "not recorded" is skipped). */
    public function spousesOf(int $personId): Collection
    {
        $marriages = $this->marriagesOf($personId);
        $ids = $marriages->map(fn ($m) => $m->spouseIdOf($personId))->filter()->values();
        $people = Person::unscoped()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => $people[$id] ?? null)->filter()->values();
    }

    /**
     * A person's children, whichever clan each child is a member of. A child whose
     * link to this person is hidden (hide_second_parent) is left out unless $showHidden.
     * Ordered: grouped by other parent in marriage order (no other parent last), then
     * sibling_order. Never by date.
     */
    public function childrenOf(int $personId, bool $showHidden = false): Collection
    {
        $kids = Person::unscoped()
            ->where(fn ($w) => $w->where('father_id', $personId)->orWhere('mother_id', $personId))
            ->get()
            ->filter(fn (Person $k) => $showHidden || ! ($k->hide_second_parent && $k->otherParentId() === $personId));

        return $this->orderChildren($kids, $personId);
    }

    /** One ordering rule for every children listing (detail page, tree, outline, print). */
    public function orderChildren(Collection $kids, int $parentId, ?array $spouseOrder = null): Collection
    {
        $spouseOrder ??= $this->marriagesOf($parentId)->map(fn ($m) => $m->spouseIdOf($parentId))->values()->all();
        $rank = function (Person $c) use ($parentId, $spouseOrder) {
            $other = $c->father_id === $parentId ? $c->mother_id : $c->father_id;
            if ($other === null) {
                return 1_000_000;
            }
            $i = array_search($other, $spouseOrder, true);

            return $i !== false ? $i : 500_000 + $other;
        };

        return $kids->sort(fn (Person $a, Person $b) => [$rank($a), $a->sibling_order, $a->id] <=> [$rank($b), $b->sibling_order, $b->id])->values();
    }

    /** Children whose CLAN-LINE parent is this person. */
    public function clanChildren(int $personId): Collection
    {
        $kids = Person::unscoped()->whereRaw(Generations::clanLineSql('people').' = ?', [$personId])->get();

        return $this->orderChildren($kids, $personId);
    }

    /** The clan line from the top of this person's clan down to them (breadcrumbs, placement). */
    public function lineOf(Person $p): Collection
    {
        $out = collect([$p]);
        $c = $p;
        for ($guard = 0; $guard < 60; $guard++) {
            $id = $c->clanParentId();
            $n = $id ? Person::unscoped()->find($id) : null;
            if (! $n || $n->clan_id !== $p->clan_id) {
                break;
            }
            $out->prepend($n);
            $c = $n;
        }

        return $out->values();
    }

    /** Every ancestor id of $id, walking both parent chains (one recursive query). */
    public function ancestorIds(int $id): array
    {
        $rows = DB::select(
            'WITH RECURSIVE anc (id) AS (
                SELECT ?
                UNION
                SELECT par.id FROM anc
                JOIN people c ON c.id = anc.id
                JOIN people par ON par.id = c.father_id OR par.id = c.mother_id
            )
            SELECT id FROM anc',
            [$id]
        );

        return array_values(array_filter(array_map(fn ($r) => (int) $r->id, $rows), fn ($x) => $x !== $id));
    }

    /** Would linking $parentId as a parent of $childId make the child their own ancestor? */
    public function wouldCycle(?int $childId, ?int $parentId): bool
    {
        if (! $childId || ! $parentId) {
            return false;
        }

        return $childId === $parentId || in_array($childId, $this->ancestorIds($parentId), true);
    }

    public function isFounder(Person $p, ?Clan $clan = null): bool
    {
        $clan ??= Clan::withTrashed()->find($p->clan_id);

        return (bool) $clan?->isFounder($p);
    }

    public function isMarried(int $personId): bool
    {
        return Marriage::of($personId)->exists();
    }

    /** No parents, not a founder, married → "married in". */
    public function isMarriedIn(Person $p, ?Clan $clan = null): bool
    {
        return ! $this->isFounder($p, $clan) && ! $p->hasParents() && $this->isMarried($p->id);
    }

    /** No parents, not a founder, not married → "unplaced" (typed in, no parent linked yet). */
    public function isUnplaced(Person $p, ?Clan $clan = null): bool
    {
        return ! $this->isFounder($p, $clan) && ! $p->hasParents() && ! $this->isMarried($p->id);
    }
}
