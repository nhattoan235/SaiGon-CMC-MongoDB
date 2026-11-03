<?php

namespace Tests\Concerns;

use App\Mongo\Schema;
use Illuminate\Support\Facades\DB;
use LogicException;

trait ResetsMongoDatabase
{
    public function setUpResetsMongoDatabase(): void
    {
        $connection = DB::connection('mongodb');
        $name = $connection->getDatabaseName();
        if (! app()->environment('testing') || ! preg_match('/^cmc_mongo_test_[a-zA-Z0-9_]+$/D', $name)) {
            throw new LogicException('Test reset refused: only an explicit cmc_mongo_test_* database may be cleared.');
        }
        Schema::create($connection);
        foreach ($connection->getDatabase()->listCollectionNames() as $collection) {
            $connection->getCollection($collection)->deleteMany([]);
        }
    }
}
