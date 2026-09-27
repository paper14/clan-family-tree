<?php

namespace App\Services;

use App\Enums\Relation;
use App\Enums\Sex;
use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\Photo;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saving people. Only clan_id and given_name are required (planning.md §2.9). The hard
 * stops — the only things that reject a save — are:
 *   - a blank given name;
 *   - a person who would be their own ancestor;
 *   - a clan-line parent in another clan;
 *   - an other parent with no clan-line parent.
 * Everything else is a warning at most.
 */
class PersonWriter
{
    public const TEXT_FIELDS = [
        'middle_name', 'last_name', 'maiden_last_name', 'suffix', 'nickname',
        'birth_date', 'birth_date_text', 'birth_place', 'death_date', 'death_date_text', 'death_place', 'burial_place',
        'occupation', 'residence', 'phone', 'email', 'notes', 'parentage_note', 'subclan_name',
    ];

    public function __construct(
        private Family $family,
        private SiblingOrder $order,
        private Generations $generations,
        private PhotoService $photos,
    ) {}

    /** Create ($person null) or update a person from the person form. */
    public function save(?Person $person, array $in, int $clanId): Person
    {
        $given = trim((string) ($in['given_name'] ?? ''));
        if ($given === '') {
            throw ValidationException::withMessages(['given_name' => 'a given name is needed. It’s the only required field.']);
        }
        foreach (['birth_date', 'death_date'] as $k) {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v !== '' && ! $this->isDate($v)) {
                throw ValidationException::withMessages([$k => 'that exact date isn’t a real date. Leave it blank and use “As written in the record”.']);
            }
        }

        $cp = ! empty($in['clan_parent_id']) ? Person::unscoped()->find((int) $in['clan_parent_id']) : null;
        $op = ! empty($in['other_parent_id']) ? Person::unscoped()->find((int) $in['other_parent_id']) : null;
        $memberClan = $person ? $person->clan_id : $clanId;

        $this->checkParents($person?->id, $given, $memberClan, $cp, $op);

