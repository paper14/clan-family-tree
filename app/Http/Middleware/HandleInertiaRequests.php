<?php

namespace App\Http\Middleware;

use App\Models\Clan;
use App\Models\Person;
use App\Services\Backup\BackupService;
use App\Services\Membership;
use App\Support\CurrentClan;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** The sidebar's data, the flash toast, and whether this is the demo database. */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => fn () => $this->shell(),
            'flash' => fn () => [
                'toast' => $request->session()->get('toast'),
                'toastId' => $request->session()->get('toast') ? uniqid() : null,
            ],
        ];
    }

    private function shell(): array
    {
        $current = app(CurrentClan::class)->id();
        $clans = Clan::orderBy('name')->get();
        $cur = $clans->firstWhere('id', $current);

        return [
            'clans' => $clans->map(fn (Clan $c) => ['id' => $c->id, 'name' => $c->name, 'label' => $c->label()])->values(),
            'currentClan' => $cur ? [
                'id' => $cur->id,
                'name' => $cur->name,
                'label' => $cur->label(),
                'unplaced' => app(Membership::class)->unplacedCount($cur),
            ] : null,
            'totals' => [
                'clans' => $clans->count(),
                'people' => Person::unscoped()->count(),
            ],
            'lastBackup' => app(BackupService::class)->lastBackupAt(),
            'isRegistry' => (bool) config('clan.is_registry'),
            'database' => config('clan.database'),
        ];
    }
}
