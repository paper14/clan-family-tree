<?php

namespace App\Http\Controllers;

use App\Enums\PhotoKind;
use App\Enums\Relation;
use App\Http\Presenters\People;
use App\Http\Presenters\Photos;
use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\Photo;
use App\Services\Cousins;
use App\Services\Descendants;
use App\Services\Family;
use App\Services\Membership;
use App\Services\PersonWriter;
use App\Services\SiblingOrder;
use App\Support\Format;
use App\Support\Generation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function __construct(
        private People $people,
        private Membership $membership,
        private Family $family,
        private Photos $photos,
    ) {}

    /**
     * People list (screen 4). MEMBERSHIP: this clan's members only, through the clan scope.
     * With a subclan root, only that head's line, numbered from them as Gen 0.
     */
    public function index(Request $request, Descendants $descendants): Response
    {
        $clan = $this->currentClan();
        if (! $clan) {
            return $this->needClan();
        }

        $members = Person::query()->get(); // scoped to the current clan
        $order = $this->membership->familyOrder($clan);
        $sorted = $this->membership->sortByFamily($members, $order);
        $married = $this->membership->marriedIds($members);

        $root = null;
        if ($request->filled('root')) {
            $root = $members->firstWhere('id', (int) $request->query('root'));
        }
        $shown = $sorted;
        if ($root) {
            $below = array_flip($descendants->reachable($root->id));
            $shown = $sorted->filter(fn ($p) => isset($below[$p->id]))->values();
        }

        $parents = Person::unscoped()->whereIn('id', $members->map->clanParentId()->filter()->unique())->get()->keyBy('id');
        $marriedInIds = $members->filter(fn ($p) => ! $clan->isFounder($p) && ! $p->hasParents() && isset($married[$p->id]))->pluck('id')->all();
        $spouseNames = $this->spouseNames($marriedInIds);

        $rows = $shown->map(function (Person $p) use ($clan, $root, $married, $parents, $spouseNames, $order) {
            $founder = $clan->isFounder($p);
            $marriedIn = ! $founder && ! $p->hasParents() && isset($married[$p->id]);
            $cp = $parents[$p->clanParentId()] ?? null;
            $sub = match (true) {
                $cp !== null => 'child of '.$cp->fullName(),
                $founder => 'founding couple',
                $marriedIn => 'married to '.implode(', ', $spouseNames[$p->id] ?? []),
                default => 'no parent linked',
            };

            return [
                'id' => $p->id,
                'name' => $p->fullName(),
                'nickname' => $p->nickname,
                'given_name' => $p->given_name,
                'last_name' => $p->last_name,
                'subclan_name' => $p->subclan_name,
                'g' => Generation::display($p, $root),
                'numbered' => $p->generation !== null,
                'span' => Format::span($p),
                'sub' => $sub,
                'unplaced' => ! $founder && ! $p->hasParents() && ! isset($married[$p->id]),
                'founder' => $founder,
                'head' => $p->is_subclan_head,
                'marriedIn' => $marriedIn,
                'living' => $p->is_living === true,
                'fam' => $order[$p->id] ?? 1_000_000_000,
            ];
        })->values();

        $unplaced = $members->filter(fn ($p) => ! $clan->isFounder($p) && ! $p->hasParents() && ! isset($married[$p->id]))
            ->sortBy('id')->values()->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->fullName(), 'nickname' => $p->nickname, 'birth' => Format::event($p, 'birth'),
            ]);

        return Inertia::render('People/Index', [
            'clan' => ['id' => $clan->id, 'label' => $clan->label()],
            'people' => $rows,
            'total' => $members->count(),
            'root' => $root ? ['id' => $root->id, 'name' => $root->fullName()] : null,
            'heads' => $this->membership->subclanHeads($clan)->map(fn ($h) => [
                'id' => $h->id, 'label' => 'Subclan: '.($h->subclan_name ? $h->subclan_name.' — ' : '').$h->fullName().' is Gen 0',
            ])->values(),
            'unplaced' => $unplaced,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $clan = $this->currentClan();
        if (! $clan) {
            return $this->needClan();
        }
        $parentId = $request->filled('parent') ? (int) $request->query('parent') : null;

        return Inertia::render('People/Form', $this->formProps(null, $clan, $parentId));
    }

    public function store(Request $request, PersonWriter $writer): RedirectResponse
    {
        $clan = $this->currentClan();
        abort_unless($clan, 404);
        $p = $writer->save(null, $request->all(), $clan->id);
        $this->toast('Saved '.$p->fullName().$this->placedSuffix($p));

        if ($request->boolean('again')) {
            return redirect()->route('people.create', array_filter(['parent' => $p->clanParentId()]));
        }

        return redirect()->route('people.show', $p);
    }

    /** Person detail (screen 6). DESCENT: parents, spouses and children are never clan-scoped. */
    public function show(Request $request, Person $person): Response
    {
        $clan = $this->people->clan($person->clan_id);
        if ($clan && ! $clan->trashed() && $clan->id !== $this->currentClan()?->id) {
            $this->switchClan($clan);
            session()->now('toast', 'Switched to the '.$clan->label());
        }

        $p = $person;
        $isFounder = (bool) $clan?->isFounder($p);
        $marriages = $this->family->marriagesOf($p->id);
        $married = $marriages->isNotEmpty();
        $cpId = $p->clanParentId();
        $opId = $p->otherParentId();
        $cp = $cpId ? Person::unscoped()->find($cpId) : null;
        $op = $opId ? Person::unscoped()->find($opId) : null;

        $sibCount = $p->hasParents() ? Person::unscoped()->siblingSet($p->father_id, $p->mother_id)->count() : 0;
        $parentNames = collect([$p->father_id, $p->mother_id])->filter()->map(fn ($id) => Person::unscoped()->find($id)?->fullName())->filter()->implode(' and ');

        $role = fn (Person $x) => $x->id === $p->father_id ? 'father' : 'mother';
        $rel = function (Person $x) use ($p) {
            $r = $p->relationTo($x->id);

            return $r && $r !== Relation::Biological ? $r->value : null;
        };

        // Children in sibling order, one group per sibling set (per other parent).
        $groups = [];
        foreach ($this->family->childrenOf($p->id) as $k) {
            $other = $k->father_id === $p->id ? $k->mother_id : $k->father_id;
            $key = $k->father_id.':'.$k->mother_id;
            if (! isset($groups[$key])) {
                $o = $other ? Person::unscoped()->find($other) : null;
                $groups[$key] = [
                    'key' => $key,
                    'other' => $o ? ['id' => $o->id, 'name' => $o->fullName(), 'clan_label' => $this->people->clan($o->clan_id)?->label(), 'other_clan' => $o->clan_id !== $p->clan_id] : null,
                    'hidden' => $other && $k->hide_second_parent && $k->otherParentId() === $other,
                    'kids' => [],
                ];
            }
            $groups[$key]['kids'][] = $this->people->card($k, $p->clan_id) + [
                'sibling_order' => $k->sibling_order,
                'clanLine' => $k->clanParentId() === $p->id && $k->generation !== null,
            ];
        }

        $marriageRows = $marriages->map(function (Marriage $m) use ($p) {
            $sid = $m->spouseIdOf($p->id);
            $s = $sid ? Person::unscoped()->find($sid) : null;

            return [
                'id' => $m->id,
                'spouse' => $s ? $this->people->card($s, $p->clan_id) + ['generation_stored' => $s->generation] : null,
                'when' => Format::marriageWhen($m->date_text, $m->date),
                'date' => $m->date,
                'date_text' => $m->date_text,
                'place' => $m->place,
                'status' => $m->status->value,
                'notes' => $m->notes,
                'familyPhoto' => $this->photos->primary(PhotoKind::Family, 'marriage', $m->id)['url'] ?? null,
            ];
        })->values();

        return Inertia::render('People/Show', [
            'person' => $this->people->card($p) + [
                'given_name' => $p->given_name,
                'last_name' => $p->last_name,
                'sex' => $p->sex->value,
                'generation_stored' => $p->generation,
                'occupation' => $p->occupation,
                'notes' => $p->notes,
                'parentage_note' => $p->parentage_note,
                'is_subclan_head' => $p->is_subclan_head,
                'subclan_name' => $p->subclan_name,
                'hide_second_parent' => $p->hide_second_parent,
                'sibling_order' => $p->sibling_order,
                'isFounder' => $isFounder,
                'unplaced' => ! $isFounder && ! $p->hasParents() && ! $married,
                'marriedIn' => ! $isFounder && ! $p->hasParents() && $married,
                'sibLine' => $sibCount ? 'Child '.$p->sibling_order.' of '.$sibCount.' · '.$parentNames : null,
            ],
            'clan' => $clan ? ['id' => $clan->id, 'label' => $clan->label()] : null,
            'line' => $this->people->line($this->family->lineOf($p)),
            'facts' => array_values(array_filter([
                ['Clan', Format::clanLabel($clan)],
                ['Maiden name', $p->maiden_last_name],
                ['Born', implode(' · ', array_filter([Format::event($p, 'birth'), $p->birth_place]))],
                ['Died', implode(' · ', array_filter([Format::event($p, 'death'), $p->death_place]))],
                ['Buried', $p->burial_place],
                ['Sex', $p->sex->value === 'unknown' ? '' : ucfirst($p->sex->value)],
                ['Living', $p->is_living === null ? '' : ($p->is_living ? 'Yes' : 'No')],
                ['Occupation', $p->occupation],
                ['Residence', $p->residence],
                ['Phone', $p->phone],
                ['Email', $p->email],
            ], fn ($f) => $f[1] !== null && $f[1] !== '')),
            'clanParent' => $cp ? $this->people->card($cp, $p->clan_id) + ['role' => $role($cp), 'relation' => $rel($cp)] : null,
            'otherParent' => $op ? $this->people->card($op, $p->clan_id) + [
                'role' => $role($op), 'relation' => $rel($op), 'numbered' => $op->generation !== null,
            ] : null,
            'marriages' => $marriageRows,
            'childGroups' => array_values($groups),
            'childCount' => array_sum(array_map(fn ($g) => count($g['kids']), $groups)),
            'photoPanels' => $this->photoPanels($p, $marriages),
            'inline' => $this->inlineChildrenProps($p),
            // Only when the Cousins card is opened (a partial reload): finding them walks several generations.
            'cousins' => Inertia::optional(fn () => $this->cousinProps($p, app(Cousins::class), $request->integer('through') ?: null)),
            'cousinAncestors' => Inertia::optional(fn () => $this->cousinAncestorOptions($p, app(Cousins::class))),
        ]);
    }

    public function edit(Person $person): Response
    {
        $clan = $this->people->clan($person->clan_id);
        if ($clan && ! $clan->trashed() && $clan->id !== $this->currentClan()?->id) {
            $this->switchClan($clan);
        }

        return Inertia::render('People/Form', $this->formProps($person, $clan, null));
    }

    public function update(Request $request, Person $person, PersonWriter $writer): RedirectResponse
    {
        $p = $writer->save($person, $request->all(), $person->clan_id);
        $this->toast('Saved '.$p->fullName().$this->placedSuffix($p));

        return redirect()->route('people.show', $p);
    }

    public function destroy(Person $person, PersonWriter $writer): RedirectResponse
    {
        $name = $person->fullName();
        $writer->delete($person);
        $this->toast('Deleted '.$name);

        return redirect()->route('people.index');
    }

    /** Up/down arrows on the person page: swap with a neighbour and renumber. */
    public function move(Request $request, Person $person, SiblingOrder $order): RedirectResponse
    {
        if ($order->move($person, $request->integer('dir') < 0 ? -1 : 1)) {
            $n = Person::unscoped()->siblingSet($person->father_id, $person->mother_id)->count();
            $this->toast($person->fullName().' is now child '.$person->sibling_order.' of '.$n.'.');
        }

        return back();
    }

    /** "1st cousins", in groups headed "Grandparents Teodoro Santos and Amparo Diaz · mother’s side". */
    private function cousinProps(Person $p, Cousins $cousins, ?int $throughId = null): array
    {
        return array_map(fn ($d) => [
            'degree' => $d['degree'],
            'label' => Format::ordinal($d['degree']).' cousins',
            'count' => array_sum(array_map(fn ($g) => $g['cousins']->count(), $d['groups'])),
            'groups' => array_map(fn ($g) => [
                'key' => $g['via']->pluck('id')->implode('-'),
                'label' => $this->ancestorsLabel($g['via'], $d['degree'] + 1).' · '.$g['side'].'’s side',
                'cousins' => $g['cousins']->map(fn (Person $c) => $this->people->card($c, $p->clan_id))->all(),
            ], $d['groups']),
        ], $cousins->of($p, $throughId));
    }

    /** The "Through ancestor" picker: "Great-grandfather · Juan Santos · mother’s side". */
    private function cousinAncestorOptions(Person $p, Cousins $cousins): array
    {
        return array_map(fn ($a) => [
            'id' => $a['person']->id,
            'name' => $a['person']->fullName(),
            'label' => ucfirst($this->ancestorTerm($a['up'], $a['person'])).' · '.$a['person']->fullName().' · '.$a['side'].'’s side',
        ], $cousins->ancestorsOf($p));
    }

    /** "grandparents" (a couple), or "great-grandfather" / "…mother" / "…parent" for one person. */
    private function ancestorTerm(int $up, ?Person $one): string
    {
        $term = match ($up) {
            2 => 'grand',
            3 => 'great-grand',
            4 => 'great-great-grand',
            default => ($up - 2).'× great-grand',
        };

        return $term.(! $one ? 'parents' : match ($one->sex->value) {
            'male' => 'father',
            'female' => 'mother',
            default => 'parent',
        });
    }

    /** "Grandparents A and B", "Great-grandfather A", "3× great-grandparents A and B". */
    private function ancestorsLabel($via, int $up): string
    {
        $term = $this->ancestorTerm($up, $via->count() > 1 ? null : $via->first());
        $names = $via->map->fullName()->all();
        $last = array_pop($names);

        return ucfirst($term).' '.($names ? implode(', ', $names).' and ' : '').$last;
    }

    private function placedSuffix(Person $p): string
    {
        if ($p->generation !== null) {
            return ' · Gen '.$p->generation;
        }

        return $this->family->isUnplaced($p) ? ' · unplaced' : '';
    }

    /** "married to Liza Santos (Santos clan)" names, for married-in rows. */
    private function spouseNames(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $ms = Marriage::whereIn('husband_id', $ids)->orWhereIn('wife_id', $ids)->inMarriageOrder()->get();
        $spouses = Person::unscoped()->whereIn('id', $ms->flatMap(fn ($m) => [$m->husband_id, $m->wife_id])->filter()->unique())->get()->keyBy('id');
        $out = [];
        foreach ($ms as $m) {
            foreach ([[$m->husband_id, $m->wife_id], [$m->wife_id, $m->husband_id]] as [$a, $b]) {
                if (in_array($a, $ids, true) && $b && isset($spouses[$b]) && isset($spouses[$a])) {
                    $s = $spouses[$b];
                    $out[$a][] = $s->fullName().($s->clan_id !== $spouses[$a]->clan_id ? ' ('.$this->people->clan($s->clan_id)?->label().')' : '');
                }
            }
        }

        return $out;
    }

    /** Portrait; a family picture per marriage (or the person, for a solo parent); subclan group. */
    private function photoPanels(Person $p, $marriages): array
    {
        $panels = [$this->photos->group(PhotoKind::Portrait, 'person', $p->id, 'Portrait')];
        foreach ($marriages as $m) {
            $sid = $m->spouseIdOf($p->id);
            $s = $sid ? Person::unscoped()->find($sid) : null;
            $when = Format::marriageWhen($m->date_text, $m->date);
            $panels[] = $this->photos->group(PhotoKind::Family, 'marriage', $m->id,
                'Family picture — with '.($s ? $s->fullName() : 'spouse not recorded'), $when ? 'm. '.$when : null)
                + ['clan_id' => $p->clan_id];
        }
        $kids = $this->family->childrenOf($p->id);
        $solo = $kids->contains(fn ($k) => ($k->father_id === $p->id ? $k->mother_id : $k->father_id) === null);
        $hasSoloPhotos = Photo::sameAttachment(PhotoKind::Family, 'person', $p->id)->exists();
        if ($solo || ($marriages->isEmpty() && $kids->isNotEmpty()) || $hasSoloPhotos) {
            $panels[] = $this->photos->group(PhotoKind::Family, 'person', $p->id,
                'Family picture — '.$p->fullName().' and children (no marriage recorded)');
        }
        if ($p->is_subclan_head) {
            $panels[] = $this->photos->group(PhotoKind::SubclanGroup, 'person', $p->id,
                'Subclan group photo'.($p->subclan_name ? ' — '.$p->subclan_name : ''));
        }

        return $panels;
    }

    /** What the inline add-children card on the person page needs. */
    private function inlineChildrenProps(Person $p): array
    {
        $spouses = $this->family->spousesOf($p->id);
        $existing = ['' => $this->existingIn($p, null)];
        foreach ($spouses as $s) {
            $existing[$s->id] = $this->existingIn($p, $s);
        }

        return [
            'parent' => [
                'id' => $p->id, 'name' => $p->fullName(), 'sex' => $p->sex->value, 'last_name' => $p->last_name,
                'generation' => $p->generation, 'clan_label' => $this->people->clan($p->clan_id)?->label(), 'clan_id' => $p->clan_id,
            ],
            'spouses' => $spouses->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->fullName(), 'sex' => $s->sex->value, 'last_name' => $s->last_name,
                'clan_id' => $s->clan_id, 'clan_label' => $this->people->clan($s->clan_id)?->label(),
            ])->values(),
            'defaultOther' => $spouses->last()?->id,
            'existing' => $existing,
        ];
    }

    /** How many children the (parent, other) pair already has — the set new rows follow. */
    private function existingIn(Person $parent, ?Person $other): int
    {
        $t = new Person;
        \App\Services\Couples::assignParents($t, $parent, null, $other);

        return Person::unscoped()->siblingSet($t->father_id, $t->mother_id)->count();
    }

    private function formProps(?Person $p, Clan $clan, ?int $parentId): array
    {
        $cpId = $p ? $p->clanParentId() : $parentId;
        $opId = $p?->otherParentId();
        $cpRel = $p && $cpId ? $p->relationTo($cpId) : null;
        $opRel = $p && $opId ? $p->relationTo($opId) : null;
        $isFounder = $p && $clan->isFounder($p);

        $dupes = [];
        if ($p) {
            $dupes = Person::unscoped()->where('clan_id', $p->clan_id)->whereKeyNot($p->id)->get()
                ->filter(fn ($x) => mb_strtolower($x->fullName()) === mb_strtolower($p->fullName()))
                ->map(fn ($x) => $x->fullName().($x->generation !== null ? ' (Gen '.$x->generation.')' : ''))->values()->all();
        }

        $fields = [];
        foreach (PersonWriter::TEXT_FIELDS as $k) {
            $fields[$k] = $p?->{$k} ?? '';
        }

        return [
            'isNew' => $p === null,
            'clan' => ['id' => $clan->id, 'label' => $clan->label()],
            'person' => [
                'id' => $p?->id,
                'name' => $p?->fullName(),
                'given_name' => $p?->given_name ?? '',
                ...$fields,
                'sex' => $p?->sex->value ?? 'unknown',
                'is_living' => $p === null || $p->is_living === null ? 'null' : ($p->is_living ? 'true' : 'false'),
                'hide_second_parent' => (bool) $p?->hide_second_parent,
                'is_subclan_head' => (bool) $p?->is_subclan_head,
                'clan_parent_id' => $cpId ? (string) $cpId : '',
                'other_parent_id' => $opId ? (string) $opId : '',
                'clan_relation' => $cpRel?->value ?? 'biological',
                'other_relation' => $opRel?->value ?? 'biological',
                'sibling_order' => $p && $p->hasParents() ? (string) $p->sibling_order : '',
                'generation' => $p?->generation,
            ],
            'isFounder' => $isFounder,
            'siblingCount' => $p && $p->hasParents() ? Person::unscoped()->siblingSet($p->father_id, $p->mother_id)->count() : 0,
            'clanParentOptions' => $this->people->memberOptions($clan, $p?->id),
            'otherParentGroups' => $this->people->allClanOptions($clan->id, $p?->id),
            'relations' => Relation::offered(),
            'dupes' => $dupes,
            'placement' => $p && $p->generation !== null ? $this->people->line($this->family->lineOf($p)) : null,
        ];
    }
}
