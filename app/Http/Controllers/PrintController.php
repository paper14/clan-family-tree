<?php

namespace App\Http\Controllers;

use App\Http\Presenters\People;
use App\Http\Presenters\Photos;
use App\Services\Descendants;
use App\Services\Family;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Print (screen 10). Browser print with CSS @page at the chosen sheet size, from Chrome or
 * Edge (architecture-local.md §6). It reuses the Tree / Outline descendants query — there is
 * deliberately no second query for print.
 */
class PrintController extends Controller
{
    public function __construct(private People $people) {}

    /** Options and a live preview. */
    public function index(): Response
    {
        $clan = $this->currentClan();
        if (! $clan) {
            return $this->needClan();
        }

        return Inertia::render('Print/Index', [
            'clan' => $this->people->clanInfo($clan),
            'startOptions' => $this->people->startOptions($clan),
            'defaultStart' => $this->people->defaultStart($clan),
        ]);
    }

    /** The print route: just the sheet, data-theme="print", @page at the sheet size. */
    public function sheet(Request $request, Descendants $descendants, Family $family, Photos $photos): Response
    {
        return Inertia::render('Print/Sheet', [
            'data' => TreeController::payload($request, $descendants, $family, $photos, $this->people),
            'options' => $request->only(['format', 'numbering', 'depth', 'w', 'h', 'fit', 'dates', 'photos', 'headphoto', 'redact', 'hidden', 'collapsed', 'start', 'auto']),
        ]);
    }
}
