<?php

namespace App\Mongo;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Connection;
use MongoDB\Operation\FindOneAndUpdate;

/** Maps the application's finite logical tables onto referenced documents and owned item arrays. */
class DocumentStore
{
    private static ?array $schema = null;

    public function __construct(private Connection $connection) {}

    public static function schema(?string $logical = null): array
    {
        self::$schema ??= require dirname(__DIR__, 2).'/config/mongodb-schema.php';
        if ($logical !== null && ! isset(self::$schema[$logical])) {
            throw new InvalidArgumentException('Unknown application collection: '.$logical);
        }

        return $logical === null ? self::$schema : self::$schema[$logical];
    }

    public static function source(string $logical): array
    {
        $schema = self::schema($logical);
        $pipeline = isset($schema['parent']) ? [
            ['$unwind' => '$items'],
            ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [
                '$items', ['id' => '$items.item_id', $schema['parent'] => '$_id'],
            ]]]],
        ] : [['$set' => [$schema['primary'] => '$_id']]];

        return ['collection' => $schema['collection'], 'pipeline' => $pipeline];
    }

    public static function normalize(string $logical, array|object $row): object
    {
        $schema = self::schema($logical);
        $values = (array) $row;
        $result = [];
        foreach ($values as $field => $value) {
            if (str_starts_with($field, '_search_') || in_array($field, ['_lock_version', 'item_id', 'items'], true)) {
                continue;
            }
            if ($field === '_id') {
                if (array_key_exists($schema['primary'], $values)) {
                    continue;
                }
                $field = $schema['primary'];
            }
            $definition = $schema['fields'][$field] ?? [];
            if ($value instanceof Decimal128) {
                $value = (string) $value;
                if (isset($definition['scale'])) {
                    $value = bcadd($value, '0', $definition['scale']);
                }
            } elseif ($value instanceof UTCDateTime) {
                $value = Carbon::instance($value->toDateTime())->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
            } elseif (($definition['type'] ?? null) === 'json' && $value !== null && ! is_string($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            $result[$field] = $value;
        }

        return (object) $result;
    }

    public function encode(string $logical, array $values, bool $defaults = false): array
    {
        $schema = self::schema($logical);
        if ($defaults) {
            foreach ($schema['fields'] as $field => $definition) {
                if (! array_key_exists($field, $values) && array_key_exists('default', $definition)) {
                    $values[$field] = $definition['default'] === '@now' ? now() : $definition['default'];
                }
            }
        }
        $encoded = [];
        foreach ($values as $field => $value) {
            $definition = $schema['fields'][$field] ?? null;
            if ($definition !== null && $value !== null) {
                $value = $this->encodeValue($definition, $value);
            }
            $key = $field === $schema['primary'] ? (isset($schema['parent']) ? 'item_id' : '_id') : $field;
            $encoded[$key] = $value;
            if (in_array($field, ['name', 'code'], true) && is_string($value)) {
                $encoded['_search_'.$field] = Str::lower(Str::ascii($value));
            }
        }

        return $encoded;
    }

    private function encodeValue(array $definition, mixed $value): mixed
    {
        return match ($definition['type']) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            'decimal' => $this->decimal($value, $definition),
            'datetime' => $value instanceof UTCDateTime ? $value : new UTCDateTime($value instanceof DateTimeInterface ? $value : Carbon::parse($value, config('app.timezone', 'UTC'))),
            'date' => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10),
            'json' => is_string($value) ? json_decode($value, false, 512, JSON_THROW_ON_ERROR) : $value,
            default => (string) $value,
        };
    }

    private function decimal(mixed $value, array $definition): Decimal128
    {
        $value = (string) $value;
        if (! preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            throw new InvalidArgumentException('An exact decimal string is required.');
        }
        $scale = $definition['scale'];
        $value = bcadd($value, '0', $scale);
        $integer = ltrim(explode('.', ltrim($value, '-'))[0], '0');
        if (strlen($integer) > $definition['precision'] - $scale) {
            throw new InvalidArgumentException('Decimal value exceeds its declared precision.');
        }

        return new Decimal128($value);
    }

    public function options(): array
    {
        $session = $this->connection->getSession();

        return $session !== null && $session->isInTransaction() ? ['session' => $session] : [];
    }

    private function operationOptions(string $logical): array
    {
        $options = $this->options();
        if (! in_array($logical, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true)) {
            $options['collation'] = Schema::COLLATION;
        }

        return $options;
    }

    public function nextId(string $logical): int
    {
        $this->schema($logical);
        $row = $this->connection->getCollection('_counters')->findOneAndUpdate(
            ['_id' => $logical], ['$inc' => ['value' => 1]],
            $this->options() + ['upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER],
        );

        return (int) $row['value'];
    }

    public function insert(string $logical, array $values): int|string
    {
        $schema = self::schema($logical);
        $primary = $schema['primary'];
        if (! isset($values[$primary]) && ! isset($values['_id'])) {
            if (! $schema['numeric']) {
                throw new InvalidArgumentException('Missing natural key '.$logical.'.'.$primary);
            }
            $values[$primary] = $this->nextId($logical);
        } elseif ($schema['numeric']) {
            $this->connection->getCollection('_counters')->updateOne(
                ['_id' => $logical], ['$max' => ['value' => (int) ($values[$primary] ?? $values['_id'])]],
                $this->operationOptions($logical) + ['upsert' => true],
            );
        }
        $encoded = $this->encode($logical, $values, true);
        $id = $encoded[isset($schema['parent']) ? 'item_id' : '_id'];
        $collection = $this->connection->getCollection($schema['collection']);
        if (isset($schema['parent'])) {
            $parentId = $encoded[$schema['parent']] ?? null;
            unset($encoded[$schema['parent']]);
            $result = $collection->updateOne(
                ['_id' => $parentId, 'items.item_id' => ['$ne' => $id]],
                ['$push' => ['items' => $encoded]], $this->operationOptions($logical),
            );
            if ($result->getMatchedCount() !== 1) {
                throw new LogicException('Missing parent or duplicate embedded item ID for '.$logical);
            }
        } else {
            $collection->insertOne($encoded, $this->operationOptions($logical));
        }

        return $id;
    }

    /** Allocate IDs and serialize explicit ID reuse on the same collection counter. */
    private function reserveId(string $logical, array &$values): void
    {
        $schema = self::schema($logical);
        $primary = $schema['primary'];
        if (! isset($values[$primary])) {
            if (! $schema['numeric']) {
                throw new InvalidArgumentException('Missing natural key '.$logical.'.'.$primary);
            }
            $values[$primary] = $this->nextId($logical);
        } elseif ($schema['numeric']) {
            $values[$primary] = (int) $values[$primary];
            $this->connection->getCollection('_counters')->updateOne(
                ['_id' => $logical], ['$max' => ['value' => $values[$primary]], '$inc' => ['_lock_version' => 1]],
                $this->options() + ['upsert' => true],
            );
        }
    }

    private function atomic(callable $callback): mixed
    {
        return $this->options() === [] ? $this->connection->transaction($callback, attempts: 5) : $callback();
    }

    /** A unique-key upsert keeps an ignored duplicate from aborting the active transaction. */
    public function insertOrIgnore(string $logical, array $values): int
    {
        return $this->atomic(function () use ($logical, $values): int {
            $schema = self::schema($logical);
            $identities = [];
            if (isset($values[$schema['primary']])) {
                $identities[] = [$schema['primary'] => $values[$schema['primary']]];
            }
            foreach ($schema['indexes'] as $index) {
                if ($index['unique'] && count(array_filter($index['fields'], fn (string $field): bool => isset($values[$field]))) === count($index['fields'])) {
                    $identities[] = array_intersect_key($values, array_flip($index['fields']));
                }
            }
            $this->reserveId($logical, $values);
            $encoded = $this->encode($logical, $values, true);
            $collection = $this->connection->getCollection($schema['collection']);
            if (isset($schema['parent'])) {
                $parentId = $encoded[$schema['parent']] ?? null;
                unset($encoded[$schema['parent']]);
                if ($collection->findOne(['items.item_id' => $encoded['item_id']], $this->operationOptions($logical)) !== null) {
                    return 0;
                }
                $result = $collection->updateOne(
                    ['_id' => $parentId, 'items.item_id' => ['$ne' => $encoded['item_id']]],
                    ['$push' => ['items' => $encoded]], $this->operationOptions($logical),
                );
                if ($result->getMatchedCount() !== 1) {
                    throw new LogicException('Missing parent for '.$logical);
                }

                return $result->getModifiedCount();
            }
            $identities = $identities ?: [[$schema['primary'] => $values[$schema['primary']]]];
            $filter = count($identities) === 1 ? $identities[0] : ['$or' => $identities];
            $result = $collection->updateOne(
                $this->rootFilter($logical, $filter), ['$setOnInsert' => $encoded],
                $this->operationOptions($logical) + ['upsert' => true],
            );

            return $result->getUpsertedCount();
        });
    }

    public function updateOrInsert(string $logical, array $attributes, array $values): bool
    {
        return $this->atomic(function () use ($logical, $attributes, $values): bool {
            $schema = self::schema($logical);
            $insert = $attributes + $values;
            $this->reserveId($logical, $insert);
            if (isset($schema['parent'])) {
                if (! isset($attributes[$schema['parent']], $attributes[$schema['primary']])) {
                    throw new LogicException('Owned item upserts require the parent and stable item ID.');
                }
                $match = iterator_to_array($this->selectedItems($logical, $attributes));
                if ($match !== []) {
                    return $this->update($logical, $attributes, $values) > 0;
                }
                $this->insert($logical, $insert);

                return true;
            }
            $patch = $this->encode($logical, $values);
            unset($patch['_id']);
            $defaults = array_diff_key($this->encode($logical, $insert, true), $patch);
            $update = ['$setOnInsert' => $defaults];
            if ($patch !== []) {
                $update['$set'] = $patch;
            }
            $this->connection->getCollection($schema['collection'])->updateOne(
                $this->rootFilter($logical, $attributes), $update, $this->operationOptions($logical) + ['upsert' => true],
            );

            return true;
        });
    }

    /** Convert logical predicates for physical root updates; embedded predicates run on the virtual source. */
    private function rootFilter(string $logical, array $filter, bool $physical = true): array
    {
        $schema = self::schema($logical);
        $result = [];
        foreach ($filter as $field => $value) {
            if (in_array($field, ['$and', '$or', '$nor'], true)) {
                $result[$field] = array_map(fn (array $part): array => $this->rootFilter($logical, $part, $physical), $value);

                continue;
            }
            $key = $physical && $field === $schema['primary'] ? '_id' : $field;
            if (isset($schema['fields'][$field])) {
                $definition = $schema['fields'][$field];
                $convert = fn (mixed $scalar): mixed => $scalar === null ? null : $this->encodeValue($definition, $scalar);
                if (is_array($value)) {
                    foreach ($value as $operator => $operand) {
                        if (in_array($operator, ['$in', '$nin'], true)) {
                            $value[$operator] = array_map($convert, $operand);
                        } elseif (in_array($operator, ['$eq', '$ne', '$gt', '$gte', '$lt', '$lte'], true)) {
                            $value[$operator] = $convert($operand);
                        }
                    }
                } else {
                    $value = $convert($value);
                }
            }
            $result[$key] = $value;
        }

        return $result;
    }

    private function selectedItems(string $logical, array $filter): iterable
    {
        $source = self::source($logical);

        return $this->connection->getCollection($source['collection'])->aggregate(
            [...$source['pipeline'], ['$match' => $this->rootFilter($logical, $filter, false)]], $this->operationOptions($logical),
        );
    }

    public function update(string $logical, array $filter, array $changes): int
    {
        $schema = self::schema($logical);
        $collection = $this->connection->getCollection($schema['collection']);
        $operators = isset($changes['$set']) || isset($changes['$inc']) || isset($changes['$unset']) ? $changes : ['$set' => $changes];
        foreach ($operators as $operator => $values) {
            $operators[$operator] = $operator === '$unset' ? $values : $this->encode($logical, $values);
            unset($operators[$operator]['_id'], $operators[$operator]['item_id']);
        }
        if (! isset($schema['parent'])) {
            return $collection->updateMany($this->rootFilter($logical, $filter), $operators, $this->operationOptions($logical))->getMatchedCount();
        }
        $count = 0;
        foreach ($this->selectedItems($logical, $filter) as $item) {
            $item = (array) $item;
            $update = [];
            foreach ($operators as $operator => $values) {
                foreach ($values as $field => $value) {
                    if ($field !== $schema['parent']) {
                        $update[$operator]['items.$[line].'.$field] = $value;
                    }
                }
            }
            $count += $collection->updateOne(
                ['_id' => $item[$schema['parent']]], $update,
                $this->operationOptions($logical) + ['arrayFilters' => [['line.item_id' => $item['id']]]],
            )->getMatchedCount();
        }

        return $count;
    }

    public function delete(string $logical, array $filter): int
    {
        $schema = self::schema($logical);
        $collection = $this->connection->getCollection($schema['collection']);
        if (! isset($schema['parent'])) {
            return $collection->deleteMany($this->rootFilter($logical, $filter), $this->operationOptions($logical))->getDeletedCount();
        }
        $count = 0;
        foreach ($this->selectedItems($logical, $filter) as $item) {
            $item = (array) $item;
            $count += $collection->updateOne(
                ['_id' => $item[$schema['parent']]], ['$pull' => ['items' => ['item_id' => $item['id']]]], $this->operationOptions($logical),
            )->getModifiedCount();
        }

        return $count;
    }

    public function lock(string $logical, array $filter): void
    {
        if ($this->options() === []) {
            throw new LogicException('A shared document guard requires an active transaction.');
        }
        $schema = self::schema($logical);
        $collection = $this->connection->getCollection($schema['collection']);
        if (! isset($schema['parent'])) {
            $collection->updateMany($this->rootFilter($logical, $filter), ['$inc' => ['_lock_version' => 1]], $this->operationOptions($logical));

            return;
        }
        foreach ($this->selectedItems($logical, $filter) as $item) {
            $collection->updateOne(
                ['_id' => $item[$schema['parent']]], ['$inc' => ['_lock_version' => 1]], $this->operationOptions($logical),
            );
        }
    }
}
