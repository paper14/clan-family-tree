<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuild the DEMO database with the three sample clans (Santos, Dulnuan, Menis).
 * It never touches the registry: refuses if the demo connection points at it.
 */
class LoadDemo extends Command
{
    protected $signature = 'app:demo';

    protected $description = 'Reset the demo database (clan_demo) to the three sample clans';

    public function handle(): int
    {
        $db = config('database.connections.demo.database');
        if (! $db || $db === config('clan.registry_database')) {
            $this->error('Refused: the demo connection points at the registry database.');

            return self::FAILURE;
        }

        $this->info("Resetting the demo database ($db)…");
        $this->call('migrate:fresh', ['--database' => 'demo', '--force' => true]);
        $this->call('db:seed', ['--database' => 'demo', '--force' => true]);

        // The demo's own photo folder, never the registry's.
        File::deleteDirectory(Storage::disk('public')->path('photos-'.$db));

        $this->info('Sample clans loaded. Start the demo with start-demo.bat.');

        return self::SUCCESS;
    }
}
