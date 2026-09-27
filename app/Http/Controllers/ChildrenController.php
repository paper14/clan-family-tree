<?php

namespace App\Http\Controllers;

use App\Http\Presenters\People;
use App\Models\Person;
use App\Services\Couples;
use App\Services\Family;
use App\Services\PersonWriter;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Add children (screen 7): a whole family in one pass, row order = sibling order. */
class ChildrenController extends Controller
{
    public function __construct(private People $people, private Family $family) {}

    public function create(Request $request): Response
    {
        $clan = $this->currentClan();
        if (! $clan) {
            return $this->needClan();
        }

        $parent = null;
        if ($request->filled('parent')) {
            $parent = Person::unscoped()->where('clan_id', $clan->id)->whereNotNull('generation')->find((int) $request->query('parent'));
        }

        $spouses = $parent ? $this->family->spousesOf($parent->id) : collect();
        $marriages = $parent ? $this->family->marriagesOf($parent->id) : collect();
        $existing = [];
        if ($parent) {
            $existing[''] = $this->existing($parent, null);
            foreach ($spouses as $s) {
                $existing[$s->id] = $this->existing($parent, $s);
            }
        }

        return Inertia::render('Children/Create', [
            'clan' => ['id' => $clan->id, 'label' => $clan->label()],
            'parentOptions' => $this->people->memberOptions($clan, null, fn (Person $p) => $p->generation !== null),
            'parent' => $parent ? $this->people->card($parent) + [
                'sex' => $parent->sex->value, 'last_name' => $parent->last_name, 'generation_stored' => $parent->generation,
            ] : null,
            'spouses' => $spouses->map(function ($s) use ($parent, $marriages) {
                $m = $marriages->first(fn ($m) => $m->spouseIdOf($parent->id) === $s->id);
                $when = $m ? Format::marriageWhen($m->date_text, $m->date) : '';

                return [
                    'id' => $s->id, 'name' => $s->fullName(), 'sex' => $s->sex->value, 'last_name' => $s->last_name,
                    'clan_id' => $s->clan_id, 'clan_label' => $this->people->clan($s->clan_id)?->label(),
                    'married' => $when,
                ];
            })->values(),
            'defaultOther' => $spouses->last()?->id,
            'existing' => $existing,
            'relations' => \App\Enums\Relation::offered(),
        ]);
    }

    public function store(Request $request, Person $person, PersonWriter $writer): RedirectResponse
    {
        $other = $request->filled('other_parent_id') ? Person::unscoped()->findOrFail((int) $request->input('other_parent_id')) : null;
        $created = $writer->addChildren($person, $other, (array) $request->input('rows', []));
        $n = count($created);
        $this->toast('Saved '.$n.($n === 1 ? ' child' : ' children').' of '.$person->fullName());

        return redirect()->route('people.show', $person);
    }

    private function existing(Person $parent, ?Person $other): int
    {
        $t = new Person;
        Couples::assignParents($t, $parent, null, $other);

        return Person::unscoped()->siblingSet($t->father_id, $t->mother_id)->count();
    }
}
