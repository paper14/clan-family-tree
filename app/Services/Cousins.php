<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * A person's cousins: 1st, 2nd, 3rd … — people of the same generation who share
 * grandparents, great-grandparents, and so on.
 *
 * This is DESCENT, so it is unscoped: cousins are found through both parents, whatever
 * clan each person is a member of. Unlike the tree it walks UP, to the shared ancestors,
 * but only to find who else descends from them. Nothing here is drawn in a chart, so the
 * tree's never-walk-up rule (planning.md §2.7) is untouched.
 *
 *  - An nth cousin shares ancestors n+1 generations up, reached by the same number of
 *    steps down. Cousins "removed" (a different generation) are not listed.
 *  - Each person is listed once, at the closest degree. Siblings, ancestors and the
 *    person's own descendants are never listed as cousins.
 *  - A parent link hidden at the family's request (hide_second_parent) is not followed,
 *    up or down.
 *  - Within a group, cousins follow sibling_order down from the shared ancestors —
 *    never dates.
 */
class Cousins
{
    public const MAX_DEGREE = 8;

    public function __construct(private Descendants $descendants) {}

    /**
     * Degrees ascending; within a degree, one group per set of shared ancestors,
     * father's side first.
     *
     * With $throughId (one of the person's ancestors, grandparent or further up), only
     * cousins who descend from that ancestor, in the person's own generation. Each still
     * shows at their closest degree: through a great-grandfather, his great-grandchildren
     * are 2nd cousins, except those who also share a grandparent, who are 1st cousins.
     *
     * @return array<int, array{degree: int, groups: array<int, array{via: Collection<int, Person>, side: string, cousins: Collection<int, Person>}>}>
     */
    public function of(Person $p, ?int $throughId = null, int $maxDegree = self::MAX_DEGREE): array
    {
        [$dist, $path] = $this->ancestors($p, $maxDegree + 1);
        if (count($dist) < 2 || ($throughId !== null && ($dist[$throughId] ?? 0) < 2)) {
            return [];
        }

        [$found, $traced] = $this->walkDown($dist, $throughId);

        $exclude = $dist + array_fill_keys($this->descendants->reachable($p->id), 0);
        $found = array_filter($found, fn ($f, $id) => ! isset($exclude[$id]) && $f['k'] >= 2
            && ($throughId === null || isset($traced[$id])), ARRAY_FILTER_USE_BOTH);
        if (! $found) {
            return [];
        }

        $viaIds = array_unique(array_merge(...array_column($found, 'via')));
        $people = Person::unscoped()->whereIn('id', [...array_keys($found), ...$viaIds])->get()->keyBy('id');

        // Group by degree, then by the exact set of shared ancestors.
        $groups = [];
        foreach ($found as $id => $f) {
            $via = $f['via'];
            usort($via, fn ($a, $b) => [$path[$a], $a] <=> [$path[$b], $b]);
            $key = implode(',', $via);
            $groups[$f['k']][$key] ??= ['via' => $via, 'cousins' => []];
            $groups[$f['k']][$key]['cousins'][$id] = $f['key'];
        }
        ksort($groups);

        $out = [];
        foreach ($groups as $k => $byVia) {
            uasort($byVia, fn ($a, $b) => $path[$a['via'][0]] <=> $path[$b['via'][0]]);
            $out[] = [
                'degree' => $k - 1,
                'groups' => array_values(array_map(function ($g) use ($people, $path) {
                    asort($g['cousins']);

                    return [
                        'via' => collect($g['via'])->map(fn ($id) => $people[$id])->values(),
                        'side' => $path[$g['via'][0]][0] === 'F' ? 'father' : 'mother',
                        'cousins' => collect(array_keys($g['cousins']))->map(fn ($id) => $people[$id])->filter()->values(),
                    ];
                }, $byVia)),
            ];
        }

        return $out;
    }

