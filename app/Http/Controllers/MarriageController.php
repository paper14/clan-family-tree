<?php

namespace App\Http\Controllers;

use App\Http\Presenters\People;
use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use App\Services\Family;
use App\Services\MarriageWriter;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Inline add marriage (screen 8): search every clan first, create second. */
class MarriageController extends Controller
{
    public function __construct(private People $people, private Family $family) {}

    /**
     * The spouse search: from 2 characters, every word must match the name or nickname,
     * across EVERY live clan (deliberately unscoped), up to 8 results. Accents and case are
     * ignored by the utf8mb4_0900_ai_ci collation, so "Pena" finds "Peña".
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $personId = (int) $request->query('person');
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        $live = Clan::pluck('id');

        $query = Person::unscoped()->whereIn('clan_id', $live)->whereKeyNot($personId);
        foreach ($tokens as $t) {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $query->whereRaw("CONCAT_WS(' ', given_name, middle_name, last_name, suffix, nickname) LIKE ?", [$like]);
        }
        $hits = $query->orderByRaw('generation IS NULL')->orderBy('generation')->orderBy('given_name')->limit(8)->get();

        $already = $personId ? $this->family->spousesOf($personId)->pluck('id')->all() : [];

        return response()->json($hits->map(function (Person $x) use ($already) {
            $spouses = $this->family->spousesOf($x->id);
            $marriedIn = $this->family->isMarriedIn($x);

            return [
                'id' => $x->id,
                'name' => $x->fullName(),
                'nickname' => $x->nickname,
                'clan_label' => $this->people->clan($x->clan_id)?->label(),
                'clan_id' => $x->clan_id,
                'generation' => $x->generation,
                'marriedIn' => $marriedIn,
                'span' => Format::span($x),
                'spouses' => $spouses->map->fullName()->implode(', '),
                'already' => in_array($x->id, $already, true),
            ];
        })->values());
    }

    public function store(Request $request, Person $person, MarriageWriter $writer): RedirectResponse
    {
        [, $spouse] = $writer->save($person, null, $request->all());
        $this->toastSaved($person, $spouse);

        return back();
    }

    public function update(Request $request, Marriage $marriage, MarriageWriter $writer): RedirectResponse
    {
        $person = Person::unscoped()->findOrFail((int) $request->input('person_id'));
        abort_unless(in_array($person->id, [$marriage->husband_id, $marriage->wife_id], true), 404);
        [, $spouse] = $writer->save($person, $marriage, $request->all());
        $this->toastSaved($person, $spouse);

        return back();
    }

    /** Removing a marriage deletes its family pictures; the people stay. */
    public function destroy(Marriage $marriage, MarriageWriter $writer): RedirectResponse
    {
        $writer->delete($marriage);
        $this->toast('Marriage removed.');

        return back();
    }

    private function toastSaved(Person $a, ?Person $b): void
    {
        if ($b && $b->clan_id !== $a->clan_id) {
            $this->toast('Marriage saved — it links the '.$this->people->clan($a->clan_id)?->label().' and the '.$this->people->clan($b->clan_id)?->label().'.');
        } else {
            $this->toast('Marriage saved.');
        }
    }
}
