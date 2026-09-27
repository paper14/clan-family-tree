<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Services\Descendants;
use App\Services\SiblingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The descendants query must walk parent links and NEVER filter on clan_id
 * (planning.md §2.7, implementation-notes.md §6.1). These use the sample data, with the
 * membership scope switched ON (Santos picked in the header) — the query must ignore it.
 */
class DescendantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSample();
    }

    private function tree(Person $root, int $depth, array $collapsed = [], bool $hidden = false): array
    {
        return app(Descendants::class)->build($root->id, $depth, $collapsed, $hidden);
    }

    /** Flatten to [id => node]. */
    private function nodes(array $node, array &$out = []): array
    {
        $out[$node['id']] = $node;
        foreach ($node['kids'] as $k) {
            $this->nodes($k, $out);
        }

        return $out;
    }

    private function chartGen(array $t, array $node): ?int
    {
        return $t['rootPerson']['generation'] === null ? null : $t['rootPerson']['generation'] + $node['d'];
    }

    public function test_santos_tree_draws_all_three_of_paolos_children_at_gen_7_with_two_labelled_menis(): void
    {
        $this->inClan($this->clan('Santos')); // scope ON — the query must not inherit it

        $t = $this->tree($this->person('Isko'), 8);
        $nodes = $this->nodes($t['root']);
        $paolo = $nodes[$this->person('Paolo')->id];

        $kids = array_map(fn ($k) => $t['people'][$k['id']]['name'], $paolo['kids']);
        $this->assertSame(['Luis Santos', 'Bea Santos', 'Carlo Santos'], $kids, 'siblings in sibling_order, whatever their clan');

        foreach ($paolo['kids'] as $k) {
            $this->assertSame(7, $this->chartGen($t, $k));
        }
        $menis = $this->clan('Menis')->id;
        $this->assertSame([$this->clan('Santos')->id, $menis, $menis], array_map(fn ($k) => $t['people'][$k['id']]['clan_id'], $paolo['kids']));
        $this->assertSame('Menis clan', $t['people'][$this->person('Bea')->id]['clan_label']);
        $this->assertSame(3, $paolo['total'], 'Paolo Santos — 3 in this chart');
    }

    public function test_lizas_dulnuan_children_appear_under_her_in_the_santos_tree(): void
    {
        $this->inClan($this->clan('Santos'));

        $t = $this->tree($this->person('Isko'), 8);
        $liza = $this->nodes($t['root'])[$this->person('Liza')->id];

        $this->assertSame(['Mark Dulnuan', 'Ana Dulnuan', 'Joy Dulnuan'], array_map(fn ($k) => $t['people'][$k['id']]['name'], $liza['kids']));
        $this->assertSame('Dulnuan clan', $t['people'][$liza['kids'][0]['id']]['clan_label']);
        $this->assertSame($this->person('Benjamin')->id, $liza['spouses'][0]['id']);
    }

    public function test_menis_tree_shows_the_same_children_at_gen_3(): void
    {
        $this->inClan($this->clan('Menis'));

        $t = $this->tree($this->person('Ramon', 'Menis'), 8);
        $annie = $this->nodes($t['root'])[$this->person('Annie Claire')->id];

        $this->assertCount(3, $annie['kids']);
        foreach ($annie['kids'] as $k) {
            $this->assertSame(3, $this->chartGen($t, $k));
        }
        // Stored generation stays per the member's own clan: Bea is Gen 3 stored, Luis Gen 7.
        $this->assertSame(3, $this->person('Bea')->generation);
        $this->assertSame(7, $this->person('Luis')->generation);
    }

    public function test_the_santos_people_list_still_contains_neither_bea_nor_carlo(): void
    {
        $this->inClan($this->clan('Santos'));

        $names = Person::query()->pluck('given_name')->all(); // membership: scoped
        $this->assertNotContains('Bea', $names);
        $this->assertNotContains('Carlo', $names);
        $this->assertContains('Luis', $names);
    }

    public function test_the_tree_never_walks_up_a_spouse_from_another_clan(): void
    {
        $t = $this->tree($this->person('Isko'), 10);

        $this->assertArrayHasKey($this->person('Annie Claire')->id, $t['people'], 'Annie is drawn as a spouse box');
        $this->assertArrayNotHasKey($this->person('Ramon', 'Menis')->id, $t['people'], 'her parents are never drawn');
        $this->assertArrayNotHasKey($this->person('Jun')->id, $t['people'], 'nor her siblings');
    }

    public function test_depth_cuts_a_branch_and_counts_what_is_below(): void
    {
        $t = $this->tree($this->person('Isko'), 3);
        $nodes = $this->nodes($t['root']);
        $andres = $nodes[$this->person('Andres', 'Santos', 'abt. 1901')->id];

        $this->assertTrue($andres['cut']);
        $this->assertSame([], $andres['kids']);
        // Teodoro, Elena, Ramon, Teodoro's 3, Liza's 3, Paolo, Paolo's 3 = 3 + 3 + 3 + 1 + 3
        $this->assertSame(13, $andres['total']);
    }

    public function test_collapsed_branches_are_walked_for_counts_but_not_returned(): void
    {
        $andres = $this->person('Andres', 'Santos', 'abt. 1901');
        $t = $this->tree($this->person('Isko'), 8, [$andres->id]);
        $node = $this->nodes($t['root'])[$andres->id];

        $this->assertTrue($node['collapsed']);
        $this->assertSame([], $node['kids']);
        $this->assertSame(13, $node['total']);
    }

    public function test_a_child_of_two_members_is_drawn_once_on_the_shortest_path(): void
    {
        // Andres (b. 1956, Gen 5) and his cousin Josefa Cruz (Gen 4) have a child.
        $andres = $this->person('Andres', 'Santos', '1956');
        $josefa = $this->person('Josefa');
        $kid = Person::unscoped()->create([
            'clan_id' => $andres->clan_id, 'given_name' => 'Cousin-child', 'father_id' => $andres->id,
            'mother_id' => $josefa->id, 'clan_parent' => 'father', 'sibling_order' => 1,
        ]);

        $t = $this->tree($this->person('Isko'), 10);
        $nodes = $this->nodes($t['root']);
        $appearances = 0;
        array_walk_recursive($t['root'], function ($v, $k) use (&$appearances, $kid) {
            if ($k === 'id' && $v === $kid->id) {
                $appearances++;
            }
        });

        $this->assertSame(1, $appearances, 'drawn once');
        $this->assertSame(4, $nodes[$kid->id]['d'], 'at the shorter depth, under Josefa (Gen 5 in the chart)');
        $this->assertContains($kid->id, array_column($nodes[$josefa->id]['kids'], 'id'));
    }

    public function test_a_hidden_second_parent_link_is_left_out_unless_the_archive_toggle_is_on(): void
    {
        // Both parents are Santos members and one is not to be shown.
        $marco = $this->person('Marco');
        $lenny = $this->person('Elena');
        $child = Person::unscoped()->create([
            'clan_id' => $marco->clan_id, 'given_name' => 'Hidden-link child', 'father_id' => $marco->id,
            'mother_id' => $lenny->id, 'clan_parent' => 'father', 'hide_second_parent' => true, 'sibling_order' => 1,
        ]);

        $t = $this->tree($lenny, 5);
        $this->assertArrayNotHasKey($child->id, $t['people'], 'hidden from the other parent’s branch');

        $t = $this->tree($lenny, 5, [], true);
        $this->assertArrayHasKey($child->id, $t['people'], 'archive copy includes it');

        $t = $this->tree($marco, 5);
        $this->assertArrayHasKey($child->id, $t['people'], 'still under the clan-line parent');
        $this->assertSame('Elena Santos', $t['people'][$child->id]['hidden_link']);
    }

    public function test_sibling_order_not_dates_decides_the_left_to_right_order(): void
    {
        $juan = $this->person('Juan');
        app(SiblingOrder::class)->move($juan, 1); // Juan after Tomas; dates unchanged

        $t = $this->tree($this->person('Isko'), 2);
        $this->assertSame(['Ambo', 'Tomas Santos', 'Juan Santos', 'Rosa Santos'], array_map(fn ($k) => $t['people'][$k['id']]['name'], $t['root']['kids']));
    }
}
