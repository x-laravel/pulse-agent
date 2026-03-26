<?php

use App\Storage\PulseDatabaseStorage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Laravel\Pulse\Contracts\Storage;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();

$app->bind(Storage::class, PulseDatabaseStorage::class);

return $app;
