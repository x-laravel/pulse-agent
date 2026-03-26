<?php

use App\Storage\PulseDatabaseStorage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Laravel\Pulse\Contracts\Storage;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create()
    ->tap(function (Application $app) {
        $app->bind(Storage::class, PulseDatabaseStorage::class);
    });
