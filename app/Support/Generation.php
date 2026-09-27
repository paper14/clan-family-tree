<?php

namespace App\Support;

use App\Models\Person;

/**
 * THE membership generation-display helper (planning.md §5, implementation-notes.md §2).
 * Every membership view goes through it — people list, person page, start dropdown,
 * pickers — so absolute and relative numbering can never diverge.
 *
 * The CHART generation (tree, outline, print) is a different number: depth along the path
 * walked from the starting person. See Descendants and chartGen() in the front end.
 */
class Generation
{
    /**
     * The stored generation, or — with a subclan root — stored minus the root's, so the
     * root is Gen 0. Null across clans, for people above the root, and when unnumbered.
     */
    public static function display(Person $p, ?Person $root = null): ?int
    {
        if ($p->generation === null) {
            return null;
        }
        if (! $root) {
            return $p->generation;
        }
        if ($root->generation === null || $root->clan_id !== $p->clan_id) {
            return null;
        }
        $d = $p->generation - $root->generation;

        return $d >= 0 ? $d : null;
    }
}
