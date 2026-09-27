<?php

namespace App\Enums;

/** Which parent is the clan line (data-model.md reconciliation 1). Stored, never worked out from sex. */
enum ClanParent: string
{
    case Father = 'father';
    case Mother = 'mother';
}
