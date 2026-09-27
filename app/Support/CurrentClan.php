<?php

namespace App\Support;

/**
 * The clan picked in the header (planning.md §3, screen 3). It scopes membership views only.
 * Set from the session by the SetCurrentClan middleware; null in console and tests unless set.
 */
class CurrentClan
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function set(?int $id): void
    {
        $this->id = $id;
    }
}
