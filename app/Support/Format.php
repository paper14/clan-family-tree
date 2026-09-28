<?php

namespace App\Support;

use App\Models\Clan;
use App\Models\Person;
use Carbon\CarbonInterface;

/**
 * Display wording, ported from the prototype. Dates are shown as the record says them:
 * the as-written text first, the exact date only when there's no text. Blank is normal —
 * an empty value is returned as '' and never as "Unknown".
 */
class Format
{
    private const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** 1988-03-14 → "14 Mar 1988"; a 1 January date is treated as "year only". */
    public static function date(?string $iso): string
    {
        if (! $iso) {
            return '';
        }
        $d = explode('-', substr($iso, 0, 10));
        if (count($d) < 3) {
            return $iso;
        }
        if ($d[1] === '01' && $d[2] === '01') {
            return $d[0];
        }

        return ((int) $d[2]).' '.self::MON[(int) $d[1] - 1].' '.$d[0];
    }

    /** "27 September 2026" — the print caption date. */
    public static function longDate(CarbonInterface $d): string
    {
        return $d->format('j F Y');
    }

    public static function fullName(?Person $p): string
    {
        if (! $p) {
            return '';
        }

        return implode(' ', array_filter([$p->given_name, $p->middle_name, $p->last_name, $p->suffix], fn ($v) => $v !== null && $v !== ''));
    }

    /** The name without the last name, for charts with last names turned off: "Liza", "Andres Jr.". */
    public static function nameWithoutLast(Person $p): string
    {
        return implode(' ', array_filter([$p->given_name, $p->middle_name, $p->suffix], fn ($v) => $v !== null && $v !== ''));
    }

    /** One event as the record says it: the text, else the formatted exact date. */
    public static function event(Person $p, string $k): string
    {
        return $p->{$k.'_date_text'} ?: self::date($p->{$k.'_date'});
    }

    /** "b. abt. 1901 · d. 14 Mar 1988" */
    public static function life(Person $p): string
    {
        $b = self::event($p, 'birth');
        $d = self::event($p, 'death');

        return implode(' · ', array_filter([$b ? 'b. '.$b : '', $d ? 'd. '.$d : '']));
    }

    /** "1928 – 2004", "b. 1956", "1905 – ?" */
    public static function span(Person $p): string
    {
        $b = self::event($p, 'birth');
        $d = self::event($p, 'death');
        if (! $b && ! $d) {
            return '';
        }
        if (! $d) {
            return $p->is_living === false ? $b.' – ?' : 'b. '.$b;
        }

        return ($b ?: '?').' – '.$d;
    }

    /** "Santos clan" — but a clan already named "… clan" isn't doubled. */
    public static function clanLabel(?Clan $c): string
    {
        if (! $c) {
            return 'No clan';
        }

        return $c->name.(preg_match('/\bclan$/i', $c->name) ? '' : ' clan');
    }

    public static function initials(Person $p): string
    {
        $parts = array_slice(preg_split('/\s+/', self::fullName($p), -1, PREG_SPLIT_NO_EMPTY), 0, 2);

        return mb_strtoupper(implode('', array_map(fn ($s) => mb_substr($s, 0, 1), $parts))) ?: '?';
    }

    /** Marriage "m. 1926" text: as written, else the exact date. */
    public static function marriageWhen(?string $text, ?string $date): string
    {
        return $text ?: self::date($date);
    }

    /** 1st, 2nd, 3rd, 4th … 11th, 12th, 13th … 21st. */
    public static function ordinal(int $n): string
    {
        $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');

        return $n.$suffix;
    }
}
