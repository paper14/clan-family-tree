<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Only ever run against the demo or test database (php artisan app:demo). Seeding the
 * registry is refused by the database guard in AppServiceProvider.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SampleClansSeeder::class);
    }
}
