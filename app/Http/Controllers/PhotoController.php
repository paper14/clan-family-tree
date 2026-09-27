<?php

namespace App\Http\Controllers;

use App\Enums\PhotoKind;
use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Photos of all four kinds (planning.md §2.11). Web-sized copies only. */
class PhotoController extends Controller
{
    public function __construct(private PhotoService $photos) {}

    public function store(Request $request): RedirectResponse
    {
        $kind = PhotoKind::tryFrom((string) $request->input('kind'));
        $type = (string) $request->input('type');
        $id = (int) $request->input('id');
        if (! $kind || ! in_array($type, ['person', 'marriage', 'clan'], true)) {
            abort(422);
        }
        if (! $request->hasFile('file')) {
            throw ValidationException::withMessages(['file' => 'Choose an image first.']);
        }
        if (! $request->file('file')->isValid()) {
            throw ValidationException::withMessages(['file' => 'That image didn’t upload. It may be larger than the server allows.']);
        }

        // The photo's clan: the person's, the page person's for a marriage, or the clan itself.
        $clanId = match ($type) {
            'person' => Person::unscoped()->findOrFail($id)->clan_id,
            'marriage' => $request->filled('clan_id') ? (int) $request->input('clan_id')
                : Person::unscoped()->find(Marriage::findOrFail($id)->husband_id ?? Marriage::findOrFail($id)->wife_id)?->clan_id,
            'clan' => Clan::findOrFail($id)->id,
        };

        $first = ! Photo::sameAttachment($kind, $type, $id)->exists();
        $photo = $this->photos->store($request->file('file'), $kind, $type, $id, $clanId, $request->input('caption'), $request->input('year'));
        $this->toast($kind->label().' saved'.($first ? ' as the main picture.' : '.').($photo->year ? '' : ' Add the year if anyone remembers it.'));

        return back();
    }

    public function update(Request $request, Photo $photo): RedirectResponse
    {
        $this->photos->update($photo, $request->input('caption'), $request->input('year'));

        return back();
    }

    public function primary(Photo $photo): RedirectResponse
    {
        $this->photos->makePrimary($photo);
        $this->toast('Main picture changed.');

        return back();
    }

    public function destroy(Photo $photo): RedirectResponse
    {
        $this->photos->delete($photo);
        $this->toast('Photo deleted.');

        return back();
    }
}
