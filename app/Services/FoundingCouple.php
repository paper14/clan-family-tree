<?php

namespace App\Services;

use App\Models\Clan;
use App\Models\Person;
use App\Services\Backup\BackupService;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moving the root (planning.md §2.4): the ONLY clan-wide renumbering besides restore.
 * Preview first; then an automatic backup of all clans, then one transaction that re-points
 * the founders, appends the previous couple to the clan notes and renumbers the clan.
 */
class FoundingCouple
{
    public function __construct(
        private Generations $generations,
        private Family $family,
        private BackupService $backups,
    ) {}

    /** What would change — nothing is written. */
    public function preview(Clan $clan, Person $founder, ?Person $spouse): array
    {
        $people = Person::unscoped()->where('clan_id', $clan->id)->get(['id', 'generation']);
        $before = $people->pluck('generation', 'id')->all();
        $after = $this->simulate($clan->id, array_filter([$founder->id, $spouse?->id]));

        $changed = 0;
        $lost = 0;
        foreach ($before as $id => $g) {
            $n = $after[$id] ?? null;
            if ($g !== $n) {
                $changed++;
            }
            if ($g !== null && $n === null) {
                $lost++;
            }
        }
        $old = $clan->founder;

        return [
            'total' => count($before),
            'changed' => $changed,
            'numbered' => count(array_filter($after, fn ($g) => $g !== null)),
            'lost' => $lost,
            'old' => $old ? [
                'name' => $old->fullName(),
                'from' => $before[$old->id] ?? null,
                'to' => $after[$old->id] ?? null,
            ] : null,
            'founderHasParents' => $founder->hasParents(),
            'founderName' => $founder->fullName(),
        ];
    }

    /**
     * Back up, then renumber. Throws (and changes nothing) if the backup fails.
     *
     * @return array{numbered: int, oldFounder: ?Person, backup: string}
     */
    public function apply(Clan $clan, Person $founder, ?Person $spouse): array
    {
        try {
            $backup = $this->backups->create('before-renumbering', true);
        } catch (\Throwable $e) {
            throw new RuntimeException('The backup failed, so nothing was renumbered. '.$e->getMessage(), 0, $e);
        }

        $oldFounder = $clan->founder;
        $numbered = DB::transaction(function () use ($clan, $founder, $spouse) {
            $line = 'Previous founding couple: '.$clan->foundersText().' (until '.Format::longDate(now()).').';
            $clan->update([
                'founder_id' => $founder->id,
                'founder_spouse_id' => $spouse?->id,
                'notes' => $clan->notes ? $clan->notes."\n".$line : $line,
            ]);

            return $this->generations->recomputeClan($clan->id);
        });

        return ['numbered' => $numbered, 'oldFounder' => $oldFounder?->refresh(), 'backup' => basename($backup)];
    }

    /** The clan's generations as they would be from these founders, breadth-first on the clan line. */
    private function simulate(int $clanId, array $seeds): array
    {
        if (! $seeds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($seeds), '?'));
        $line = Generations::clanLineSql('k');
        $rows = DB::select(
            "WITH RECURSIVE line (id, gen) AS (
                SELECT id, 1 FROM people WHERE clan_id = ? AND deleted_at IS NULL AND id IN ($in)
                UNION ALL
                SELECT k.id, line.gen + 1 FROM people k JOIN line ON $line = line.id
                WHERE k.clan_id = ? AND k.deleted_at IS NULL AND line.gen < 900
            )
            SELECT id, MIN(gen) AS g FROM line GROUP BY id",
            [$clanId, ...$seeds, $clanId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = (int) $r->g;
        }

        return $out;
    }
}
