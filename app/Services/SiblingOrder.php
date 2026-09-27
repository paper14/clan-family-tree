<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * planning.md §2.6: sibling_order is the ONLY sequence authority. Dense 1…n within a
 * (father_id, mother_id) pair, whatever clan each child is a member of. Never dates.
 */
class SiblingOrder
{
    /** The set, first child to last. Parents are compared NULL-safely (<=>). */
    public function members(?int $fatherId, ?int $motherId): Collection
    {
        if ($fatherId === null && $motherId === null) {
            return collect();
        }

        return Person::unscoped()->siblingSet($fatherId, $motherId)->inSiblingOrder()->get();
    }

    /** The next free position: new people go after the children already recorded. */
    public function next(?int $fatherId, ?int $motherId): int
    {
        if ($fatherId === null && $motherId === null) {
            return 1;
        }

        return Person::unscoped()->siblingSet($fatherId, $motherId)->count() + 1;
    }

    /**
     * Put $p at $position within its set (null = after the last child) and renumber
     * the whole set 1…n. $p must already carry its parents.
     */
    public function place(Person $p, ?int $position = null): void
    {
        if (! $p->hasParents()) {
            $this->write($p, 1);

            return;
        }
        $list = $this->members($p->father_id, $p->mother_id)->reject(fn ($x) => $x->id === $p->id)->values();
        $position = $position ? max(1, min($list->count() + 1, $position)) : $list->count() + 1;
        $list->splice($position - 1, 0, [$p]);
        $this->renumber($list);
    }

    /** Swap with a neighbour (-1 earlier, +1 later) and renumber. */
    public function move(Person $p, int $dir): bool
    {
        $list = $this->members($p->father_id, $p->mother_id)->values();
        $i = $list->search(fn ($x) => $x->id === $p->id);
        $j = $i === false ? -1 : $i + $dir;
        if ($i === false || $j < 0 || $j >= $list->count()) {
            return false;
        }
        $all = $list->all();
        [$all[$i], $all[$j]] = [$all[$j], $all[$i]];
        $this->renumber(collect($all));
        $p->sibling_order = $j + 1;

        return true;
    }

    /** Close any gap, e.g. after someone left the set. */
    public function normalize(?int $fatherId, ?int $motherId): void
    {
        $this->renumber($this->members($fatherId, $motherId));
    }

    private function renumber(Collection $list): void
    {
        foreach ($list->values() as $i => $x) {
            $this->write($x, $i + 1);
        }
    }

    private function write(Person $x, int $order): void
    {
        if ($x->exists && (int) $x->getOriginal('sibling_order') !== $order) {
            Person::unscoped()->whereKey($x->id)->update(['sibling_order' => $order]);
        }
        $x->sibling_order = $order;
        $x->syncOriginalAttribute('sibling_order');
    }
}
