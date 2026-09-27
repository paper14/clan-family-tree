<?php

namespace App\Http\Controllers;

use App\Enums\PhotoKind;
use App\Http\Presenters\People;
use App\Http\Presenters\Photos;
use App\Models\Person;
use App\Services\Descendants;
use App\Services\Family;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tree / Outline (screen 9): one page, one menu item. The view, start, depth, numbering,
 * dates, photos and collapsed branches live in React; the descendants payload is fetched
 * once per start or depth change.
 */
class TreeController extends Controller
{
    public function __construct(private People $people) {}

    public function index(Request $request): Response
    {
        $start = $request->filled('start') ? Person::unscoped()->find((int) $request->query('start')) : null;
        // Show in tree switches clan if needed (implementation-notes.md §6.2).
        if ($start && ($c = $this->people->clan($start->clan_id)) && ! $c->trashed() && $c->id !== $this->currentClan()?->id) {
            $this->switchClan($c);
            session()->now('toast', 'Switched to the '.$c->label().' — showing '.$start->fullName().'’s family.');
        }
        $clan = $this->currentClan();
        if (! $clan) {
            return $this->needClan();
        }

        return Inertia::render('Tree/Index', [
            'clan' => $this->people->clanInfo($clan),
            'startOptions' => $this->people->startOptions($clan),
            'defaultStart' => $this->people->defaultStart($clan),
            'requestedStart' => $start && $start->clan_id === $clan->id && $start->generation !== null ? $start->id : null,
        ]);
    }

    /** The one descendants query, as JSON. Also used by the print preview. */
    public function data(Request $request, Descendants $descendants, Family $family, Photos $photos): JsonResponse
    {
        return response()->json(self::payload($request, $descendants, $family, $photos, $this->people));
    }

    /** Shared with PrintController::sheet so print uses exactly the same query. */
    public static function payload(Request $request, Descendants $descendants, Family $family, Photos $photos, People $people): array
    {
        $start = Person::unscoped()->findOrFail((int) $request->query('start'));
        $collapsed = array_filter(array_map('intval', explode(',', (string) $request->query('collapsed', ''))));
        $tree = $descendants->build($start->id, (int) $request->query('depth', 3), $collapsed, $request->boolean('hidden'));
        $clan = $people->clan($start->clan_id);
        $line = $family->lineOf($start);

        $tree ??= ['root' => null, 'rootPerson' => ['id' => $start->id, 'clan_id' => $start->clan_id, 'generation' => $start->generation], 'people' => [], 'search' => [], 'familyPhotos' => []];

        return $tree + [
            'clan' => $clan ? $people->clanInfo($clan) : null,
            'start' => [
                'id' => $start->id,
                'name' => $start->fullName(),
                'is_subclan_head' => $start->is_subclan_head,
                'subclan_name' => $start->subclan_name,
                'is_founder' => (bool) $clan?->isFounder($start),
                'generation' => $start->generation,
            ],
            'ancestors' => $people->line($line->slice(0, -1)->values()),
            'headPhotos' => [
                'clan' => $clan ? $photos->primary(PhotoKind::ClanGroup, 'clan', $clan->id) : null,
                'subclan' => $start->is_subclan_head ? $photos->primary(PhotoKind::SubclanGroup, 'person', $start->id) : null,
            ],
        ];
    }
}
