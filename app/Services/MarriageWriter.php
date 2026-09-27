<?php

namespace App\Services;

use App\Enums\MarriageStatus;
use App\Enums\Sex;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\Photo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Add / edit / remove a marriage from a person's page (planning.md §3 screen 8,
 * implementation-notes.md §5). Search first, create second: an existing person from ANY
 * clan is linked rather than retyped. A spouse may be "not recorded" (NULL).
 */
class MarriageWriter
{
    public function __construct(private SiblingOrder $order, private PhotoService $photos) {}

    /**
     * @param  array  $in  spouse_id | new_given/new_nick/new_last/new_sex | neither;
     *                     date, date_text, place, status, notes
     * @return array{0: Marriage, 1: ?Person} the marriage and the spouse
     */
    public function save(Person $a, ?Marriage $marriage, array $in): array
    {
        $date = trim((string) ($in['date'] ?? ''));
        if ($date !== '' && ! (($d = \DateTime::createFromFormat('!Y-m-d', $date)) && $d->format('Y-m-d') === $date)) {
            throw ValidationException::withMessages(['date' => 'that exact date isn’t a real date. Leave it blank and use “Date, as written”.']);
        }
        $creating = filter_var($in['create'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($creating && trim((string) ($in['new_given'] ?? '')) === '' && trim((string) ($in['new_nick'] ?? '')) === '') {
            throw ValidationException::withMessages(['new_given' => 'Type a given name or a nickname for the new spouse.']);
        }
        if (! $creating && ! empty($in['spouse_id']) && (int) $in['spouse_id'] === $a->id) {
            throw ValidationException::withMessages(['spouse_id' => 'a person can’t be married to themselves.']);
        }

        return DB::transaction(function () use ($a, $marriage, $in, $creating, $date) {
            $b = null;
            if ($creating) {
                // The new spouse joins the clan of the person whose page this is. No sex is assumed.
                $g = trim((string) ($in['new_given'] ?? ''));
                $nk = trim((string) ($in['new_nick'] ?? ''));
                $b = Person::unscoped()->create([
                    'clan_id' => $a->clan_id,
                    'given_name' => $g !== '' ? $g : $nk,
                    'nickname' => $g !== '' && $nk !== '' ? $nk : null,
                    'notes' => $g !== '' ? null : 'Known only by this nickname.',
                    'last_name' => trim((string) ($in['new_last'] ?? '')) ?: null,
                    'sex' => Sex::tryFrom((string) ($in['new_sex'] ?? '')) ?? Sex::Unknown,
                    'sibling_order' => 1,
                ]);
            } elseif (! empty($in['spouse_id'])) {
                $b = Person::unscoped()->findOrFail((int) $in['spouse_id']);
            }

            [$h, $w] = Couples::marriageColumns($a, $b);
            $data = [
                'husband_id' => $h?->id,
                'wife_id' => $w?->id,
                'date' => $date ?: null,
                'date_text' => trim((string) ($in['date_text'] ?? '')) ?: null,
                'place' => trim((string) ($in['place'] ?? '')) ?: null,
                'status' => MarriageStatus::tryFrom((string) ($in['status'] ?? '')) ?? MarriageStatus::Married,
                'notes' => trim((string) ($in['notes'] ?? '')) ?: null,
            ];
            if ($marriage) {
                $marriage->update($data);
            } else {
                $marriage = Marriage::create($data);
            }

            return [$marriage, $b];
        });
    }

    /** Removing a marriage deletes its family pictures; the people stay. */
    public function delete(Marriage $m): void
    {
        DB::transaction(function () use ($m) {
            foreach (Photo::where('marriage_id', $m->id)->get() as $ph) {
                $this->photos->deleteFile($ph);
                $ph->delete();
            }
            $m->delete();
        });
    }
}
