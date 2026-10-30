<?php

namespace App\Database;

use Illuminate\Database\Schema\Builder;

class MongoSchema extends Builder
{
    public function getTables($schema = null): array
    {
        return array_map(fn ($name) => ['name' => $name, 'schema' => $this->connection->getDatabaseName(), 'schema_qualified_name' => $name, 'size' => 0, 'comment' => null, 'engine' => 'MongoDB', 'collation' => 'vi'], array_keys($this->connection->definitions));
    }

    public function getTableListing($schema = null, $schemaQualified = true): array
    {
        return array_keys($this->connection->definitions);
    }

    public function hasTable($table): bool
    {
        return isset($this->connection->definitions[$table]);
    }

    public function getColumns($table): array
    {
        return $this->connection->definitions[$table]['columns'] ?? [];
    }

    public function getIndexes($table): array
    {
        return $this->connection->definitions[$table]['indexes'] ?? [];
    }

    public function getForeignKeys($table): array
    {
        return $this->connection->definitions[$table]['foreign_keys'] ?? [];
    }

    public function disableForeignKeyConstraints(): bool
    {
        $this->connection->constraintsDisabled = true;

        return true;
    }

    public function enableForeignKeyConstraints(): bool
    {
        $this->connection->constraintsDisabled = false;

        return true;
    }
}
