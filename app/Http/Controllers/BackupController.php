<?php

namespace App\Http\Controllers;

use App\Services\Backup\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Backup and restore (architecture-local.md §4). */
class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index(): Response
    {
        return Inertia::render('Backups/Index', [
            'folder' => config('clan.backup.path'),
            'backups' => $this->backups->list(),
            'keepAuto' => config('clan.backup.keep_auto'),
        ]);
    }

    public function store(): RedirectResponse
    {
        try {
            $file = $this->backups->create();
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['backup' => $e->getMessage()]);
        }
        $m = $this->backups->manifest($file);
        $this->toast('Backup of all clans'.($m['photos'] ?? 0 ? ' and '.$m['photos'].' photos' : '').' written to '.basename($file).' — keep a copy off this computer too.');

        return back();
    }

    /** Restore: a safety backup of the current state first, then the chosen file. */
    public function restore(Request $request): RedirectResponse
    {
        try {
            $result = $this->backups->restore($this->backups->resolve((string) $request->input('file')));
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['restore' => $e->getMessage()]);
        }
        session()->forget('clan_id');
        $this->toast('Restored '.$result['people'].' people in '.$result['clans'].' clans. A safety backup of the previous state was taken first ('.$result['safety_backup'].').'
            .($result['pending_migrations'] ? ' This backup is from an older version: run php artisan app:migrate.' : ''));

        return redirect()->route('clans.index');
    }
}
