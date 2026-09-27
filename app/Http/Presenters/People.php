<?php

namespace App\Http\Presenters;

use App\Enums\PhotoKind;
use App\Models\Clan;
use App\Models\Person;
use App\Models\Photo;
use App\Services\Family;
use App\Services\Membership;
use App\Support\Format;
use App\Support\Generation;
use Illuminate\Support\Collection;

/**
 * Turns people into what the screens show. Labels here come from the prototype:
 * "Juan Santos · Gen 2", "· married in", "· unplaced". Generations shown on membership
 * screens always go through the one helper (Support\Generation).
 */
class People
{
    private array $clanCache = [];

    public function __construct(private Family $family, private Membership $membership) {}

    public function clan(int $id): ?Clan
    {
        return $this->clanCache[$id] ??= Clan::withTrashed()->find($id);
    }

    /** A person card: photo, generation, name, dates, other-clan label, living. */
    public function card(Person $p, ?int $relToClan = null): array
    {
        return [
            'id' => $p->id,
            'name' => $p->fullName(),
            'nickname' => $p->nickname,
            'initials' => Format::initials($p),
            'generation' => Generation::display($p),
            'life' => Format::life($p),
            'span' => Format::span($p),
            'is_living' => $p->is_living,
            'clan_id' => $p->clan_id,
            'clan_label' => Format::clanLabel($this->clan($p->clan_id)),
            'other_clan' => $relToClan !== null && $p->clan_id !== $relToClan,
            'portrait' => $this->portrait($p->id),
        ];
    }

    public function portrait(int $personId): ?string
    {
        return Photo::where('kind', PhotoKind::Portrait->value)->where('person_id', $personId)->whereNull('marriage_id')
            ->inPhotoOrder()->first()?->url();
    }

    /** "Juan Santos · Gen 2" / "· married in" / "· unplaced". */
    public function optionLabel(Person $p, array $married, ?Person $root = null): string
    {
        $g = Generation::display($p, $root);
        if ($g !== null) {
            return $p->fullName().' · Gen '.$g;
        }
        $clan = $this->clan($p->clan_id);
        $isFounder = $clan?->isFounder($p);
        if (! $isFounder && ! $p->hasParents() && isset($married[$p->id])) {
            return $p->fullName().' · married in';
        }

        return $p->fullName().($isFounder ? '' : ' · unplaced');
    }

    /** Members of a clan as picker options, in generation-then-family order. */
    public function memberOptions(Clan $clan, ?int $excludeId = null, ?callable $filter = null): array
    {
        $people = Person::unscoped()->where('clan_id', $clan->id)->get()
            ->reject(fn ($p) => $p->id === $excludeId)
            ->filter($filter ?? fn () => true);
        $married = $this->membership->marriedIds($people);
        $sorted = $this->membership->sortByFamily($people, $this->membership->familyOrder($clan));

        return $sorted->map(fn (Person $p) => [
            'id' => $p->id,
            'label' => $this->optionLabel($p, $married),
            'generation' => $p->generation,
            'sex' => $p->sex?->value,
            'last_name' => $p->last_name,
        ])->values()->all();
    }

    /** Every live clan's people, grouped, the given clan first (the other-parent picker). */
    public function allClanOptions(int $firstClanId, ?int $excludeId = null): array
    {
        $clans = Clan::orderBy('name')->get()->sortBy(fn ($c) => $c->id === $firstClanId ? 0 : 1)->values();

        return $clans->map(function (Clan $c) use ($firstClanId, $excludeId) {
            $opts = $this->memberOptions($c, $excludeId);
            if ($c->id !== $firstClanId) {
                foreach ($opts as &$o) {
                    $o['label'] .= ' · '.$c->name;
                }
            }

            return ['label' => $c->label(), 'options' => $opts];
        })->filter(fn ($g) => $g['options'])->values()->all();
    }

    /**
     * The start-person dropdown (membership, so this clan only): founding couple, then
     * marked subclan heads, then everyone numbered.
     */
    public function startOptions(Clan $clan): array
    {
        $members = Person::unscoped()->where('clan_id', $clan->id)->whereNotNull('generation')->get();
        $order = $this->membership->familyOrder($clan);
        $sorted = $this->membership->sortByFamily($members, $order);
        $founder = $clan->founder_id ? $sorted->firstWhere('id', $clan->founder_id) : null;
        $heads = $sorted->filter(fn ($p) => $p->is_subclan_head && $p->id !== $founder?->id);
        $rest = $sorted->reject(fn ($p) => $p->id === $founder?->id || $p->is_subclan_head);

        $groups = [];
        if ($founder) {
            $groups[] = ['label' => 'Founding couple', 'options' => [[
                'id' => $founder->id, 'label' => $clan->foundersText().' · Gen 1', 'head' => false, 'founder' => true,
            ]]];
        }
        if ($heads->isNotEmpty()) {
            $groups[] = ['label' => 'Subclan heads', 'options' => $heads->map(fn ($p) => [
                'id' => $p->id,
                'label' => ($p->subclan_name ? $p->subclan_name.' — ' : '').$p->fullName().' · Gen '.$p->generation,
                'head' => true, 'founder' => false,
            ])->values()->all()];
        }
        $groups[] = ['label' => 'Anyone in the tree', 'options' => $rest->map(fn ($p) => [
            'id' => $p->id, 'label' => $p->fullName().' · Gen '.$p->generation, 'head' => false, 'founder' => false,
        ])->values()->all()];

        return $groups;
    }

    /** The default start: the founder if numbered, else the first numbered member. */
    public function defaultStart(Clan $clan): ?int
    {
        $f = $clan->founder;
        if ($f && $f->generation !== null) {
            return $f->id;
        }
        $members = Person::unscoped()->where('clan_id', $clan->id)->whereNotNull('generation')->get();

        return $this->membership->sortByFamily($members, $this->membership->familyOrder($clan))->first()?->id;
    }

    /** Clan-level facts every chart caption needs. */
    public function clanInfo(Clan $clan): array
    {
        return [
            'id' => $clan->id,
            'name' => $clan->name,
            'label' => $clan->label(),
            'foundersText' => $clan->foundersText(),
            'founderId' => $clan->founder_id,
            'founderSpouseId' => $clan->founder_spouse_id,
        ];
    }

    /** @param Collection<int, Person> $line */
    public function line(Collection $line): array
    {
        return $line->map(fn (Person $x) => ['id' => $x->id, 'name' => $x->fullName(), 'generation' => $x->generation])->all();
    }
}
