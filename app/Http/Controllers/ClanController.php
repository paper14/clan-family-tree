<?php

namespace App\Http\Controllers;

use App\Enums\PhotoKind;
use App\Http\Presenters\People;
use App\Http\Presenters\Photos;
use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\Photo;
use App\Services\Couples;
use App\Services\Descendants;
use App\Services\Family;
use App\Services\FoundingCouple;
use App\Services\Generations;
use App\Services\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClanController extends Controller
{
    public function __construct(private People $people, private Membership $membership, private Family $family) {}

    /** Clan list: the landing screen (planning.md §3, screen 1). */
    public function index(): Response
    {
        $current = $this->currentClan()?->id;
        $clans = Clan::orderBy('name')->get()->map(function (Clan $c) use ($current) {
            $ppl = Person::unscoped()->where('clan_id', $c->id)->get(['id', 'generation', 'father_id', 'mother_id']);
            $married = $this->membership->marriedIds($ppl);
            $founders = array_filter([$c->founder_id, $c->founder_spouse_id]);
            $noParents = $ppl->filter(fn ($p) => $p->father_id === null && $p->mother_id === null && ! in_array($p->id, $founders, true));
            $unplaced = $noParents->reject(fn ($p) => isset($married[$p->id]))->count();
            $marriedIn = $noParents->filter(fn ($p) => isset($married[$p->id]))->count();
            $placed = $ppl->whereNotNull('generation')->count();
            $cross = Marriage::query()
                ->join('people as h', 'h.id', '=', 'marriages.husband_id')
                ->join('people as w', 'w.id', '=', 'marriages.wife_id')
                ->whereColumn('h.clan_id', '<>', 'w.clan_id')
                ->where(fn ($q) => $q->where('h.clan_id', $c->id)->orWhere('w.clan_id', $c->id))
                ->count();
            $banner = Photo::sameAttachment(PhotoKind::ClanGroup, 'clan', $c->id)->inPhotoOrder()->first();

            return [
                'id' => $c->id,
                'label' => $c->label(),
                'origin_place' => $c->origin_place,
                'foundersText' => $c->foundersText(),
                'people' => $ppl->count(),
                'generations' => (int) $ppl->max('generation'),
                'subclans' => $ppl->count() ? Person::unscoped()->where('clan_id', $c->id)->where('is_subclan_head', true)->whereNotNull('generation')->count() : 0,
                'unplaced' => $unplaced,
                'crossMarriages' => $cross,
                'someUnnumbered' => $placed < $ppl->count() - $unplaced - $marriedIn,
                'banner' => $banner ? ['url' => $banner->url(), 'caption' => $banner->caption] : null,
                'current' => $c->id === $current,
            ];
        });

        $deleted = Clan::onlyTrashed()->orderBy('name')->get()->map(fn (Clan $c) => [
            'id' => $c->id,
            'label' => $c->label(),
            'people' => Person::unscoped()->where('clan_id', $c->id)->count(),
            'deleted_at' => $c->deleted_at->toDateString(),
        ]);

        return Inertia::render('Clans/Index', ['clans' => $clans, 'deleted' => $deleted]);
    }

    public function create(): Response
    {
        return Inertia::render('Clans/Create');
    }

    /** A clan starts with a name; the founding couple can be typed in now or set later. */
    public function store(Request $request, Generations $generations): RedirectResponse
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'the clan needs a name.']);
        }

        $clan = DB::transaction(function () use ($request, $name, $generations) {
            $clan = Clan::create([
                'name' => $name,
                'origin_place' => trim((string) $request->input('origin_place')) ?: null,
                'notes' => trim((string) $request->input('notes')) ?: null,
            ]);
            $fg = trim((string) $request->input('f_given'));
            $sg = trim((string) $request->input('s_given'));
            $fsex = $request->input('f_sex') === 'female' ? 'female' : 'male';
            if ($fg !== '') {
                $f = Person::unscoped()->create([
                    'clan_id' => $clan->id, 'given_name' => $fg, 'last_name' => trim((string) $request->input('f_last')) ?: null,
                    'sex' => $fsex, 'is_living' => false, 'sibling_order' => 1,
                ]);
                $clan->founder_id = $f->id;
                if ($sg !== '') {
                    $s = Person::unscoped()->create([
                        'clan_id' => $clan->id, 'given_name' => $sg, 'last_name' => trim((string) $request->input('s_last')) ?: null,
                        'sex' => $fsex === 'male' ? 'female' : 'male', 'is_living' => false, 'sibling_order' => 1,
                    ]);
                    $clan->founder_spouse_id = $s->id;
                    [$h, $w] = Couples::marriageColumns($f, $s);
                    Marriage::create(['husband_id' => $h->id, 'wife_id' => $w->id]);
                }
                $clan->save();
            }
            $generations->recomputeClan($clan->id);

            return $clan;
        });

        $this->switchClan($clan);
        $this->toast('Created the '.$clan->label().'.');

        return redirect()->route('people.index');
    }

    /** Clan settings: details, Set founding couple (with preview), group photos, subclans, delete. */
    public function edit(Request $request, Clan $clan, FoundingCouple $fc, Descendants $descendants, Photos $photos): Response
    {
        $this->switchClan($clan);

        $founderId = $request->has('founder') ? ((int) $request->query('founder') ?: null) : $clan->founder_id;
        $founder = $founderId ? Person::unscoped()->where('clan_id', $clan->id)->find($founderId) : null;
        $spouses = $founder ? $this->family->spousesOf($founder->id) : collect();
        if ($request->has('spouse')) {
            $spouseId = (int) $request->query('spouse') ?: null;
        } elseif ($request->has('founder')) {
            $spouseId = $spouses->first()?->id; // a new founder: their first spouse by default
        } else {
            $spouseId = $clan->founder_spouse_id;
        }
        $spouse = $spouseId ? $spouses->firstWhere('id', $spouseId) : null;
        $changed = $founder && ($founder->id !== $clan->founder_id || ($spouse?->id) !== $clan->founder_spouse_id);

        $members = Person::unscoped()->where('clan_id', $clan->id)->get();
        $married = $this->membership->marriedIds($members);
        $sorted = $this->membership->sortByFamily($members, $this->membership->familyOrder($clan));
        $sorted = $sorted->sortBy(fn ($p) => $p->generation === null ? 1 : 0)->values();

        $heads = $this->membership->subclanHeads($clan)->map(fn (Person $h) => [
            'id' => $h->id,
            'name' => $h->fullName(),
            'subclan_name' => $h->subclan_name,
            'generation' => $h->generation,
            'people' => count($descendants->reachable($h->id)),
        ]);

        return Inertia::render('Clans/Settings', [
            'clan' => [
                'id' => $clan->id,
                'name' => $clan->name,
                'label' => $clan->label(),
                'origin_place' => $clan->origin_place,
                'notes' => $clan->notes,
                'foundersText' => $clan->foundersText(),
                'founder_id' => $clan->founder_id,
                'founder_spouse_id' => $clan->founder_spouse_id,
                'people' => $members->count(),
            ],
            'founderOptions' => $sorted->map(fn ($p) => ['id' => $p->id, 'label' => $this->people->optionLabel($p, $married)])->values(),
            'chosen' => ['founder' => $founder?->id, 'spouse' => $spouse?->id],
            'spouseOptions' => $spouses->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->fullName().($s->clan_id !== $clan->id ? ' · '.$this->people->clan($s->clan_id)?->label() : ' · spouse'),
            ])->values(),
            'preview' => $changed ? $fc->preview($clan, $founder, $spouse) : null,
            'heads' => $heads,
            'photos' => $photos->group(PhotoKind::ClanGroup, 'clan', $clan->id),
        ]);
    }

    public function update(Request $request, Clan $clan): RedirectResponse
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'the clan needs a name.']);
        }
        $clan->update([
            'name' => $name,
            'origin_place' => trim((string) $request->input('origin_place')) ?: null,
            'notes' => trim((string) $request->input('notes')) ?: null,
        ]);
        $this->toast('Saved clan details.');

        return back();
    }

    /** "Back up, then set founding couple and renumber" (planning.md §2.4). */
    public function setFounders(Request $request, Clan $clan, FoundingCouple $fc): RedirectResponse
    {
        $founder = Person::unscoped()->where('clan_id', $clan->id)->findOrFail((int) $request->input('founder_id'));
        $spouse = null;
        if ($request->filled('spouse_id')) {
            $spouse = $this->family->spousesOf($founder->id)->firstWhere('id', (int) $request->input('spouse_id'));
            if (! $spouse) {
                throw ValidationException::withMessages(['spouse_id' => 'the founder’s spouse must be someone married to the founder.']);
            }
        }

        try {
            $result = $fc->apply($clan, $founder, $spouse);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['founders' => $e->getMessage()]);
        }

        $old = $result['oldFounder'];
        $this->toast('Backed up, then renumbered the '.$clan->label().': '.$result['numbered'].' people numbered.'
            .($old && $old->generation !== null ? ' '.$old->fullName().' is now Gen '.$old->generation.'.' : ''));

        return redirect()->route('clans.edit', $clan);
    }

    /** Soft delete, with the people count shown in the confirmation. Restorable from the clan list. */
    public function destroy(Clan $clan): RedirectResponse
    {
        $n = Person::unscoped()->where('clan_id', $clan->id)->count();
        $clan->delete();
        if (session('clan_id') === $clan->id) {
            session()->forget('clan_id');
        }
        $this->toast('Deleted the '.$clan->label().' ('.$n.' people).');

        return redirect()->route('clans.index');
    }

    public function restore(Clan $clan): RedirectResponse
    {
        $clan->restore();
        $this->toast('Restored the '.$clan->label().'.');

        return redirect()->route('clans.index');
    }

    /** The header's clan picker. Stays on the same kind of screen where that makes sense. */
    public function switch(Request $request): RedirectResponse
    {
        $clan = Clan::findOrFail((int) $request->input('clan_id'));
        $this->switchClan($clan);
        $to = (string) $request->input('to');

        return match ($to) {
            'tree' => redirect()->route('tree'),
            'print' => redirect()->route('print'),
            'children' => redirect()->route('children.create'),
            'new' => redirect()->route('people.create'),
            'settings' => redirect()->route('clans.edit', $clan),
            'open' => redirect()->route('people.index'),
            default => redirect()->route('people.index'),
        };
    }
}
