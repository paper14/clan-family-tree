<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Services\Cousins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Cousins are descent, so they are unscoped: found through both parents, whatever clan
 * each person is a member of. Uses the sample data with the membership scope switched ON.
 */
class CousinsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSample();
        $this->inClan($this->clan('Santos')); // scope ON — cousins must ignore it
    }

    /** [degree => [group => [names]]], groups keyed "Via A + B (side)". */
    private function cousins(Person $p): array
    {
        $out = [];
        foreach (app(Cousins::class)->of($p) as $d) {
            foreach ($d['groups'] as $g) {
                $key = $g['via']->map->fullName()->implode(' + ').' ('.$g['side'].')';
                $out[$d['degree']][$key] = $g['cousins']->map->fullName()->all();
            }
        }

        return $out;
    }

    /** Rosario Aquino's child: a 2nd cousin to Lito's and Benjie's children. */
    private function addNena(): Person
    {
        $rosario = $this->person('Rosario', 'Aquino');

        return Person::unscoped()->create([
            'clan_id' => $rosario->clan_id, 'given_name' => 'Nena', 'last_name' => 'Aquino', 'sex' => 'female',
            'mother_id' => $rosario->id, 'clan_parent' => 'mother', 'sibling_order' => 1,
        ]);
    }

    public function test_first_cousins_come_through_both_parents_and_across_clans(): void
    {
        // Mark is Dulnuan through his father Benjie; his mother Liza is Santos.
        $this->assertSame([
            1 => [
                'Pedro Dulnuan + Carmen Bautista (father)' => ['Grace Dulnuan'],
                'Teodoro Santos + Amparo Diaz (mother)' => ['Paolo Santos'],
            ],
        ], $this->cousins($this->person('Mark', 'Dulnuan')));
    }

    public function test_siblings_are_never_listed_as_cousins(): void
    {
        $names = collect($this->cousins($this->person('Ana', 'Dulnuan')))->flatten()->all();

        $this->assertNotContains('Mark Dulnuan', $names);
        $this->assertNotContains('Joy Dulnuan', $names);
        $this->assertNotContains('Ana Dulnuan', $names);
    }

    public function test_cousins_follow_sibling_order_not_dates(): void
    {
        // Josefa's 1st cousins on her mother's side are Andres's children, in sibling order.
        $this->assertSame(
            ['Teodoro Santos', 'Elena Santos', 'Ramon Santos'],
            $this->cousins($this->person('Josefa', 'Cruz'))[1]['Juan Santos + Petra Lim (mother)'],
        );
    }

    public function test_second_cousins_share_great_grandparents(): void
    {
        $this->addNena();

        $this->assertSame(
            ['Nena Aquino'],
            $this->cousins($this->person('Mark', 'Dulnuan'))[2]['Pablo Dulnuan + Ines (father)'],
        );
        $nena = $this->cousins(Person::unscoped()->where('given_name', 'Nena')->firstOrFail());
        $this->assertSame(['Grace Dulnuan', 'Mark Dulnuan', 'Ana Dulnuan', 'Joy Dulnuan'], $nena[2]['Pablo Dulnuan + Ines (mother)']);
        $this->assertArrayNotHasKey(1, $nena);
    }

    public function test_cousins_once_removed_are_not_listed(): void
    {
        // Rosario is Lito's 1st cousin, so Grace's 1st cousin once removed.
        $names = collect($this->cousins($this->person('Grace', 'Dulnuan')))->flatten()->all();

        $this->assertNotContains('Rosario Aquino', $names);
        $this->assertContains('Mark Dulnuan', $names);
    }

    public function test_a_hidden_parent_link_is_not_followed(): void
    {
        $mark = $this->person('Mark', 'Dulnuan');
        $mark->update(['hide_second_parent' => true]); // hides Liza, his mother

        $this->assertSame([
            1 => ['Pedro Dulnuan + Carmen Bautista (father)' => ['Grace Dulnuan']],
        ], $this->cousins($mark->fresh()));

        // …nor from the other side: Paolo no longer reaches Mark through Liza.
        $this->assertSame(['Ana Dulnuan', 'Joy Dulnuan'], $this->cousins($this->person('Paolo', 'Santos'))[1]['Teodoro Santos + Amparo Diaz (father)']);
    }

    public function test_ancestors_to_pick_from_are_grandparents_and_up_nearest_first_fathers_side_first(): void
    {
        $names = array_map(fn ($a) => [$a['person']->fullName(), $a['up'], $a['side']], app(Cousins::class)->ancestorsOf($this->person('Mark', 'Dulnuan')));

        $this->assertSame([
            ['Pedro Dulnuan', 2, 'father'], ['Carmen Bautista', 2, 'father'], ['Teodoro Santos', 2, 'mother'], ['Amparo Diaz', 2, 'mother'],
            ['Pablo Dulnuan', 3, 'father'], ['Ines', 3, 'father'], ['Andres Santos', 3, 'mother'], ['Lucia Reyes', 3, 'mother'],
        ], array_slice($names, 0, 8));
        $this->assertNotContains('Benjamin Dulnuan', array_column($names, 0), 'parents are not offered: through a parent there are only siblings');
    }

    public function test_through_an_ancestor_lists_only_their_descendants_each_at_the_closest_degree(): void
    {
        $this->addNena();
        $mark = $this->person('Mark', 'Dulnuan');
        $through = fn (Person $a) => $this->cousinsThrough($mark, $a);

        // Great-grandfather Pablo: Grace shares a grandparent too, so she stays a 1st cousin; Nena is a 2nd.
        $this->assertSame([
            1 => ['Pedro Dulnuan + Carmen Bautista (father)' => ['Grace Dulnuan']],
            2 => ['Pablo Dulnuan + Ines (father)' => ['Nena Aquino']],
        ], $through($this->person('Pablo', 'Dulnuan')));

        // Grandfather Teodoro: only the Santos side.
        $this->assertSame([1 => ['Teodoro Santos + Amparo Diaz (mother)' => ['Paolo Santos']]], $through($this->person('Teodoro')));

        // Someone who isn't Mark's ancestor, or is only his parent: nothing.
        $this->assertSame([], $through($this->person('Rosa', 'Santos'))); // Juan's sister, not Mark's ancestor
        $this->assertSame([], $through($this->person('Benjamin', 'Dulnuan')));
    }

    private function cousinsThrough(Person $p, Person $ancestor): array
    {
        $out = [];
        foreach (app(Cousins::class)->of($p, $ancestor->id) as $d) {
            foreach ($d['groups'] as $g) {
                $out[$d['degree']][$g['via']->map->fullName()->implode(' + ').' ('.$g['side'].')'] = $g['cousins']->map->fullName()->all();
            }
        }

        return $out;
    }

    public function test_person_page_filters_cousins_through_the_chosen_ancestor(): void
    {
        $mark = $this->person('Mark', 'Dulnuan');
        $teodoro = $this->person('Teodoro');

        $this->get("/people/{$mark->id}?through={$teodoro->id}")->assertInertia(fn (Assert $page) => $page
            ->reloadOnly(['cousins', 'cousinAncestors'], fn (Assert $reload) => $reload
                ->where('cousins.0.count', 1)
                ->where('cousins.0.groups.0.cousins.0.name', 'Paolo Santos')
                ->where('cousinAncestors.2', ['id' => $teodoro->id, 'name' => 'Teodoro Santos', 'label' => 'Grandfather · Teodoro Santos · mother’s side'])
            )
        );
    }

    public function test_cousins_are_not_worked_out_until_the_card_is_opened(): void
    {
        $mark = $this->person('Mark', 'Dulnuan');

        $this->get("/people/{$mark->id}")->assertInertia(fn (Assert $page) => $page
            ->component('People/Show')
            ->missing('cousins')->missing('cousinAncestors')
            ->reloadOnly(['cousins', 'cousinAncestors'], fn (Assert $reload) => $reload
                ->where('cousins.0.label', '1st cousins')
                ->where('cousins.0.count', 2)
                ->where('cousins.0.groups.0.label', 'Grandparents Pedro Dulnuan and Carmen Bautista · father’s side')
                ->where('cousins.0.groups.1.label', 'Grandparents Teodoro Santos and Amparo Diaz · mother’s side')
                ->where('cousins.0.groups.1.cousins.0.name', 'Paolo Santos')
                ->where('cousins.0.groups.1.cousins.0.other_clan', true)
            )
        );
    }
}
