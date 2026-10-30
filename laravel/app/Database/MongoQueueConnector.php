<?php

namespace App\Database;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\DatabaseConnector;

class MongoQueueConnector extends DatabaseConnector
{
    public function connect(array $config): Queue
    {
        $connection = $this->connections->connection($config['connection'] ?? null);
        if (! $connection instanceof MongoConnection) {
            return parent::connect($config);
        }

        return new MongoQueue($connection, $config['table'], $config['queue'], $config['retry_after'] ?? 60, $config['after_commit'] ?? null);
    }
}
