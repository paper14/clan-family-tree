<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Services\FoundingCouple;
use App\Services\PersonWriter;
use App\Support\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Stored generation: absolute, clan line only; renumbered clan-wide only by Set founding couple. */
class GenerationTest extends TestCase
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

    public function test_sample_generations(): void
    {
        $this->assertSame(1, $this->person('Isko')->generation);
        $this->assertSame(1, $this->person('Sela')->generation);
        $this->assertSame(3, $this->person('Andres', 'Santos', 'abt. 1901')->generation);
        $this->assertNull($this->person('Petra')->generation, 'married in: no stored generation');
        $this->assertNull($this->person('Tomasa')->generation, 'unplaced');
        $this->assertSame(4, $this->person('Mark')->generation, 'Dulnuan line through Benjie');
        $this->assertSame(4, $this->person('Josefa')->generation, 'clan line through her mother Maria Clara (Gen 3)');
    }

    public function test_saving_a_person_recomputes_them_and_their_clan_line_descendants_only(): void
    {
        $nicolas = $this->person('Nicolas');
        $marco = $this->person('Marco');
        // Corrupt an unrelated row to prove the save doesn't renumber the whole clan.
        Person::unscoped()->whereKey($this->person('Ramon', 'Santos')->id)->update(['generation' => 42]);

        app(PersonWriter::class)->save($nicolas, ['given_name' => 'Nicolas', 'clan_parent_id' => $marco->id], $nicolas->clan_id);

        $this->assertSame(6, $this->person('Nicolas')->generation);
        $this->assertSame(42, $this->person('Ramon', 'Santos')->generation, 'the rest of the clan is untouched');
    }

    public function test_relinking_a_branch_renumbers_its_clan_line_descendants(): void
    {
        $teo = $this->person('Teodoro');
        // Move Teodoro (Gen 4, under Andres) to be a child of Tomas's adopted Pilar (Gen 3) → Gen 4 still;
        // move him under Ambo (Gen 2) instead → Gen 3, and his line follows.
        app(PersonWriter::class)->save($teo, ['given_name' => 'Teodoro', 'last_name' => 'Santos', 'sex' => 'male', 'clan_parent_id' => $this->person('Ambo')->id], $teo->clan_id);

        $this->assertSame(3, $this->person('Teodoro')->generation);
        $this->assertSame(4, $this->person('Liza')->generation);
        $this->assertSame(5, $this->person('Paolo')->generation);
        $this->assertSame(6, $this->person('Luis')->generation);
        $this->assertSame(3, $this->person('Bea')->generation, 'Menis members keep their own clan’s number');
        $this->assertSame(4, $this->person('Mark')->generation, 'Dulnuan members keep theirs');
    }

    public function test_display_helper_is_relative_to_a_subclan_root_and_null_across_clans(): void
    {
        $ando = $this->person('Andres', 'Santos', 'abt. 1901');
        $this->assertSame(3, Generation::display($ando));
        $this->assertSame(0, Generation::display($ando, $ando));
        $this->assertSame(3, Generation::display($this->person('Paolo'), $ando));
        $this->assertNull(Generation::display($this->person('Juan'), $ando), 'above the root');
        $this->assertNull(Generation::display($this->person('Bea'), $ando), 'across clans');
        $this->assertNull(Generation::display($this->person('Petra'), $ando), 'unnumbered');
    }

    public function test_set_founding_couple_previews_backs_up_renumbers_and_notes_the_previous_couple(): void
    {
        $santos = $this->clan('Santos');
        $isko = $this->person('Isko');
        // Research found Isko's father: add him and link Isko to him.
        $elder = app(PersonWriter::class)->save(null, ['given_name' => 'Elder', 'sex' => 'male'], $santos->id);
        app(PersonWriter::class)->save($isko, ['given_name' => 'Isko', 'sex' => 'male', 'is_living' => 'false', 'clan_parent_id' => $elder->id], $santos->id);
        $this->assertSame(1, $this->person('Isko')->generation, 'a founder stays Gen 1 until Set founding couple');

        $fc = app(FoundingCouple::class);
        $preview = $fc->preview($santos->fresh(), $elder, null);
        $this->assertSame(['name' => 'Isko', 'from' => 1, 'to' => 2], $preview['old']);
        $this->assertSame(1, $preview['lost'], 'Sela loses her number: her line doesn’t reach the new founder');
        $this->assertFalse($preview['founderHasParents']);

        $result = $fc->apply($santos->fresh(), $elder, null);

        $this->assertCount(1, glob($this->backupDir.'/clan-backup-*-auto-before-renumbering.zip'), 'an automatic backup first');
        $this->assertSame(2, $this->person('Isko')->generation);
        $this->assertSame(8, $this->person('Luis')->generation);
        $this->assertNull($this->person('Sela')->generation);
        $this->assertSame(3, $this->person('Bea')->generation, 'other clans untouched');
        $this->assertStringEndsWith('Previous founding couple: Isko and Sela (until '.now()->format('j F Y').').', $santos->fresh()->notes);
        $this->assertSame($result['numbered'], Person::unscoped()->where('clan_id', $santos->id)->whereNotNull('generation')->count());
    }
}
