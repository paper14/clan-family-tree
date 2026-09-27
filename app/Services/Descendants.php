<?php

namespace App\Services;

use App\Enums\PhotoKind;
use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\Photo;
use App\Support\Format;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * THE descendants query (planning.md §2.7, §5; data-model.md §5; implementation-notes.md §6.1).
 * One query feeds the tree, the outline and print; the controls are its parameters.
 *
 *  - It walks father_id / mother_id DOWNWARD and never filters on clan_id. The clan scope
 *    is explicitly out of it: the CTE is raw SQL on `people`, and rows are loaded with
 *    Person::unscoped(). A walk that inherited the scope would silently draw a smaller tree.
 *  - It never walks upward. Spouses are attached to the person they married; their own
 *    parents and siblings are never drawn.
 *  - A child hidden by hide_second_parent is left out of the hidden parent's branch
 *    (unless the archive toggle asks for hidden links).
 *  - Each person is drawn once, at their minimum depth (first and shortest path).
 *  - Counts are "people in this chart": the boxes below each node at this root and depth.
 */
class Descendants
{
    /** @var array<int, Person> */
    private array $people = [];

    /** @var array<int, int[]> parent id → ordered child ids */
    private array $children = [];

    /** @var array<int, int> */
    private array $reachCount = [];

    public function __construct(private Family $family) {}

