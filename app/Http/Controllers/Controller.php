<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Support\CurrentClan;
use Inertia\Inertia;
use Inertia\Response;

abstract class Controller
{
    /** The clan picked in the header, or null. */
    protected function currentClan(): ?Clan
    {
        $id = app(CurrentClan::class)->id();

        return $id ? Clan::find($id) : null;
    }

    /** Switch the header's clan (e.g. when opening a person from another clan). */
    protected function switchClan(Clan $clan): void
    {
        session(['clan_id' => $clan->id]);
        app(CurrentClan::class)->set($clan->id);
    }

    protected function toast(string $message): void
    {
        session()->flash('toast', $message);
    }

    /** Screens scoped to one clan, when no clan exists yet. */
    protected function needClan(): Response
    {
        return Inertia::render('NeedClan');
    }
}
