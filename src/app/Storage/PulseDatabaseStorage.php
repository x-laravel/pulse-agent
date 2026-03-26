<?php

namespace App\Storage;

use Laravel\Pulse\Storage\DatabaseStorage;

/**
 * Oracle Cloud MySQL does not support md5() in generated columns.
 * Always compute key_hash in PHP instead of relying on a generated column.
 */
class PulseDatabaseStorage extends DatabaseStorage
{
    protected function requiresManualKeyHash(): bool
    {
        return true;
    }
}