    /**
     * @param  int[]  $collapsed  person ids whose branches are collapsed (still walked, so
     *                            their counts are known, but their children aren't returned)
     */
    public function build(int $rootId, int $depth, array $collapsed = [], bool $showHidden = false): ?array
    {
        $depth = max(1, min(30, $depth));
        $ids = $this->reachable($rootId, $showHidden);
        if (! $ids) {
            return null;
        }

        $this->people = Person::unscoped()->whereIn('id', $ids)->get()->keyBy('id')->all();
        if (! isset($this->people[$rootId])) {
            return null;
        }

        // Marriages of everyone in the walk, in marriage order; spouses may be outside it.
        $marriages = Marriage::whereIn('husband_id', $ids)->orWhereIn('wife_id', $ids)->inMarriageOrder()->get();
        $spouseIds = $marriages->flatMap(fn ($m) => [$m->husband_id, $m->wife_id])->filter()->unique()
            ->reject(fn ($id) => isset($this->people[$id]))->values()->all();
        $spouses = $spouseIds ? Person::unscoped()->whereIn('id', $spouseIds)->get()->keyBy('id')->all() : [];
        $everyone = $this->people + $spouses;

        $marriagesOf = [];
        foreach ($marriages as $m) {
            foreach ([$m->husband_id, $m->wife_id] as $pid) {
                if ($pid !== null) {
                    $marriagesOf[$pid][] = $m;
                }
            }
        }

        // Child links within the walk, in the one sibling ordering.
        $this->children = [];
        $raw = [];
        foreach ($this->people as $child) {
            foreach ([$child->father_id, $child->mother_id] as $parentId) {
                if ($parentId === null || ! isset($this->people[$parentId]) || $parentId === $child->id) {
                    continue;
                }
                if (! $showHidden && $child->hide_second_parent && $child->otherParentId() === $parentId) {
                    continue;
                }
                $raw[$parentId][$child->id] = $child;
            }
        }
        foreach ($raw as $parentId => $kids) {
            $order = array_map(fn ($m) => $m->spouseIdOf($parentId), $marriagesOf[$parentId] ?? []);
            $this->children[$parentId] = $this->family->orderChildren(collect(array_values($kids)), $parentId, $order)->pluck('id')->all();
        }

        // Minimum depth of each person, breadth-first, to the depth shown.
        $minD = [$rootId => 0];
        $queue = [$rootId];
        while ($queue) {
            $x = array_shift($queue);
            if ($minD[$x] >= $depth - 1) {
                continue;
            }
            foreach ($this->children[$x] ?? [] as $k) {
                if (! isset($minD[$k])) {
                    $minD[$k] = $minD[$x] + 1;
                    $queue[] = $k;
                }
            }
        }

        // Depth-first build: each child attached only at its minimum depth, and only once.
        $collapsedSet = array_fill_keys(array_map('intval', $collapsed), true);
        $seen = [];
        $shown = [];
        $build = function (int $id, int $d) use (&$build, &$seen, &$shown, $minD, $depth, $collapsedSet, $marriagesOf, $everyone) {
            $p = $this->people[$id] ?? null;
            if (! $p || isset($seen[$id])) {
                return null;
            }
            $seen[$id] = true;
            $shown[$id] = true;

            $sps = [];
            foreach ($marriagesOf[$id] ?? [] as $m) {
                $sid = $m->spouseIdOf($id);
                $s = $sid ? ($everyone[$sid] ?? null) : null;
                if (! $s || (isset($seen[$sid]) && ! $this->isFounder($s))) {
                    continue;
                }
                $shown[$sid] = true;
                $sps[] = ['id' => $sid, 'marriage' => $m->date_text ?: ($m->date ? substr($m->date, 0, 4) : '')];
            }

            $all = $this->children[$id] ?? [];
            $node = [
                'id' => $id, 'd' => $d, 'spouses' => $sps, 'kids' => [], 'nKids' => count($all),
                'collapsed' => isset($collapsedSet[$id]) && count($all) > 0, 'cut' => false, 'total' => 0,
            ];
            if ($all) {
                if ($d >= $depth - 1) {
                    $node['cut'] = true;
                    $node['total'] = $this->countBelow($id);
                } else {
                    foreach ($all as $k) {
                        if (($minD[$k] ?? null) === $d + 1 && ! isset($seen[$k])) {
                            $n = $build($k, $d + 1);
                            if ($n) {
                                $node['kids'][] = $n;
                            }
                        }
                    }
                }
            }
            if (! $node['cut']) {
                $node['total'] = array_sum(array_map(fn ($k) => 1 + $k['total'], $node['kids']));
            }
            if ($node['collapsed']) {
                $node['kids'] = [];
            }

            return $node;
        };
        $root = $build($rootId, 0);

        return [
            'root' => $root,
            'rootPerson' => [
                'id' => $rootId,
                'clan_id' => $this->people[$rootId]->clan_id,
                'generation' => $this->people[$rootId]->generation,
            ],
            'people' => $this->present(array_intersect_key($everyone, $shown), $marriagesOf, $everyone),
            // Every descendant in the walk (any depth), for outline search counts.
            'search' => $this->haystacks($marriagesOf, $everyone),
            'familyPhotos' => $this->familyPhotos(array_keys($this->people), $marriagesOf),
        ];
    }

    /**
     * One recursive CTE, down the parent links. No clan_id in it, deliberately.
     *
     * @return int[]
     */
    public function reachable(int $rootId, bool $showHidden = false): array
    {
        $clanLine = Generations::clanLineSql('c');
        $other = "(CASE WHEN $clanLine = c.father_id THEN c.mother_id ELSE c.father_id END)";

        $rows = DB::select(
            "WITH RECURSIVE d (id) AS (
                SELECT id FROM people WHERE id = ? AND deleted_at IS NULL
                UNION
                SELECT c.id FROM people c
                JOIN d ON c.father_id = d.id OR c.mother_id = d.id
                WHERE c.deleted_at IS NULL
                  AND (? = 1 OR NOT (c.hide_second_parent = 1 AND d.id = $other))
            )
            SELECT id FROM d",
            [$rootId, $showHidden ? 1 : 0]
        );

