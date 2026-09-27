<?php

namespace App\Support;

use Illuminate\Console\Events\CommandStarting;

/**
 * Migrations and resets are the main way this app could destroy hand-typed records
 * (architecture-local.md §3). Against the registry database:
 *  - plain `migrate` is refused: it skips the backup. Use `php artisan app:migrate`.
 *  - fresh / refresh / reset / rollback / wipe / seed are refused outright.
 *
 * app:migrate and app:demo run their inner commands with $this->call(), which doesn't
 * fire CommandStarting, so only direct invocations are checked here.
 */
class DatabaseGuard
{
    public const DESTRUCTIVE = ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'db:seed'];

    public function handle(CommandStarting $event): void
    {
        $connection = $event->input->getParameterOption('--database') ?: null;
        if ($message = $this->check((string) $event->command, $connection)) {
            $this->refuse($message);
        }
    }

    /** The refusal message for this command against this connection, or null when it may run. */
    public function check(string $command, ?string $connection = null): ?string
    {
        if ($command !== 'migrate' && ! in_array($command, self::DESTRUCTIVE, true)) {
            return null;
        }

        $connection = $connection ?: config('database.default');
        $database = config("database.connections.$connection.database");

        if ($database !== config('clan.registry_database')) {
            return null;
        }

        if ($command === 'migrate') {
            return 'Refused: plain "migrate" skips the backup. Migrate the registry with: php artisan app:migrate';
        }

        return "Refused: \"$command\" against the registry database ($database) could destroy hand-typed records. Sample data goes to the demo database: php artisan app:demo";
    }

    /** A plain message and a failing exit code — no stack trace for a non-developer to read. */
    private function refuse(string $message): never
    {
        fwrite(STDERR, PHP_EOL.'  '.$message.PHP_EOL.PHP_EOL);
        exit(1);
    }
}
