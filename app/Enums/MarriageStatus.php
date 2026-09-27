<?php

namespace App\Enums;

enum MarriageStatus: string
{
    case Married = 'married';
    case Separated = 'separated';
    case Widowed = 'widowed';
    case Unknown = 'unknown';
}
