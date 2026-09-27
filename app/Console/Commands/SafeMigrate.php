<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * The ONLY way to migrate the registry (architecture-local.md §3): back up, then
 * `migrate --force`. Plain `migrate` is refused against the registry by DatabaseGuard.
 */
class SafeMigrate extends Command
{
    protected $signature = 'app:migrate';

    protected $description = 'Back up the database and photos, then run pending migrations';

    public function handle(BackupService $backups): int
    {
        $this->info('Backing up before migrating…');
        try {
            $file = $backups->create('before-migrate', auto: true);
        } catch (Throwable $e) {
            $this->error('Backup failed, so nothing was migrated. '.$e->getMessage());

            return self::FAILURE;
        }
        $this->line('Backup written: '.$file);

        return $this->call('migrate', ['--force' => true]);
    }
}
