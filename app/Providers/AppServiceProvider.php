<?php

namespace App\Providers;

use App\Support\CurrentClan;
use App\Support\DatabaseGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentClan::class);
    }

    public function boot(): void
    {
        Event::listen(CommandStarting::class, [DatabaseGuard::class, 'handle']);
    }
}
