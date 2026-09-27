<?php

namespace App\Services\Backup;

use App\Models\Clan;
use App\Models\Person;
use App\Models\Photo;
use App\Services\Generations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * architecture-local.md §4. A backup is a timestamped zip of:
 *   dump.sql       mysqldump --single-transaction of the registry database
 *   photos/…       the photos folder
 *   manifest.json  date, database, clan and people counts (shown before a restore)
 * written to a folder OUTSIDE the project. Taken on demand, before Set founding couple,
 * before migrations, and before a restore (a safety backup).
 */
class BackupService
{
    public function __construct(private Generations $generations) {}

    public function directory(): string
    {
        $dir = config('clan.backup.path');
        if (! $dir) {
            throw new RuntimeException('No backup folder is set. Put CLAN_BACKUP_PATH in the .env file — a folder outside the project, ideally on another drive.');
        }
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Can't create the backup folder $dir.");
        }

        return $dir;
    }

    /**
     * Take a backup. $tag names why ("before-renumbering"); automatic backups are pruned
     * to the newest N, manual ones never are. Returns the zip's full path.
     */
    public function create(?string $tag = null, bool $auto = false): string
    {
        $dir = $this->directory();
        $base = 'clan-backup-'.now()->format('Y-m-d-Hi').($auto ? '-auto' : '').($tag ? '-'.$tag : '');
        $path = $dir.DIRECTORY_SEPARATOR.$base.'.zip';
        for ($n = 2; file_exists($path); $n++) {
            $path = $dir.DIRECTORY_SEPARATOR.$base.'-'.$n.'.zip';
        }

        $work = $this->tempDir();
        try {
            $dump = $work.DIRECTORY_SEPARATOR.'dump.sql';
            $this->runDump($dump);

            $zip = new ZipArchive;
            $partial = $path.'.part';
            if ($zip->open($partial, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Can't write the backup file in $dir.");
            }
            $zip->addFile($dump, 'dump.sql');
            $photos = 0;
            $photoDir = $this->photoDir();
            if (is_dir($photoDir)) {
                foreach (File::allFiles($photoDir) as $file) {
                    $zip->addFile($file->getPathname(), 'photos/'.str_replace('\\', '/', $file->getRelativePathname()));
                    $photos++;
                }
            }
            $zip->addFromString('manifest.json', json_encode([
                'app' => 'clan-family-tree',
                'format' => 1,
                'created_at' => now()->toIso8601String(),
                'database' => config('database.connections.'.config('database.default').'.database'),
                'tag' => $tag,
                'auto' => $auto,
                'clans' => Schema::hasTable('clans') ? Clan::count() : 0,
                'people' => Schema::hasTable('people') ? Person::unscoped()->count() : 0,
                'photos' => $photos,
            ], JSON_PRETTY_PRINT));
            if (! $zip->close()) {
                throw new RuntimeException("Can't finish writing the backup file in $dir.");
            }
            rename($partial, $path);
        } finally {
            File::deleteDirectory($work);
        }

        if ($auto) {
            $this->pruneAutomatic();
        }

        return $path;
    }

    /** Every backup in the folder, newest first, with its manifest summary. */
    public function list(): array
    {
        $dir = config('clan.backup.path');
        if (! $dir || ! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir.DIRECTORY_SEPARATOR.'clan-backup-*.zip') ?: [] as $file) {
            $m = $this->manifest($file);
            $out[] = [
                'file' => basename($file),
                'size' => filesize($file),
                'created_at' => $m['created_at'] ?? Carbon::createFromTimestamp(filemtime($file))->toIso8601String(),
                'auto' => $m['auto'] ?? str_contains(basename($file), '-auto'),
                'tag' => $m['tag'] ?? null,
                'clans' => $m['clans'] ?? null,
                'people' => $m['people'] ?? null,
                'photos' => $m['photos'] ?? null,
                'readable' => $m !== null,
            ];
        }
        usort($out, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $out;
    }

    /** Cheap enough for every page: file times only, no zip is opened. */
    public function lastBackupAt(): ?string
    {
        $dir = config('clan.backup.path');
        $files = $dir && is_dir($dir) ? (glob($dir.DIRECTORY_SEPARATOR.'clan-backup-*.zip') ?: []) : [];
        $newest = $files ? max(array_map('filemtime', $files)) : null;

        return $newest ? Carbon::createFromTimestamp($newest)->toIso8601String() : null;
    }

    /** manifest.json from a backup, or null if it isn't one. */
    public function manifest(string $file): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            return null;
        }
        $json = $zip->getFromName('manifest.json');
        $hasDump = $zip->locateName('dump.sql') !== false;
        $zip->close();
        $m = $json ? json_decode($json, true) : null;

        return $hasDump && is_array($m) && ($m['app'] ?? null) === 'clan-family-tree' ? $m : null;
    }

    /** Resolve a file name from the list to its full path, refusing anything outside the folder. */
    public function resolve(string $name): string
    {
        if (! preg_match('/^clan-backup-[A-Za-z0-9-]+\.zip$/', $name)) {
            throw new RuntimeException('That isn’t a Clan Family Tree backup file.');
        }
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        if (! is_file($path)) {
            throw new RuntimeException('That backup file isn’t in the backup folder any more.');
        }

        return $path;
    }

    /**
     * Restore: a safety backup of the current state first, then load the dump and replace
     * the photos folder, then renumber every clan (an older file can bring back a different
     * founder). Returns the restored manifest plus the safety backup's file name.
     */
    public function restore(string $file): array
    {
        $manifest = $this->manifest($file);
        if (! $manifest) {
            throw new RuntimeException('That file isn’t a Clan Family Tree backup.');
        }

        $safety = $this->create('before-restore', true);

        $work = $this->tempDir();
        try {
            $zip = new ZipArchive;
            if ($zip->open($file, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('Can’t open the backup file.');
            }
            $dump = $work.DIRECTORY_SEPARATOR.'dump.sql';
            file_put_contents($dump, $zip->getStream('dump.sql'));

            $this->runLoad($dump);

            // Photos: replace the folder with the backup's copy.
            $photoDir = $this->photoDir();
            File::deleteDirectory($photoDir);
            File::ensureDirectoryExists($photoDir);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! str_starts_with($name, 'photos/') || str_ends_with($name, '/')) {
                    continue;
                }
                $rel = substr($name, strlen('photos/'));
                if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/')) {
                    continue; // never write outside the photos folder
                }
                $target = $photoDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
                File::ensureDirectoryExists(dirname($target));
                file_put_contents($target, $zip->getStream($name));
            }
            $zip->close();
        } finally {
            File::deleteDirectory($work);
        }

        DB::purge();
        $this->generations->recomputeAll();

        return $manifest + ['safety_backup' => basename($safety), 'pending_migrations' => $this->pendingMigrations()];
    }

    /** Migrations in the code that the restored database hasn't run (an older backup). */
    public function pendingMigrations(): int
    {
        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
        } catch (\Throwable) {
            return 0;
        }
        $files = array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')) ?: []);

        return count(array_diff($files, $ran));
    }

    public function photoDir(): string
    {
        return Storage::disk('public')->path(config('clan.photos.folder'));
    }

    private function pruneAutomatic(): void
    {
        $keep = max(1, config('clan.backup.keep_auto'));
        $auto = array_values(array_filter($this->list(), fn ($b) => $b['auto']));
        foreach (array_slice($auto, $keep) as $b) {
            @unlink($this->directory().DIRECTORY_SEPARATOR.$b['file']);
        }
    }

    private function runDump(string $target): void
    {
        $conn = $this->connection();
        $this->withDefaultsFile($conn, function (string $defaults) use ($conn, $target) {
            $process = new Process([
                config('clan.backup.mysqldump'),
                '--defaults-extra-file='.$defaults,
                '--single-transaction',
                '--routines',
                '--default-character-set=utf8mb4',
                '--no-tablespaces',
                '--set-gtid-purged=OFF',
                '--result-file='.$target,
                $conn['database'],
            ]);
            $process->setTimeout(600)->run();
            if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
                throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput() ?: $process->getOutput() ?: 'no output').' — check CLAN_MYSQLDUMP_PATH in .env.');
            }
        });
    }

    private function runLoad(string $dump): void
    {
        $conn = $this->connection();
        $this->withDefaultsFile($conn, function (string $defaults) use ($conn, $dump) {
            $in = fopen($dump, 'rb');
            try {
                $process = new Process([
                    config('clan.backup.mysql'),
                    '--defaults-extra-file='.$defaults,
                    '--default-character-set=utf8mb4',
                    $conn['database'],
                ]);
                $process->setInput($in)->setTimeout(600)->run();
            } finally {
                if (is_resource($in)) {
                    fclose($in);
                }
            }
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Loading the backup failed: '.trim($process->getErrorOutput() ?: 'no output').' — the safety backup taken just before holds the previous state.');
            }
        });
    }

    /** The password goes in a temporary options file, never on the command line. */
    private function withDefaultsFile(array $conn, callable $fn): void
    {
        $file = tempnam(sys_get_temp_dir(), 'clan-my');
        file_put_contents($file, sprintf(
            "[client]\nuser=\"%s\"\npassword=\"%s\"\nhost=\"%s\"\nport=%d\n",
            addcslashes($conn['username'], '"\\'),
            addcslashes((string) $conn['password'], '"\\'),
            addcslashes($conn['host'], '"\\'),
            (int) $conn['port'],
        ));
        try {
            $fn($file);
        } finally {
            @unlink($file);
        }
    }

    private function connection(): array
    {
        return config('database.connections.'.config('database.default'));
    }

    private function tempDir(): string
    {
        $dir = storage_path('app/private/backup-work-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
