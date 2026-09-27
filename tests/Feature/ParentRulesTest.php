<?php

namespace Tests\Feature;

use App\Enums\ClanParent;
use App\Enums\Sex;
use App\Models\Person;
use App\Services\Couples;
use App\Services\PersonWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The hard stops (planning.md §2.12) — and that nothing else blocks a save. */
class ParentRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSample();
    }

    private function saveError(?Person $p, array $in, int $clanId): array
    {
        try {
            app(PersonWriter::class)->save($p, $in, $clanId);
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Expected the save to be refused.');
    }

    public function test_only_the_given_name_is_required(): void
    {
        $santos = $this->clan('Santos');
        $errors = $this->saveError(null, ['given_name' => '   '], $santos->id);
        $this->assertSame(['a given name is needed. It’s the only required field.'], $errors['given_name']);

        // A founder known by one name, with nothing else, is a complete record.
        $p = app(PersonWriter::class)->save(null, ['given_name' => 'Isko'], $santos->id);
        $this->assertNull($p->last_name);
        $this->assertNull($p->is_living);
        $this->assertSame(Sex::Unknown, $p->sex);
    }

    public function test_a_person_cannot_be_their_own_ancestor(): void
    {
        $juan = $this->person('Juan');
        $teo = $this->person('Teodoro');

        $errors = $this->saveError($juan, ['given_name' => 'Juan', 'clan_parent_id' => $teo->id], $juan->clan_id);
        $this->assertSame(['Juan can’t be their own ancestor — Teodoro Santos descends from them. Pick a different parent.'], $errors['clan_parent_id']);
    }

    public function test_the_clan_line_parent_must_be_in_the_same_clan(): void
    {
        $nicolas = $this->person('Nicolas');
        $benjie = $this->person('Benjamin');

        $errors = $this->saveError($nicolas, ['given_name' => 'Nicolas', 'clan_parent_id' => $benjie->id], $nicolas->clan_id);
        $this->assertSame(['a parent link can’t cross clans. Benjamin Dulnuan is in the Dulnuan clan.'], $errors['clan_parent_id']);
    }

    public function test_the_other_parent_may_be_in_another_clan(): void
    {
        $nicolas = $this->person('Nicolas');
        $lucia = $this->person('Lucia');
        $benjie = $this->person('Benjamin'); // Dulnuan

        // Clan-line parent Lucia (Santos, female), other parent Benjie (Dulnuan): allowed.
        $p = app(PersonWriter::class)->save($nicolas, ['given_name' => 'Nicolas', 'clan_parent_id' => $lucia->id, 'other_parent_id' => $benjie->id], $nicolas->clan_id);
        $this->assertSame($lucia->id, $p->mother_id);
        $this->assertSame($benjie->id, $p->father_id);
        $this->assertSame(ClanParent::Mother, $p->clan_parent);
        $this->assertSame($lucia->clan_id, $p->clan_id);
    }

    public function test_an_other_parent_with_no_clan_line_parent_is_refused(): void
    {
        $nicolas = $this->person('Nicolas');
        $errors = $this->saveError($nicolas, ['given_name' => 'Nicolas', 'other_parent_id' => $this->person('Lucia')->id], $nicolas->clan_id);
        $this->assertSame(['pick the clan-line parent first. The other parent alone doesn’t place anyone in the tree.'], $errors['clan_parent_id']);
    }

    public function test_a_female_clan_line_parent_goes_in_mother_id(): void
    {
        $p = new Person;
        Couples::assignParents($p, $this->person('Rosa'), 'biological', $this->person('Ignacio'), 'biological');
        $this->assertSame([$this->person('Ignacio')->id, $this->person('Rosa')->id, ClanParent::Mother], [$p->father_id, $p->mother_id, $p->clan_parent]);

        // Unknown-sex clan-line parent with a male other parent → the clan-line parent is the mother.
        $u = Person::unscoped()->create(['clan_id' => $this->clan('Santos')->id, 'given_name' => 'U', 'sibling_order' => 1])->refresh();
        $p = new Person;
        Couples::assignParents($p, $u, null, $this->person('Ignacio'));
        $this->assertSame(ClanParent::Mother, $p->clan_parent);
    }

    public function test_husband_and_wife_columns_follow_the_known_sex(): void
    {
        $male = new Person(['sex' => 'male']);
        $female = new Person(['sex' => 'female']);
        $unknownA = new Person(['sex' => 'unknown']);
        $unknownB = new Person(['sex' => 'unknown']);

        $this->assertSame([$male, $unknownB], Couples::marriageColumns($male, $unknownB), 'a male → husband = a');
        $this->assertSame([$unknownB, $female], Couples::marriageColumns($female, $unknownB), 'a female → wife = a');
        $this->assertSame([$male, $unknownA], Couples::marriageColumns($unknownA, $male), 'b male → husband = b');
        $this->assertSame([$unknownA, $female], Couples::marriageColumns($unknownA, $female), 'b female → wife = b');
        $this->assertSame([$unknownA, $unknownB], Couples::marriageColumns($unknownA, $unknownB), 'neither → husband = a (consistent default)');
        $this->assertSame([$male, null], Couples::marriageColumns($male, null), 'spouse not recorded');
    }
}
