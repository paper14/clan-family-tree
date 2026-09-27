<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Services\PersonWriter;
use App\Services\SiblingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** planning.md §2.6: dense 1…n per (father_id, mother_id), renumbered on every change. Never dates. */
class SiblingOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSample();
    }

    private function names(?int $f, ?int $m): array
    {
        return app(SiblingOrder::class)->members($f, $m)->map(fn ($p) => $p->given_name.':'.$p->sibling_order)->all();
    }

    public function test_sample_founders_children_are_in_recorded_order(): void
    {
        $isko = $this->person('Isko');
        $this->assertSame(['Ambo:1', 'Juan:2', 'Tomas:3', 'Rosa:4'], $this->names($isko->id, $this->person('Sela')->id));
    }

    public function test_a_forgotten_child_inserted_mid_set_renumbers_the_siblings_and_nothing_else(): void
    {
        $juan = $this->person('Juan');
        $petra = $this->person('Petra');
        $pedroCruz = $this->person('Pedro', 'Cruz');
        $before = Person::unscoped()->whereNot('father_id', $juan->id)->orWhereNull('father_id')->pluck('sibling_order', 'id')->all();

        app(PersonWriter::class)->save(null, ['given_name' => 'Remembered', 'clan_parent_id' => $juan->id, 'other_parent_id' => $petra->id, 'sibling_order' => 2], $juan->clan_id);

        $this->assertSame(['Maria Clara:1', 'Remembered:2', 'Andres:3'], $this->names($juan->id, $petra->id));
        $after = Person::unscoped()->whereIn('id', array_keys($before))->pluck('sibling_order', 'id')->all();
        ksort($before);
        ksort($after);
        $this->assertSame($before, $after, 'no other set changed');
        $this->assertNotNull($pedroCruz);
    }

    public function test_blank_position_means_after_the_last_child(): void
    {
        $juan = $this->person('Juan');
        $petra = $this->person('Petra');
        app(PersonWriter::class)->save(null, ['given_name' => 'Last', 'clan_parent_id' => $juan->id, 'other_parent_id' => $petra->id], $juan->clan_id);

        $this->assertSame(['Maria Clara:1', 'Andres:2', 'Last:3'], $this->names($juan->id, $petra->id));
    }

    public function test_up_down_swaps_with_a_neighbour_and_renumbers(): void
    {
        $rosa = $this->person('Rosa');
        $this->assertTrue(app(SiblingOrder::class)->move($rosa, -1));
        $this->assertSame(3, $rosa->sibling_order);
        $this->assertSame(['Ambo:1', 'Juan:2', 'Rosa:3', 'Tomas:4'], $this->names($rosa->father_id, $rosa->mother_id));
        $this->assertFalse(app(SiblingOrder::class)->move($this->person('Ambo'), -1), 'first child can’t move earlier');
    }

    public function test_changing_parents_moves_to_the_end_of_the_new_set_and_closes_the_old_gap(): void
    {
        $tomas = $this->person('Tomas', 'Santos');
        $juan = $this->person('Juan');
        $petra = $this->person('Petra');
        $elena = $this->person('Elena');

        app(PersonWriter::class)->save($elena, ['given_name' => 'Elena', 'last_name' => 'Santos', 'nickname' => 'Lenny', 'sex' => 'female', 'clan_parent_id' => $juan->id, 'other_parent_id' => $petra->id], $elena->clan_id);

        $this->assertSame(['Maria Clara:1', 'Andres:2', 'Elena:3'], $this->names($juan->id, $petra->id));
        $andres = $this->person('Andres', 'Santos', 'abt. 1901');
        $this->assertSame(['Teodoro:1', 'Ramon:2'], $this->names($andres->id, $this->person('Lucia')->id));
        $this->assertNotNull($tomas);
    }

    public function test_one_parent_families_form_one_set_with_null_safe_comparison(): void
    {
        $tomas = $this->person('Tomas', 'Santos');
        app(PersonWriter::class)->save(null, ['given_name' => 'Second foundling', 'clan_parent_id' => $tomas->id], $tomas->clan_id);

        // Pilar and the new child share (Tomas, NULL): one set, 1 and 2 — not two sets of 1.
        $this->assertSame(['Pilar:1', 'Second foundling:2'], $this->names($tomas->id, null));
    }

    public function test_a_couples_children_sort_together_whatever_their_clan(): void
    {
        $paolo = $this->person('Paolo');
        $annie = $this->person('Annie Claire');
        $this->assertSame(['Luis:1', 'Bea:2', 'Carlo:3'], $this->names($paolo->id, $annie->id));
    }

    public function test_deleting_a_person_renumbers_their_own_set(): void
    {
        app(PersonWriter::class)->delete($this->person('Juan'));
        $isko = $this->person('Isko');
        $this->assertSame(['Ambo:1', 'Tomas:2', 'Rosa:3'], $this->names($isko->id, $this->person('Sela')->id));
        // Juan's children lose that link: Maria Clara and Andres become Petra's set.
        $this->assertSame(['Maria Clara:1', 'Andres:2'], $this->names(null, $this->person('Petra')->id));
        $this->assertSoftDeleted('people', ['given_name' => 'Juan']);
    }

    public function test_add_children_rows_take_consecutive_numbers_after_those_recorded(): void
    {
        $teo = $this->person('Teodoro');
        $amparo = $this->person('Amparo');
        app(PersonWriter::class)->addChildren($teo, $amparo, [
            ['given_name' => 'Row one'],
            ['given_name' => '', 'nickname' => ''],
            ['nickname' => 'Bunso'],
        ]);

        $this->assertSame(['Andres:1', 'Liza:2', 'Marco:3', 'Row one:4', 'Bunso:5'], $this->names($teo->id, $amparo->id));
        $bunso = $this->person('Bunso');
        $this->assertNull($bunso->nickname, 'nickname-only child: the nickname stands in as the name');
        $this->assertSame('Known only by this nickname.', $bunso->notes);
        $this->assertSame(5, $bunso->generation, 'Teodoro is Gen 4');
    }
}
