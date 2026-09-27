<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\Person;
use App\Services\Backup\BackupService;
use App\Support\DatabaseGuard;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Phase 0's proof (docs/IMPLEMENTING.md §3): seed, back up, drop a clan, restore, confirm
 * the clan and people counts match and generations were recomputed.
 *
 * No wrapping transaction here: mysqldump and the mysql client use their own connections.
 */
class BackupRestoreTest extends TestCase
{
    use DatabaseTruncation;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clan-test-backups-'.uniqid();
        config(['clan.backup.path' => $this->dir, 'clan.backup.keep_auto' => 2]);
        File::deleteDirectory(app(BackupService::class)->photoDir());
        $this->seedSample();
    }

    protected function tearDown(): void
    {
        // This test commits real rows (no wrapping transaction); leave the test DB empty for
        // the transaction-based tests that follow.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['photos', 'marriages', 'people', 'clans'] as $t) {
            DB::table($t)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        File::deleteDirectory($this->dir);
        File::deleteDirectory(app(BackupService::class)->photoDir());
        parent::tearDown();
    }

    public function test_backup_then_drop_a_clan_then_restore_round_trips(): void
    {
        $backups = app(BackupService::class);
        $photoDir = $backups->photoDir();
        File::ensureDirectoryExists($photoDir.'/2026');
        file_put_contents($photoDir.'/2026/portrait.jpg', 'fake-jpeg-bytes');

        $clans = Clan::count();
        $people = Person::unscoped()->count();
        $file = $backups->create();

        $this->assertMatchesRegularExpression('/clan-backup-\d{4}-\d{2}-\d{2}-\d{4}\.zip$/', $file);
        $m = $backups->manifest($file);
        $this->assertSame([3, 48, 1], [$m['clans'], $m['people'], $m['photos']]);

        // Disaster: the Menis clan's rows are gone, generations scrambled, the photo deleted.
        $menis = $this->clan('Menis');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Person::unscoped()->where('clan_id', $menis->id)->forceDelete();
        $menis->forceDelete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        Person::unscoped()->update(['generation' => 99]);
        File::delete($photoDir.'/2026/portrait.jpg');
        $this->assertSame(2, Clan::count());

        $result = $backups->restore($file);

        $this->assertSame($clans, Clan::count());
        $this->assertSame($people, Person::unscoped()->count());
        $this->assertSame(7, $this->person('Luis')->generation, 'generations recomputed after restore');
        $this->assertSame(3, $this->person('Bea')->generation);
        $this->assertNull($this->person('Petra')->generation);
        $this->assertFileExists($photoDir.'/2026/portrait.jpg');
        $this->assertSame('fake-jpeg-bytes', file_get_contents($photoDir.'/2026/portrait.jpg'));
        $this->assertStringContainsString('-auto-before-restore', $result['safety_backup'], 'a safety backup was taken first');
        $this->assertSame(0, $result['pending_migrations']);
    }

    public function test_automatic_backups_are_pruned_and_manual_ones_are_kept(): void
    {
        $backups = app(BackupService::class);
        $backups->create();                        // manual
        foreach (range(1, 4) as $i) {
            $backups->create('before-migrate', true);
        }
        $list = $backups->list();

        $this->assertCount(1, array_filter($list, fn ($b) => ! $b['auto']), 'manual backups are never deleted');
        $this->assertCount(2, array_filter($list, fn ($b) => $b['auto']), 'keep the last N automatic ones');
    }

    public function test_restore_refuses_a_file_that_is_not_a_backup(): void
    {
        $backups = app(BackupService::class);
        $this->expectExceptionMessage('isn’t a Clan Family Tree backup');
        $backups->resolve('../../.env');
    }

    public function test_the_guard_refuses_destructive_commands_against_the_registry_only(): void
    {
        $guard = new DatabaseGuard;
        config(['database.connections.registry-probe' => ['database' => config('clan.registry_database')]]);

        $this->assertStringContainsString('app:migrate', $guard->check('migrate', 'registry-probe'));
        foreach (DatabaseGuard::DESTRUCTIVE as $cmd) {
            $this->assertNotNull($guard->check($cmd, 'registry-probe'), $cmd);
        }
        $this->assertNull($guard->check('migrate:status', 'registry-probe'));
        $this->assertNull($guard->check('migrate:fresh'), 'the test database may be reset');
        $this->assertNull($guard->check('db:seed', 'demo'), 'the demo database may be seeded');
    }
}
