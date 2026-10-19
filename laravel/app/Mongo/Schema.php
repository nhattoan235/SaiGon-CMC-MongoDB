<?php

namespace App\Mongo;

use MongoDB\Laravel\Connection;

/** Creates the final BSON schema, including native framework stores, without SQL DDL. */
class Schema
{
    /** Closest ICU case/accent behavior to the source config's utf8mb4_unicode_ci. */
    public const COLLATION = ['locale' => 'vi', 'strength' => 1];

    public static function create(Connection $connection): void
    {
        $database = $connection->getDatabase();
        $existing = iterator_to_array($database->listCollectionNames());
        foreach (DocumentStore::schema() as $logical => $schema) {
            if (isset($schema['parent'])) {
                continue;
            }
            $validator = self::validator($logical, $schema);
            if (! in_array($schema['collection'], $existing, true)) {
                $database->createCollection($schema['collection'], ['validator' => ['$jsonSchema' => $validator]]);
            } else {
                $database->command(['collMod' => $schema['collection'], 'validator' => ['$jsonSchema' => $validator]])->toArray();
            }
            $collection = $connection->getCollection($schema['collection']);
            foreach ($schema['indexes'] as $index) {
                $keys = [];
                foreach ($index['fields'] as $field) {
                    $keys[$field === $schema['primary'] ? '_id' : $field] = 1;
                }
                $options = ['unique' => $index['unique']];
                if (isset($index['non_null'])) {
                    foreach ($index['non_null'] as $field) {
                        $options['partialFilterExpression'][$field] = ['$type' => $schema['fields'][$field]['type'] === 'int' ? 'number' : 'string'];
                    }
                }
                if ($index['unique'] && ! in_array($logical, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true) && array_filter($index['fields'], fn (string $field): bool => $schema['fields'][$field]['type'] === 'string')) {
                    $options['collation'] = self::COLLATION;
                    // A separate name allows safe installation beside an existing binary unique index.
                    $options['name'] = 'cmc_ci_'.implode('_', $index['fields']).'_unique';
                }
                $collection->createIndex($keys, $options);
            }
            if (in_array($logical, ['sessions', 'cache', 'cache_locks'], true)) {
                $collection->createIndex(['expires_at' => 1], ['expireAfterSeconds' => 0]);
            }
        }
        foreach (DocumentStore::schema() as $logical => $schema) {
            if (! isset($schema['parent'])) {
                continue;
            }
            $connection->getCollection($schema['collection'])->createIndex(
                ['items.item_id' => 1], ['unique' => true, 'partialFilterExpression' => ['items.item_id' => ['$exists' => true]]],
            );
            foreach ($schema['foreign_keys'] as $foreign) {
                if ($foreign['from'] !== $schema['parent']) {
                    $connection->getCollection($schema['collection'])->createIndex(['items.'.$foreign['from'] => 1]);
                }
            }
        }
        if (! in_array('_counters', $existing, true)) {
            $database->createCollection('_counters');
        }
    }

    private static function validator(string $logical, array $schema): array
    {
        // Native framework drivers own their BSON layouts (binary sessions, dated TTLs and ObjectId batches).
        if (in_array($logical, ['sessions', 'cache', 'cache_locks', 'job_batches'], true)) {
            return ['bsonType' => 'object'];
        }
        $properties = [];
        $required = [];
        foreach ($schema['fields'] as $field => $definition) {
            if (isset($schema['parent']) && $field === $schema['parent']) {
                continue;
            }
            $key = $field === $schema['primary'] ? (isset($schema['parent']) ? 'item_id' : '_id') : $field;
            $types = match ($definition['type']) {
                'int' => ['int', 'long'],
                'bool' => ['bool'],
                'decimal' => ['decimal'],
                'datetime' => ['date'],
                'json' => ['object', 'array'],
                default => ['string'],
            };
            if ($definition['nullable']) {
                $types[] = 'null';
            } else {
                $required[] = $key;
            }
            $properties[$key] = ['bsonType' => $types];
        }
        if (! isset($schema['parent'])) {
            foreach (DocumentStore::schema() as $child => $definition) {
                if (isset($definition['parent']) && $definition['collection'] === $schema['collection']) {
                    $properties['items'] = ['bsonType' => 'array', 'items' => self::validator($child, $definition)];
                }
            }
        }

        return ['bsonType' => 'object', 'required' => $required, 'properties' => $properties];
    }
}
