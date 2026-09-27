<?php

namespace App\Services;

use App\Models\Clan;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * STORED generation: absolute, per the member's own clan, counted from that clan's
 * founding couple (Gen 1) along CLAN-LINE links only. NULL = married in, or not on this
 * clan's line. This is membership numbering; the number a chart shows is different
 * (depth along the path walked — see Descendants).
 *
 * Only Set founding couple and restore renumber a whole clan. Saving a person updates
 * that person and their clan-line descendants, nothing else.
 */
class Generations
{
    /** SQL for a row's clan-line parent id. */
    public const CLAN_LINE_SQL = "(CASE WHEN %1\$s.clan_parent = 'mother' THEN COALESCE(%1\$s.mother_id, %1\$s.father_id) ELSE COALESCE(%1\$s.father_id, %1\$s.mother_id) END)";

    public static function clanLineSql(string $alias): string
    {
        return sprintf(self::CLAN_LINE_SQL, $alias);
    }

    /**
     * Renumber every member of a clan, breadth-first from the founder and spouse, in one
     * set-based statement. Returns how many are numbered.
     */
    public function recomputeClan(int $clanId): int
    {
        $clan = Clan::withTrashed()->find($clanId);
        $seeds = array_values(array_filter([$clan?->founder_id, $clan?->founder_spouse_id]));

        if (! $seeds) {
            Person::unscoped()->where('clan_id', $clanId)->update(['generation' => null]);

            return 0;
        }

        $in = implode(',', array_fill(0, count($seeds), '?'));
        $line = self::clanLineSql('k');

        DB::update(
            "WITH RECURSIVE line (id, gen) AS (
                SELECT id, 1 FROM people WHERE clan_id = ? AND deleted_at IS NULL AND id IN ($in)
                UNION ALL
                SELECT k.id, line.gen + 1 FROM people k JOIN line ON $line = line.id
                WHERE k.clan_id = ? AND k.deleted_at IS NULL AND line.gen < 900
            )
            UPDATE people p LEFT JOIN (SELECT id, MIN(gen) AS g FROM line GROUP BY id) l ON l.id = p.id
            SET p.generation = l.g
            WHERE p.clan_id = ?",
            [$clanId, ...$seeds, $clanId, $clanId]
        );

        return Person::unscoped()->where('clan_id', $clanId)->whereNotNull('generation')->count();
    }

    public function recomputeAll(): void
    {
        foreach (Clan::withTrashed()->pluck('id') as $id) {
            $this->recomputeClan($id);
        }
    }

    /** What $p's own generation should be, from its clan-line parent. */
    public function expectedFor(Person $p, ?Clan $clan = null): ?int
    {
        $clan ??= Clan::withTrashed()->find($p->clan_id);
        if ($clan && $clan->isFounder($p)) {
            return 1;
        }
        $cpId = $p->clanParentId();
        $cp = $cpId ? Person::unscoped()->find($cpId) : null;

        return $cp && $cp->clan_id === $p->clan_id && $cp->generation !== null ? $cp->generation + 1 : null;
    }

    /**
     * On save: this person and the people below them on the clan line, only.
     * One query per generation level, never the whole clan.
     */
    public function updateSubtree(Person $p): void
    {
        $clan = Clan::withTrashed()->find($p->clan_id);
        $gen = $this->expectedFor($p, $clan);
        $this->write($p->id, $gen);
        $p->generation = $gen;

        $frontier = [$p->id => $gen];
        $seen = [$p->id => true];
        while ($frontier) {
            $ids = array_keys($frontier);
            $kids = Person::unscoped()->where('clan_id', $p->clan_id)
                ->where(fn ($w) => $w->whereIn('father_id', $ids)->orWhereIn('mother_id', $ids))
                ->get();
            $next = [];
            foreach ($kids as $k) {
                $cp = $k->clanParentId();
                if (isset($seen[$k->id]) || ! array_key_exists($cp, $frontier)) {
                    continue;
                }
                $seen[$k->id] = true;
                $g = $clan && $clan->isFounder($k) ? 1 : ($frontier[$cp] === null ? null : $frontier[$cp] + 1);
                $this->write($k->id, $g);
                $next[$k->id] = $g;
            }
            $frontier = $next;
        }
    }

    private function write(int $id, ?int $gen): void
    {
        Person::unscoped()->whereKey($id)->where(fn ($w) => $gen === null
            ? $w->whereNotNull('generation')
            : $w->whereNull('generation')->orWhere('generation', '<>', $gen))
            ->update(['generation' => $gen]);
    }
}
