<?php

namespace App\Services;

use App\Enums\ClanParent;
use App\Enums\Relation;
use App\Enums\Sex;
use App\Models\Person;

/** Column assignment rules: which person goes in husband_id / wife_id, father_id / mother_id. */
class Couples
{
    /**
     * Husband/wife assignment (implementation-notes.md §5), where $a is the person whose
     * page it is and $b the spouse. The person whose sex is known takes their matching
     * column; neither known → husband = a, wife = b (a consistent default).
     *
     * @return array{0: ?Person, 1: ?Person} [husband, wife]
     */
    public static function marriageColumns(?Person $a, ?Person $b): array
    {
        return match (true) {
            $a?->sex === Sex::Male => [$a, $b],
            $a?->sex === Sex::Female => [$b, $a],
            $b?->sex === Sex::Male => [$b, $a],
            $b?->sex === Sex::Female => [$a, $b],
            default => [$a, $b],
        };
    }

    /**
     * Map a clan-line parent and an optional other parent to father_id / mother_id
     * (implementation-notes.md §1), record which one is the clan line, and set the child's
     * clan from the clan-line parent. No clan-line parent → no parents at all.
     */
    public static function assignParents(Person $p, ?Person $clanParent, ?string $clanRelation = null, ?Person $other = null, ?string $otherRelation = null): void
    {
        $p->father_id = null;
        $p->mother_id = null;
        $p->clan_parent = null;
        $p->father_relation = Relation::Biological;
        $p->mother_relation = Relation::Biological;

        if (! $clanParent) {
            return;
        }

        $cpIsMother = $clanParent->sex === Sex::Female
            || (in_array($clanParent->sex, [Sex::Unknown, null], true) && $other?->sex === Sex::Male);

        $cRel = Relation::tryFrom((string) $clanRelation) ?? Relation::Biological;
        $oRel = Relation::tryFrom((string) $otherRelation) ?? Relation::Biological;

        if ($cpIsMother) {
            $p->mother_id = $clanParent->id;
            $p->mother_relation = $cRel;
            $p->clan_parent = ClanParent::Mother;
            if ($other) {
                $p->father_id = $other->id;
                $p->father_relation = $oRel;
            }
        } else {
            $p->father_id = $clanParent->id;
            $p->father_relation = $cRel;
            $p->clan_parent = ClanParent::Father;
            if ($other) {
                $p->mother_id = $other->id;
                $p->mother_relation = $oRel;
            }
        }

        // A child belongs to the clan-line parent's clan (planning.md §2.7, §2.8).
        $p->clan_id = $clanParent->clan_id;
    }
}
