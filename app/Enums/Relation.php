<?php

namespace App\Enums;

enum Relation: string
{
    case Biological = 'biological';
    case Adopted = 'adopted';
    case Step = 'step';
    case Foster = 'foster';
    case Unknown = 'unknown';

    /** The choices offered on forms (the prototype's four; `unknown` is accepted but not offered). */
    public static function offered(): array
    {
        return ['biological', 'adopted', 'step', 'foster'];
    }
}
