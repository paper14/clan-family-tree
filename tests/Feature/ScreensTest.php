<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The screens and actions, through HTTP, on the sample data. */
class ScreensTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSample();
        $this->backupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clan-test-backups-'.uniqid();
        config(['clan.backup.path' => $this->backupDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);
        parent::tearDown();
    }

    public function test_people_list_is_membership_scoped(): void
    {
        $this->inClan($this->clan('Santos'));

        $this->get('/people')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('People/Index')
            ->where('total', 28)
            ->where('people', fn ($people) => collect($people)->pluck('name')->contains('Luis Santos')
                && ! collect($people)->pluck('name')->contains('Bea Santos')
                && ! collect($people)->pluck('name')->contains('Carlo Santos'))
            ->where('unplaced', fn ($u) => collect($u)->pluck('name')->sort()->values()->all() === ['Nicolas', 'Tomasa'])
        );
    }

    public function test_people_list_family_order_and_subclan_numbering(): void
    {
        $this->inClan($this->clan('Santos'));
        $ando = $this->person('Andres', 'Santos', 'abt. 1901');

        $this->get('/people')->assertInertia(fn (Assert $page) => $page
            ->where('people', function ($people) {
                $names = collect($people)->take(6)->pluck('name')->all();

                return $names === ['Isko', 'Sela', 'Ambo', 'Juan Santos', 'Tomas Santos', 'Rosa Santos'];
            })
        );

        $this->get('/people?root='.$ando->id)->assertInertia(fn (Assert $page) => $page
            ->where('root.id', $ando->id)
            ->where('people.0.name', 'Andres Santos')
            ->where('people.0.g', 0)
            ->where('people', fn ($people) => collect($people)->firstWhere('name', 'Paolo Santos')['g'] === 3
                && ! collect($people)->contains('name', 'Juan Santos'))
        );
    }

    public function test_lizas_page_lists_her_dulnuan_children(): void
    {
        $this->inClan($this->clan('Santos'));
        $liza = $this->person('Liza');

        $this->get('/people/'.$liza->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('People/Show')
            ->where('childCount', 3)
            ->where('childGroups.0.other.name', 'Benjamin Dulnuan')
            ->where('childGroups.0.kids', fn ($kids) => collect($kids)->pluck('name')->all() === ['Mark Dulnuan', 'Ana Dulnuan', 'Joy Dulnuan']
                && collect($kids)->every(fn ($k) => $k['other_clan'] && $k['clan_label'] === 'Dulnuan clan' && $k['generation'] === 4))
        );
    }

    public function test_paolos_page_lists_all_three_children_as_one_set(): void
    {
        $paolo = $this->person('Paolo');
        $this->get('/people/'.$paolo->id)->assertInertia(fn (Assert $page) => $page
            ->where('childGroups', fn ($g) => count($g) === 1)
            ->where('childGroups.0.kids', fn ($kids) => collect($kids)->pluck('sibling_order')->all() === [1, 2, 3]
                && collect($kids)->pluck('other_clan')->all() === [false, true, true])
        );
    }

    public function test_opening_a_person_from_another_clan_switches_clan(): void
    {
        $this->inClan($this->clan('Santos'));
        $bea = $this->person('Bea');

        $this->get('/people/'.$bea->id)->assertOk()->assertSessionHas('clan_id', $this->clan('Menis')->id);
    }

    public function test_spouse_search_crosses_clans_ignores_accents_and_ands_words(): void
    {
        $this->inClan($this->clan('Santos'));
        Person::unscoped()->create(['clan_id' => $this->clan('Dulnuan')->id, 'given_name' => 'Rosario', 'last_name' => 'Peña', 'sibling_order' => 1]);
        $lucia = $this->person('Lucia');

        $hits = $this->getJson('/spouse-search?q=pena&person='.$lucia->id)->assertOk()->json();
        $this->assertSame(['Rosario Peña'], array_column($hits, 'name'));
        $this->assertSame('Dulnuan clan', $hits[0]['clan_label']);

        $hits = $this->getJson('/spouse-search?q=annie+menis&person='.$lucia->id)->json();
        $this->assertSame(['Annie Claire Menis'], array_column($hits, 'name'));

        $hits = $this->getJson('/spouse-search?q=ben&person='.$this->person('Liza')->id)->json();
        $benjie = collect($hits)->firstWhere('name', 'Benjamin Dulnuan');
        $this->assertTrue($benjie['already'], 'flagged: already married to them');

        $this->assertSame([], $this->getJson('/spouse-search?q=a&person=1')->json(), 'starts at 2 characters');
    }

    public function test_the_form_refuses_hard_stops_with_the_prototype_wording(): void
    {
        $this->inClan($this->clan('Santos'));

        $this->post('/people', ['given_name' => ''])->assertSessionHasErrors(['given_name' => 'a given name is needed. It’s the only required field.']);
        $this->post('/people', ['given_name' => 'X', 'clan_parent_id' => $this->person('Benjamin')->id])
            ->assertSessionHasErrors(['clan_parent_id' => 'a parent link can’t cross clans. Benjamin Dulnuan is in the Dulnuan clan.']);

        $juan = $this->person('Juan');
        $this->put('/people/'.$juan->id, ['given_name' => 'Juan', 'clan_parent_id' => $this->person('Paolo')->id])
            ->assertSessionHasErrors(['clan_parent_id' => 'Juan can’t be their own ancestor — Paolo Santos descends from them. Pick a different parent.']);
    }

    public function test_save_person_and_add_another_under_the_same_parent(): void
    {
        $this->inClan($this->clan('Santos'));
        $marco = $this->person('Marco');

        $this->post('/people', ['given_name' => 'Pia', 'clan_parent_id' => $marco->id, 'again' => true])
            ->assertRedirect('/people/create?parent='.$marco->id)
            ->assertSessionHas('toast', 'Saved Pia · Gen 6');
        $this->assertSame(2, $this->person('Pia')->sibling_order, 'after Paolo');
    }

    public function test_add_children_saves_rows_in_order_and_toasts(): void
    {
        $teo = $this->person('Teodoro');
        $amparo = $this->person('Amparo');

        $this->post('/people/'.$teo->id.'/children', [
            'other_parent_id' => $amparo->id,
            'rows' => [['given_name' => 'Nena', 'sex' => 'female'], ['given_name' => ''], ['given_name' => 'Totoy']],
        ])->assertRedirect('/people/'.$teo->id)->assertSessionHas('toast', 'Saved 2 children of Teodoro Santos');

        $this->assertSame([4, 5], [$this->person('Nena')->sibling_order, $this->person('Totoy')->sibling_order]);
    }

    public function test_add_marriage_links_across_clans_and_assigns_columns_by_sex(): void
    {
        $jun = $this->person('Jun'); // Menis, male
        $tomasa = $this->person('Tomasa'); // Santos, female

        $this->from('/people/'.$tomasa->id)
            ->post('/people/'.$tomasa->id.'/marriages', ['spouse_id' => $jun->id, 'date_text' => 'abt. 1950', 'status' => 'married'])
            ->assertSessionHas('toast', 'Marriage saved — it links the Santos clan and the Menis clan.');

        $m = Marriage::where('wife_id', $tomasa->id)->firstOrFail();
        $this->assertSame($jun->id, $m->husband_id);

        // Create second: a new spouse, nickname only, no sex → husband = the page person (default).
        $nicolas = $this->person('Nicolas');
        $this->post('/people/'.$nicolas->id.'/marriages', ['create' => 1, 'new_nick' => 'Iday', 'new_sex' => ''])->assertSessionHasNoErrors();
        $iday = $this->person('Iday');
        $this->assertNull($iday->nickname);
        $this->assertSame('Known only by this nickname.', $iday->notes);
        $this->assertSame($nicolas->clan_id, $iday->clan_id);
        $m2 = Marriage::where('wife_id', $iday->id)->firstOrFail();
        $this->assertSame($nicolas->id, $m2->husband_id, 'male page person → husband');
    }

    public function test_clan_create_with_founders_then_soft_delete_and_restore(): void
    {
        $this->post('/clans', ['name' => 'Bautista', 'f_given' => 'Andoy', 'f_sex' => 'female', 's_given' => 'Kulas'])
            ->assertRedirect('/people');
        $c = $this->clan('Bautista');
        $this->assertSame('Andoy', $c->founder->given_name);
        $this->assertSame(1, $c->founder->generation);
        $this->assertSame($c->founder_spouse_id, Marriage::where('wife_id', $c->founder_id)->value('husband_id'), 'female founder → wife');

        $this->post('/clans', ['name' => ' '])->assertSessionHasErrors(['name' => 'the clan needs a name.']);

        $this->delete('/clans/'.$c->id)->assertSessionHas('toast', 'Deleted the Bautista clan (2 people).');
        $this->assertSoftDeleted('clans', ['id' => $c->id]);
        $this->post('/clans/'.$c->id.'/restore')->assertSessionHas('toast', 'Restored the Bautista clan.');
        $this->assertNull($c->fresh()->deleted_at);
    }

    public function test_set_founding_couple_through_the_settings_screen(): void
    {
        $santos = $this->clan('Santos');
        $ando = $this->person('Andres', 'Santos', 'abt. 1901');

        $this->get('/clans/'.$santos->id.'/settings?founder='.$ando->id)->assertInertia(fn (Assert $page) => $page
            ->component('Clans/Settings')
            ->where('chosen.spouse', $this->person('Lucia')->id)
            ->where('preview.old', ['name' => 'Isko', 'from' => 1, 'to' => null])
            ->where('preview.founderHasParents', true)
        );

        $this->post('/clans/'.$santos->id.'/founders', ['founder_id' => $ando->id, 'spouse_id' => $this->person('Lucia')->id])
            ->assertRedirect('/clans/'.$santos->id.'/settings');
        $this->assertSame(1, $ando->fresh()->generation);
        $this->assertSame(4, $this->person('Paolo')->generation);
        $this->assertCount(1, glob($this->backupDir.'/*-auto-before-renumbering.zip'));

        // Spouse must be married to the founder.
        $this->post('/clans/'.$santos->id.'/founders', ['founder_id' => $ando->id, 'spouse_id' => $this->person('Petra')->id])
            ->assertSessionHasErrors('spouse_id');
    }

    public function test_up_down_arrows_toast_the_new_position(): void
    {
        $this->from('/people/1')->post('/people/'.$this->person('Rosa')->id.'/move', ['dir' => -1])
            ->assertSessionHas('toast', 'Rosa Santos is now child 3 of 4.');
    }

    public function test_tree_page_and_data_and_print_route_share_one_query(): void
    {
        $this->inClan($this->clan('Santos'));
        $isko = $this->person('Isko');
        $paolo = $this->person('Paolo');

        $this->get('/tree?start='.$paolo->id)->assertInertia(fn (Assert $page) => $page
            ->component('Tree/Index')->where('requestedStart', $paolo->id)->where('defaultStart', $isko->id));

        $data = $this->getJson('/tree/data?start='.$isko->id.'&depth=8')->assertOk()->json();
        $this->assertSame('Gen 1 = founding couple Isko and Sela', 'Gen 1 = founding couple '.$data['clan']['foundersText']);
        $this->assertTrue($data['start']['is_founder']);
        $this->assertArrayHasKey($this->person('Carlo')->id, $data['people']);

        $this->get('/print/sheet?start='.$isko->id.'&depth=8&format=outline')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Print/Sheet')
            ->where('data.people.'.$this->person('Bea')->id.'.clan_label', 'Menis clan'));
    }

    public function test_show_in_tree_for_a_person_of_another_clan_switches_clan(): void
    {
        $this->inClan($this->clan('Santos'));
        $mark = $this->person('Mark');
        $this->get('/tree?start='.$mark->id)->assertSessionHas('clan_id', $this->clan('Dulnuan')->id)
            ->assertInertia(fn (Assert $page) => $page->where('requestedStart', $mark->id));
    }

    public function test_photos_are_resized_first_is_main_and_deleting_main_promotes_the_next(): void
    {
        Storage::fake('public');
        $ando = $this->person('Andres', 'Santos', 'abt. 1901');

        $this->post('/photos', ['kind' => 'portrait', 'type' => 'person', 'id' => $ando->id, 'file' => UploadedFile::fake()->image('big.jpg', 3000, 2000)])
            ->assertSessionHas('toast', 'Portrait saved as the main picture. Add the year if anyone remembers it.');
        $this->post('/photos', ['kind' => 'portrait', 'type' => 'person', 'id' => $ando->id, 'file' => UploadedFile::fake()->image('second.jpg', 300, 400), 'year' => '1950'])
            ->assertSessionHas('toast', 'Portrait saved.');
        $this->post('/photos', ['kind' => 'portrait', 'type' => 'person', 'id' => $ando->id, 'file' => UploadedFile::fake()->image('x.jpg'), 'year' => '50'])
            ->assertSessionHasErrors(['year' => 'Year should be four digits, like 1998 — or leave it blank.']);

        [$first, $second] = Photo::orderBy('id')->get()->all();
        $this->assertSame([480, 320], [$first->width, $first->height], 'web-sized: 480 px on the long edge');
        $this->assertTrue($first->is_primary);
        $this->assertFalse($second->is_primary);
        Storage::disk('public')->assertExists(config('clan.photos.folder').'/'.$first->file_path);

        $this->delete('/photos/'.$first->id);
        $this->assertTrue($second->fresh()->is_primary);
        Storage::disk('public')->assertMissing(config('clan.photos.folder').'/'.$first->file_path);

        $this->post('/photos', ['kind' => 'clan_group', 'type' => 'clan', 'id' => $this->clan('Santos')->id, 'file' => UploadedFile::fake()->image('reunion.jpg', 4000, 3000)]);
        $group = Photo::where('kind', 'clan_group')->firstOrFail();
        $this->assertSame([1400, 1050], [$group->width, $group->height], 'group photos to 1400 px');
    }

    public function test_backups_screen_and_manual_backup(): void
    {
        $this->post('/backups')->assertSessionHas('toast');
        $this->get('/backups')->assertInertia(fn (Assert $page) => $page
            ->component('Backups/Index')
            ->where('backups.0.people', 48)
            ->where('backups.0.auto', false));
    }

    public function test_every_screen_renders(): void
    {
        $this->inClan($this->clan('Santos'));
        $id = $this->person('Andres', 'Santos', 'abt. 1901')->id;
        foreach (['/', '/clans/create', '/people', '/people/create', "/people/$id", "/people/$id/edit", '/children', "/children?parent=$id", '/tree', '/print', '/backups', '/clans/'.$this->clan('Santos')->id.'/settings'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
