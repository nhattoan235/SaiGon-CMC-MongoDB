<?php

namespace App\Database;

use Illuminate\Queue\DatabaseQueue;

class MongoQueue extends DatabaseQueue
{
    protected function getLockForPopping(): bool
    {
        return true;
    }
}
