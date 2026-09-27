<?php

namespace App\Services;

use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * MEMBERSHIP lists — the ones clan_id is for: the people list, generation filters,
 * subclan lists, member counts, the start-person dropdown, the clan-line parent picker.
 * These use the clan-scoped Person query (or name the clan explicitly).
 */
class Membership
{
    public function __construct(private Family $family) {}

    /** Members of one clan. Scoped by ClanScope when it's the current clan; explicit otherwise. */
    public function members(int $clanId): Collection
    {
        return Person::query()->where('people.clan_id', $clanId)->get();
    }

    /** Person ids that appear in any marriage, for married-in / unplaced flags in bulk. */
    public function marriedIds(Collection $people): array
    {
        $ids = $people->pluck('id')->all();
        if (! $ids) {
            return [];
        }
        $rows = Marriage::whereIn('husband_id', $ids)->orWhereIn('wife_id', $ids)->get(['husband_id', 'wife_id']);

        return array_fill_keys(array_filter(array_merge($rows->pluck('husband_id')->all(), $rows->pluck('wife_id')->all())), true);
    }

    public function unplacedCount(Clan $clan): int
    {
        $founders = array_filter([$clan->founder_id, $clan->founder_spouse_id]);

        return Person::unscoped()->where('clan_id', $clan->id)
            ->whereNull('father_id')->whereNull('mother_id')
            ->when($founders, fn ($q) => $q->whereNotIn('id', $founders))
            ->whereNotExists(fn ($q) => $q->from('marriages')
                ->where(fn ($w) => $w->whereColumn('marriages.husband_id', 'people.id')->orWhereColumn('marriages.wife_id', 'people.id')))
            ->count();
    }

    /**
     * "Generation, then family order": depth-first tree order from the founders, spouses
     * right after the person they married, siblings in sibling_order. Never by date.
     * Returns [personId => position].
     */
    public function familyOrder(Clan $clan): array
    {
        $order = [];
        $seen = [];
        $i = 0;
        $members = Person::unscoped()->where('clan_id', $clan->id)->get();
        $byClanParent = $members->groupBy(fn (Person $p) => $p->clanParentId() ?? 0);
        $marriages = Marriage::whereIn('husband_id', $members->pluck('id'))->orWhereIn('wife_id', $members->pluck('id'))
            ->inMarriageOrder()->get();

        $spouseIds = function (int $id) use ($marriages) {
            return $marriages->filter(fn ($m) => $m->husband_id === $id || $m->wife_id === $id)
                ->map(fn ($m) => $m->spouseIdOf($id))->filter()->values()->all();
        };

        $walk = function (?int $id) use (&$walk, &$order, &$seen, &$i, $byClanParent, $spouseIds) {
            if (! $id || isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $order[$id] = $i++;
            $spouses = $spouseIds($id);
            foreach ($spouses as $s) {
                if (! isset($order[$s])) {
                    $order[$s] = $i++;
                }
            }
            $kids = $this->family->orderChildren(collect($byClanParent[$id] ?? []), $id, $spouses);
            foreach ($kids as $k) {
                $walk($k->id);
            }
        };
        $walk($clan->founder_id);
        $walk($clan->founder_spouse_id);

        return $order;
    }

    /** Generation (unnumbered last), then family order, then name. */
    public function sortByFamily(Collection $people, array $familyOrder): Collection
    {
        return $people->sort(function (Person $a, Person $b) use ($familyOrder) {
            return [$a->generation ?? 999, $familyOrder[$a->id] ?? 1e9, $a->fullName()]
                <=> [$b->generation ?? 999, $familyOrder[$b->id] ?? 1e9, $b->fullName()];
        })->values();
    }

    /** Marked subclan heads who are numbered, in family order. */
    public function subclanHeads(Clan $clan): Collection
    {
        $heads = Person::query()->where('people.clan_id', $clan->id)
            ->where('is_subclan_head', true)->whereNotNull('generation')->get();

        return $this->sortByFamily($heads, $this->familyOrder($clan));
    }
}
