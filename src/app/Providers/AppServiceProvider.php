<?php

namespace App\Providers;

use App\Storage\PulseDatabaseStorage;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Contracts\Storage;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Storage::class, PulseDatabaseStorage::class);
    }
}