    /**
     * The ancestors cousins can be found through: grandparents and further up, both sides,
     * nearest generation first, father's side first.
     *
     * @return array<int, array{person: Person, up: int, side: string}>
     */
    public function ancestorsOf(Person $p, int $maxDegree = self::MAX_DEGREE): array
    {
        [$dist, $path] = $this->ancestors($p, $maxDegree + 1);
        $ids = array_keys(array_filter($dist, fn ($k) => $k >= 2));
        usort($ids, fn ($a, $b) => [$dist[$a], $path[$a]] <=> [$dist[$b], $path[$b]]);
        $people = Person::unscoped()->whereIn('id', $ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(fn ($id) => isset($people[$id]) ? [
            'person' => $people[$id], 'up' => $dist[$id], 'side' => $path[$id][0] === 'F' ? 'father' : 'mother',
        ] : null, $ids)));
    }

    /**
     * Breadth-first up both parent chains.
     *
     * @return array{0: array<int, int>, 1: array<int, string>} [id => generations up, id => path like "FM"]
     */
    private function ancestors(Person $p, int $maxUp): array
    {
        $dist = [$p->id => 0];
        $path = [$p->id => ''];
        $frontier = [$p];
        for ($d = 1; $d <= $maxUp && $frontier; $d++) {
            usort($frontier, fn ($a, $b) => $path[$a->id] <=> $path[$b->id]);
            $next = [];
            foreach ($frontier as $c) {
                foreach (['F' => $c->father_id, 'M' => $c->mother_id] as $side => $parentId) {
                    if ($parentId === null || isset($dist[$parentId]) || $this->hidden($c, $parentId)) {
                        continue;
                    }
                    $dist[$parentId] = $d;
                    $path[$parentId] = $path[$c->id].$side;
                    $next[] = $parentId;
                }
            }
            $frontier = $next ? Person::unscoped()->whereIn('id', $next)->get()->all() : [];
        }

        return [$dist, $path];
    }

    /**
     * From every ancestor k generations up, walk k generations down. One children query
     * per level for all ancestors together.
     *
     * Also collects the ids that $trace (one ancestor) reaches in the person's generation.
     *
     * @param  array<int, int>  $dist
     * @return array{0: array<int, array{k: int, via: int[], key: string}>, 1: array<int, true>} [id => closest shared generation, traced ids]
     */
    private function walkDown(array $dist, ?int $trace = null): array
    {
        $traced = [];
        $frontier = [];
        foreach ($dist as $id => $k) {
            if ($k >= 1) {
                $frontier["$id:$id"] = [$id, $id, ''];
            }
        }

        $found = [];
        for ($level = 1; $frontier; $level++) {
            $nodes = array_values(array_unique(array_column($frontier, 1)));
            $inNodes = array_flip($nodes);
            $kids = Person::unscoped()
                ->where(fn ($w) => $w->whereIn('father_id', $nodes)->orWhereIn('mother_id', $nodes))
                ->inSiblingOrder()
                ->get();

            $childrenOf = [];
            foreach ($kids as $c) {
                foreach (array_unique(array_filter([$c->father_id, $c->mother_id])) as $parentId) {
                    if (isset($inNodes[$parentId]) && ! $this->hidden($c, $parentId)) {
                        $childrenOf[$parentId][] = $c;
                    }
                }
            }

            $next = [];
            foreach ($frontier as [$origin, $node, $key]) {
                $k = $dist[$origin];
                foreach ($childrenOf[$node] ?? [] as $c) {
                    $ck = $key.sprintf('%05d.', $c->sibling_order);
                    if ($level < $k) {
                        $next["$origin:{$c->id}"] ??= [$origin, $c->id, $ck];

                        continue;
                    }
                    if ($origin === $trace) {
                        $traced[$c->id] = true;
                    }
                    $f = &$found[$c->id];
                    if ($f === null || $k < $f['k']) {
                        $f = ['k' => $k, 'via' => [$origin], 'key' => $ck];
                    } elseif ($k === $f['k']) {
                        $f['via'] = array_values(array_unique([...$f['via'], $origin]));
                        $f['key'] = min($f['key'], $ck);
                    }
                    unset($f);
                }
            }
            $frontier = $next;
        }

        return [$found, $traced];
    }

    /** The other-parent link the family asked to hide. */
    private function hidden(Person $child, int $parentId): bool
    {
        return $child->hide_second_parent && $child->otherParentId() === $parentId;
    }
}
