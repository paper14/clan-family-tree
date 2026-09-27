<?php

namespace App\Http\Middleware;

use App\Models\Clan;
use App\Support\CurrentClan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/** The clan chosen in the header, kept in the session. Falls back to the first live clan. */
class SetCurrentClan
{
    public function __construct(private CurrentClan $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('clan_id');
        $clan = $id ? Clan::find($id) : null;

        if (! $clan && Schema::hasTable('clans')) {
            $clan = Clan::orderBy('name')->first();
            $request->session()->put('clan_id', $clan?->id);
        }

        $this->current->set($clan?->id);

        return $next($request);
    }
}
