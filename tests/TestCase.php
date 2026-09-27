<?php

namespace Tests;

use App\Models\Clan;
use App\Models\Person;
use App\Support\CurrentClan;
use Database\Seeders\SampleClansSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** RefreshDatabase wipes the database: never let that be the registry. */
    protected function beforeRefreshingDatabase()
    {
        $db = config('database.connections.'.config('database.default').'.database');
        if ($db === config('clan.registry_database') || $db !== 'clan_test') {
            throw new RuntimeException("Tests refuse to run against \"$db\". They use clan_test only (phpunit.xml).");
        }
    }

    protected function seedSample(): void
    {
        $this->seed(SampleClansSeeder::class);
    }

    protected function clan(string $name): Clan
    {
        return Clan::withTrashed()->where('name', $name)->firstOrFail();
    }

    /** Find a sample person by given name (and last name, where names repeat). */
    protected function person(string $given, ?string $last = null, ?string $birth = null): Person
    {
        return Person::unscoped()
            ->where('given_name', $given)
            ->when($last !== null, fn ($q) => $q->where('last_name', $last))
            ->when($birth !== null, fn ($q) => $q->where('birth_date_text', $birth))
            ->firstOrFail();
    }

    /** Pretend the header's clan picker is on this clan (turns the membership scope on). */
    protected function inClan(Clan $clan): void
    {
        app(CurrentClan::class)->set($clan->id);
        $this->withSession(['clan_id' => $clan->id]);
    }
}
