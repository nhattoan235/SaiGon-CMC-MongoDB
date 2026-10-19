<?php

namespace App\Mongo;

use Closure;
use LogicException;
use MongoDB\Laravel\Connection as NativeConnection;

/** Keep native Eloquent/session support and adapt only legacy DB::table queries. */
class Connection extends NativeConnection
{
    protected function run($query, $bindings, Closure $callback): never
    {
        throw new LogicException('SQL execution is not supported by the MongoDB connection.');
    }

    public function transaction(Closure $callback, $attempts = 5, array $options = []): mixed
    {
        return parent::transaction($callback, $attempts, $options);
    }

    public function table($table, $as = null): Builder
    {
        if ($as !== null) {
            throw new LogicException('Root table aliases are outside the application query vocabulary.');
        }

        return (new Builder($this, $this->getQueryGrammar(), $this->getPostProcessor()))->from($table);
    }
}