        return array_map(fn ($r) => (int) $r->id, $rows);
    }

    /** Descendants below $id in the walk, however deep ("N more below"). */
    private function countBelow(int $id): int
    {
        if (isset($this->reachCount[$id])) {
            return $this->reachCount[$id];
        }
        $set = [$id => true];
        $q = [$id];
        while ($q) {
            $x = array_shift($q);
            foreach ($this->children[$x] ?? [] as $k) {
                if (! isset($set[$k])) {
                    $set[$k] = true;
                    $q[] = $k;
                }
            }
        }

        return $this->reachCount[$id] = count($set) - 1;
    }

    private array $clans = [];

    private function clan(int $id): ?Clan
    {
        return $this->clans[$id] ??= Clan::withTrashed()->find($id);
    }

    private function isFounder(Person $p): bool
    {
        return (bool) $this->clan($p->clan_id)?->isFounder($p);
    }

    /** What each box needs. Living redaction happens in the renderer (it's a print option). */
    private function present(array $persons, array $marriagesOf, array $everyone): array
    {
        $ids = array_keys($persons);
        $portraits = Photo::where('kind', PhotoKind::Portrait->value)->whereIn('person_id', $ids)->whereNull('marriage_id')
            ->inPhotoOrder()->get()->groupBy('person_id');

        $out = [];
        foreach ($persons as $id => $p) {
            $other = $p->hide_second_parent ? $p->otherParentId() : null;
            $otherP = $other ? ($everyone[$other] ?? Person::unscoped()->find($other)) : null;
            $photo = $portraits[$id][0] ?? null;
            $out[$id] = [
                'id' => $id,
                'name' => $p->fullName(),
                'nickname' => $p->nickname,
                'span' => Format::span($p),
                'initials' => Format::initials($p),
                'clan_id' => $p->clan_id,
                'clan_label' => Format::clanLabel($this->clan($p->clan_id)),
                'is_living' => $p->is_living,
                'is_founder' => $this->isFounder($p),
                'generation' => $p->generation,
                'sibling_order' => $p->sibling_order,
                'is_subclan_head' => $p->is_subclan_head,
                'subclan_name' => $p->subclan_name,
                'hidden_link' => $otherP?->fullName(),
                'portrait' => $photo?->url(),
            ];
        }

        return $out;
    }

    /** "name nickname spouse names" for every descendant in the walk. */
    private function haystacks(array $marriagesOf, array $everyone): array
    {
        $out = [];
        foreach ($this->people as $id => $p) {
            $spouses = array_filter(array_map(function ($m) use ($id, $everyone) {
                $sid = $m->spouseIdOf($id);

                return $sid && isset($everyone[$sid]) ? $everyone[$sid]->fullName().' '.$everyone[$sid]->nickname : null;
            }, $marriagesOf[$id] ?? []));
            $out[$id] = trim($p->fullName().' '.$p->nickname.' '.implode(' ', $spouses));
        }

        return $out;
    }

    /** Main family picture per marriage (and per solo parent), for the printed outline. */
    private function familyPhotos(array $personIds, array $marriagesOf): array
    {
        $marriageIds = collect($marriagesOf)->only($personIds)->flatten()->pluck('id')->unique()->values()->all();
        $photos = Photo::where('kind', PhotoKind::Family->value)->where('is_primary', true)
            ->where(fn ($w) => $w->whereIn('marriage_id', $marriageIds)->orWhere(fn ($x) => $x->whereNull('marriage_id')->whereIn('person_id', $personIds)))
            ->get();
        $byMarriage = $photos->whereNotNull('marriage_id')->keyBy('marriage_id');
        $byPerson = $photos->whereNull('marriage_id')->keyBy('person_id');

        $out = [];
        foreach ($personIds as $id) {
            $list = [];
            foreach ($marriagesOf[$id] ?? [] as $m) {
                if ($ph = $byMarriage[$m->id] ?? null) {
                    $list[] = ['url' => $ph->url(), 'caption' => $ph->captionLine(), 'w' => $ph->width, 'h' => $ph->height];
                }
            }
            if ($ph = $byPerson[$id] ?? null) {
                $list[] = ['url' => $ph->url(), 'caption' => $ph->captionLine(), 'w' => $ph->width, 'h' => $ph->height];
            }
            if ($list) {
                $out[$id] = $list;
            }
        }

        return $out;
    }
}