        return DB::transaction(function () use ($person, $in, $given, $memberClan, $cp, $op) {
            $p = $person ?? new Person(['clan_id' => $memberClan]);
            $oldF = $p->father_id;
            $oldM = $p->mother_id;
            $isNew = ! $p->exists;

            foreach (self::TEXT_FIELDS as $k) {
                $v = trim((string) ($in[$k] ?? ''));
                $p->{$k} = $v === '' ? null : $v;
            }
            $p->given_name = $given;
            $p->sex = Sex::tryFrom((string) ($in['sex'] ?? '')) ?? Sex::Unknown;
            $living = $in['is_living'] ?? null;
            $p->is_living = $living === true || $living === 'true' || $living === '1' ? true
                : ($living === false || $living === 'false' || $living === '0' ? false : null);
            $p->hide_second_parent = filter_var($in['hide_second_parent'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $p->is_subclan_head = filter_var($in['is_subclan_head'] ?? false, FILTER_VALIDATE_BOOLEAN);

            Couples::assignParents($p, $cp, $in['clan_relation'] ?? null, $op, $in['other_relation'] ?? null);
            $setChanged = $isNew || $oldF !== $p->father_id || $oldM !== $p->mother_id;
            if ($isNew) {
                $p->sibling_order = 1;
            }
            $p->save();

            $req = (int) ($in['sibling_order'] ?? 0);
            if (! $p->hasParents()) {
                $this->order->place($p);
            } elseif ($setChanged) {
                $this->order->place($p, $req > 0 ? $req : null);
            } elseif ($req > 0 && $req !== $p->sibling_order) {
                $this->order->place($p, $req);
            }
            if (! $isNew && ($oldF !== $p->father_id || $oldM !== $p->mother_id)) {
                $this->order->normalize($oldF, $oldM);
            }

            // Only this person and their clan-line descendants — never the whole clan.
            $this->generations->updateSubtree($p);

            return $p->refresh();
        });
    }

    /** The hard stops on parent links (implementation-notes.md §1). */
    public function checkParents(?int $personId, string $given, int $clanId, ?Person $cp, ?Person $op): void
    {
        if ($cp && $op && $cp->id === $op->id) {
            throw ValidationException::withMessages(['other_parent_id' => 'the clan-line parent and the other parent are the same person.']);
        }
        if ($cp && $cp->clan_id !== $clanId) {
            $clan = Clan::withTrashed()->find($cp->clan_id);
            throw ValidationException::withMessages(['clan_parent_id' => 'a parent link can’t cross clans. '.$cp->fullName().' is in the '.Format::clanLabel($clan).'.']);
        }
        if (! $cp && $op) {
            throw ValidationException::withMessages(['clan_parent_id' => 'pick the clan-line parent first. The other parent alone doesn’t place anyone in the tree.']);
        }
        foreach (['clan_parent_id' => $cp, 'other_parent_id' => $op] as $field => $parent) {
            if ($parent && $this->family->wouldCycle($personId, $parent->id)) {
                throw ValidationException::withMessages([$field => $given.' can’t be their own ancestor — '.$parent->fullName().' descends from them. Pick a different parent.']);
            }
        }
    }

    /**
     * Inline / screen add-children (implementation-notes.md §4). Row order is sibling order,
     * after any children already recorded. A row needs a given name or a nickname; empty rows
     * are skipped. A nickname-only child is saved with the nickname as the given name.
     *
     * @return Person[]
     */
    public function addChildren(Person $clanParent, ?Person $other, array $rows): array
    {
        if ($other && $other->id === $clanParent->id) {
            throw ValidationException::withMessages(['other_parent_id' => 'the clan-line parent and the other parent are the same person.']);
        }
        $rows = array_values(array_filter($rows, fn ($r) => trim((string) ($r['given_name'] ?? '')) !== '' || trim((string) ($r['nickname'] ?? '')) !== ''));
        if (! $rows) {
            throw ValidationException::withMessages(['rows' => 'Type at least one name or nickname.']);
        }
        foreach ($rows as $i => $r) {
            $d = trim((string) ($r['birth_date'] ?? ''));
            if ($d !== '' && ! $this->isDate($d)) {
                throw ValidationException::withMessages(['rows' => 'Row '.($i + 1).': that exact date isn’t a real date. Leave it blank and use “Born, as written”.']);
            }
        }

        return DB::transaction(function () use ($clanParent, $other, $rows) {
            $created = [];
            foreach ($rows as $r) {
                $p = new Person;
                foreach (['given_name', 'middle_name', 'last_name', 'nickname', 'birth_date_text', 'birth_date'] as $k) {
                    $v = trim((string) ($r[$k] ?? ''));
                    $p->{$k} = $v === '' ? null : $v;
                }
                if ($p->given_name === null) {
                    // A nickname alone stands in as the name — avoids "Bunso “Bunso”".
                    $p->given_name = $p->nickname;
                    $p->nickname = null;
                    $p->notes = 'Known only by this nickname.';
                }
                $p->sex = Sex::tryFrom((string) ($r['sex'] ?? '')) ?? Sex::Unknown;
                Couples::assignParents($p, $clanParent, $r['rel'] ?? Relation::Biological->value, $other, Relation::Biological->value);
                $p->sibling_order = $this->order->next($p->father_id, $p->mother_id);
                $p->save();
                $this->generations->updateSubtree($p);
                $created[] = $p;
            }

            return $created;
        });
    }

    /**
     * Soft delete (the row is kept, out of view). Children stay in the registry but lose this
     * parent link; the person's own sibling set and the children's sets are renumbered;
     * their marriages and all their photos (and their marriages' family pictures) go.
     */
    public function delete(Person $p): void
    {
        DB::transaction(function () use ($p) {
            $kids = $this->family->childrenOf($p->id, true);
            $pf = $p->father_id;
            $pm = $p->mother_id;

            Person::unscoped()->where('father_id', $p->id)->update(['father_id' => null]);
            Person::unscoped()->where('mother_id', $p->id)->update(['mother_id' => null]);

            $marriageIds = Marriage::of($p->id)->pluck('id');
            foreach (Photo::where('person_id', $p->id)->orWhereIn('marriage_id', $marriageIds)->get() as $ph) {
                $this->photos->deleteFile($ph);
                $ph->delete();
            }
            Marriage::whereIn('id', $marriageIds)->delete();
            Clan::withTrashed()->where('founder_id', $p->id)->update(['founder_id' => null]);
            Clan::withTrashed()->where('founder_spouse_id', $p->id)->update(['founder_spouse_id' => null]);

            $p->delete();
            $this->order->normalize($pf, $pm);

            $done = [];
            foreach ($kids as $k) {
                $k->refresh();
                $key = $k->father_id.':'.$k->mother_id;
                if (! isset($done[$key])) {
                    $done[$key] = true;
                    $this->order->normalize($k->father_id, $k->mother_id);
                }
                $k->refresh();
                $this->generations->updateSubtree($k);
            }
        });
    }

    private function isDate(string $v): bool
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $v);

        return $d !== false && $d->format('Y-m-d') === $v;
    }
}
