<?php

namespace App\Database;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use MongoDB\BSON\Decimal128;
use RuntimeException;

class MongoQuery extends Builder
{
    private array $subJoins = [];

    private array $existsFields = [];

    public function source(): array
    {
        $parts = preg_split('/\s+as\s+/i', (string) $this->from);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }

    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false): static
    {
        if ($query instanceof Closure) {
            $query($query = $this->newQuery());
        }
        $this->subJoins[$as] = $query;

        return $this->join($as, $first, $operator, $second, $type, $where);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false): static
    {
        if ($values instanceof Closure) {
            $values($values = $this->newQuery());
        }
        if ($values instanceof Builder) {
            $values = $values->get()->map(fn ($row) => array_values((array) $row)[0])->all();
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    protected function runSelect(): array
    {
        $this->applyBeforeQueryCallbacks();
        [$table] = $this->source();
        $rows = $this->connection->mongo->selectCollection($table)->aggregate($this->pipeline(), $this->connection->options() + [
            'collation' => ['locale' => 'vi', 'strength' => 1],
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ])->toArray();

        return array_map(fn ($row) => (object) $this->connection->decode($row, $table), $rows);
    }

    public function pipeline(array $outer = []): array
    {
        [$table, $alias] = $this->source();
        $aliases = [$alias => $table];
        $pipeline = [['$set' => ['__base' => '$$ROOT', $alias => '$$ROOT']]];
        foreach ($this->joins ?? [] as $join) {
            $parts = preg_split('/\s+as\s+/i', (string) $join->table);
            $foreign = $parts[0];
            $name = $parts[1] ?? $foreign;
            $sub = $this->subJoins[$name] ?? null;
            $clauses = [];
            foreach ($join->wheres as $where) {
                if ($where['type'] !== 'Column') {
                    throw new RuntimeException('Điều kiện lookup chưa được hỗ trợ.');
                }
                $sides = [];
                foreach ([$where['first'], $where['second']] as $field) {
                    $sides[] = str_starts_with($field, $name.'.')
                        ? '$'.substr($field, strlen($name) + 1)
                        : '$$outer.'.ltrim($this->field($field, $aliases), '$');
                }
                $clauses[] = ['$and' => [['$ne' => [$sides[0], null]], ['$ne' => [$sides[1], null]], [$this->operator($where['operator']) => $sides]]];
            }
            $stages = $sub ? $sub->pipeline() : [];
            $stages[] = ['$match' => ['$expr' => ['$and' => $clauses]]];
            $pipeline[] = ['$lookup' => ['from' => $sub ? $sub->source()[0] : $foreign, 'let' => ['outer' => '$$ROOT'], 'pipeline' => $stages, 'as' => $name]];
            $pipeline[] = ['$unwind' => ['path' => '$'.$name, 'preserveNullAndEmptyArrays' => $join->type === 'left']];
            $aliases[$name] = $sub ? $sub->source()[0] : $foreign;
        }
        $this->prepareExists($this->wheres ?? [], $pipeline, $aliases);
        if ($this->wheres) {
            $pipeline[] = ['$match' => ['$expr' => $this->predicates($this->wheres, $aliases, $outer)]];
        }
        $grouped = $this->groups || $this->aggregate;
        $columns = $this->columns ?? ['*'];
        if ($grouped) {
            $group = ['_id' => null];
            $projection = ['_id' => 0];
            foreach ($this->groups ?? [] as $i => $column) {
                $group['_id']['g'.$i] = $this->field($column, $aliases);
                $projection[$this->outputName($column)] = '$_id.g'.$i;
            }
            if ($this->aggregate) {
                $function = strtolower($this->aggregate['function']);
                $column = $this->aggregate['columns'][0];
                $group['aggregate'] = $function === 'count' ? ['$sum' => 1] : ['$'.$function => $this->field($column, $aliases)];
                $projection = ['_id' => 0, 'aggregate' => 1];
            } else {
                foreach ($columns as $column) {
                    if (! $column instanceof Expression) {
                        continue;
                    }
                    foreach (preg_split('/,(?![^()]*\))/', $column->getValue($this->grammar)) as $expression) {
                        if (! preg_match('/^\s*(SUM|COUNT|MAX|MIN|AVG)\(([^)]+)\)\s+as\s+(\w+)\s*$/i', $expression, $match)) {
                            throw new RuntimeException('Phép tổng hợp chưa được hỗ trợ: '.$expression);
                        }
                        $group[$match[3]] = strtolower($match[1]) === 'count' ? ['$sum' => 1] : ['$'.strtolower($match[1]) => $this->field($match[2], $aliases)];
                        $projection[$match[3]] = 1;
                    }
                }
            }
            $pipeline[] = ['$group' => $group];
            $pipeline[] = ['$project' => $projection];
        }
        if ($this->orders) {
            $sort = [];
            foreach ($this->orders as $i => $order) {
                if (($order['type'] ?? null) === 'Raw') {
                    if (! preg_match('/^COALESCE\((\w+),\s*(\w+)\)(?:\s+(ASC|DESC))?$/i', $order['sql'], $match)) {
                        throw new RuntimeException('Biểu thức sắp xếp chưa được hỗ trợ.');
                    }
                    $field = '_sort'.$i;
                    $pipeline[] = ['$set' => [$field => ['$ifNull' => [$this->field($match[1], $aliases), $this->field($match[2], $aliases)]]]];
                    $sort[$field] = strtoupper($match[3] ?? 'ASC') === 'DESC' ? -1 : 1;

                    continue;
                }
                $sort[$grouped ? $this->outputName($order['column']) : ltrim($this->field($order['column'], $aliases), '$')] = $order['direction'] === 'asc' ? 1 : -1;
            }
            $pipeline[] = ['$sort' => $sort];
        }
        if (! $grouped) {
            $parts = [];
            $project = [];
            foreach ($columns as $column) {
                if ($column instanceof Expression) {
                    $text = $column->getValue($this->grammar);
                    if (preg_match('/^COALESCE\(([^,]+),\s*(\d+)\)\s+as\s+(\w+)$/i', $text, $match)) {
                        $project[$match[3]] = ['$ifNull' => [$this->field($match[1], $aliases), (int) $match[2]]];
                    } elseif ($text !== '1') {
                        throw new RuntimeException('Projection chưa được hỗ trợ: '.$text);
                    }
                } elseif ($column === '*') {
                    $parts[] = '$__base';
                    foreach (array_keys($aliases) as $name) {
                        if ($name !== $alias) {
                            $parts[] = '$'.$name;
                        }
                    }
                } elseif (str_ends_with($column, '.*')) {
                    $parts[] = '$'.substr($column, 0, -2);
                } else {
                    $tokens = preg_split('/\s+as\s+/i', $column);
                    $project[$tokens[1] ?? $this->outputName($tokens[0])] = ['$ifNull' => [$this->field($tokens[0], $aliases), null]];
                }
            }
            if ($project !== []) {
                $parts[] = $project;
            }
            $pipeline[] = ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => $parts ?: [(object) []]]]];
        }
        if ($this->distinct) {
            $pipeline[] = ['$group' => ['_id' => '$$ROOT']];
            $pipeline[] = ['$replaceRoot' => ['newRoot' => '$_id']];
        }
        if ($this->offset) {
            $pipeline[] = ['$skip' => $this->offset];
        }
        if ($this->limit !== null) {
            $pipeline[] = ['$limit' => max(1, $this->limit)];
        }

        return $pipeline;
    }

    private function prepareExists(array $wheres, array &$pipeline, array $aliases): void
    {
        foreach ($wheres as $where) {
            if ($where['type'] === 'Nested') {
                $this->prepareExists($where['query']->wheres, $pipeline, $aliases);
            } elseif (in_array($where['type'], ['Exists', 'NotExists'], true)) {
                $name = '_exists_'.count($this->existsFields);
                $this->existsFields[spl_object_id($where['query'])] = $name;
                $stages = $where['query']->pipeline($aliases);
                $stages[] = ['$limit' => 1];
                $pipeline[] = ['$lookup' => ['from' => $where['query']->source()[0], 'let' => ['outer' => '$$ROOT'], 'pipeline' => $stages, 'as' => $name]];
            }
        }
    }

    private function field(string $column, array $aliases, array $outer = []): string
    {
        $column = str_replace('`', '', trim($column));
        $prefix = explode('.', $column)[0];
        if (str_contains($column, '.') && isset($outer[$prefix]) && ! isset($aliases[$prefix])) {
            return '$$outer.'.$column;
        }

        return '$'.$column;
    }

    private function outputName(string $column): string
    {
        return Str::afterLast(str_replace('`', '', $column), '.');
    }

    private function operator(string $operator): string
    {
        return match (strtolower($operator)) {
            '=' => '$eq', '!=', '<>' => '$ne', '>' => '$gt', '>=' => '$gte', '<' => '$lt', '<=' => '$lte',
            default => throw new RuntimeException('Toán tử chưa được hỗ trợ: '.$operator),
        };
    }

    private function literal(mixed $value): array
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        return ['$literal' => $value];
    }

    private function coerce(string $column, mixed $value, array $aliases): mixed
    {
        [$table] = $this->source();
        if (str_contains($column, '.')) {
            [$prefix, $column] = explode('.', $column, 2);
            $table = $aliases[$prefix] ?? $table;
        }
        $definition = $this->connection->columns($table)[$column] ?? [];
        $type = $definition['type_name'] ?? null;
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value !== null && in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true) && (is_numeric($value) || is_bool($value))) {
            return (int) $value;
        }
        if ($value !== null && $type === 'decimal' && is_numeric($value)) {
            return new Decimal128((string) $value);
        }

        return $value;
    }

    private function predicates(array $wheres, array $aliases, array $outer = []): array
    {
        $groups = [[]];
        foreach ($wheres as $where) {
            $where['type'] = ucfirst($where['type']);
            $column = $where['column'] ?? '';
            $field = $column ? ['$ifNull' => [$this->field((string) $column, $aliases, $outer), null]] : null;
            $value = $this->coerce((string) $column, $where['value'] ?? null, $aliases);
            $expr = match ($where['type']) {
                'Nested' => $this->predicates($where['query']->wheres ?? [], $aliases, $outer),
                'Null' => ['$eq' => [$field, null]],
                'NotNull' => ['$ne' => [$field, null]],
                'Column' => ['$and' => [
                    ['$ne' => [$this->field($where['first'], $aliases, $outer), null]],
                    ['$ne' => [$this->field($where['second'], $aliases, $outer), null]],
                    [$this->operator($where['operator']) => [$this->field($where['first'], $aliases, $outer), $this->field($where['second'], $aliases, $outer)]],
                ]],
                'Exists', 'NotExists' => [($where['type'] === 'Exists' ? '$gt' : '$eq') => [['$size' => '$'.$this->existsFields[spl_object_id($where['query'])]], 0]],
                'In', 'NotIn', 'InRaw', 'NotInRaw' => $this->inPredicate($where, $field, $aliases),
                'Between', 'NotBetween' => $this->betweenPredicate($where, $field, $aliases),
                'Date', 'Year', 'Month', 'Day', 'Time' => [$this->operator($where['operator']) => [
                    ['$substrCP' => [['$ifNull' => [$this->field($column, $aliases), '']], match ($where['type']) {
                        'Year', 'Date' => 0, 'Month' => 5, 'Day' => 8, 'Time' => 11
                    }, match ($where['type']) {
                        'Year' => 4, 'Date' => 10, 'Month', 'Day' => 2, 'Time' => 8
                    }]],
                    $this->literal((string) $value),
                ]],
                'Basic' => $this->basicPredicate($where, $field, $value, $aliases),
                default => throw new RuntimeException('Điều kiện lọc MongoDB chưa hỗ trợ: '.$where['type']),
            };
            if (($where['boolean'] ?? 'and') === 'or' && end($groups) !== []) {
                $groups[] = [];
            }
            $groups[array_key_last($groups)][] = $expr;
        }

        return ['$or' => array_map(fn ($group) => ['$and' => $group ?: [true]], $groups)];
    }

    private function basicPredicate(array $where, mixed $field, mixed $value, array $aliases): array
    {
        $op = strtolower($where['operator']);
        if (in_array($op, ['like', 'not like'], true)) {
            $column = (string) $where['column'];
            $prefix = str_contains($column, '.') ? Str::beforeLast($column, '.').'._search.' : '_search.';
            $search = '$'.$prefix.$this->outputName($column);
            $pattern = '^'.str_replace(['%', '_'], ['.*', '.'], preg_quote(Str::lower(Str::ascii((string) $value)), '/')).'$';
            $regex = ['$regexMatch' => ['input' => ['$ifNull' => [$search, '']], 'regex' => $pattern, 'options' => 'i']];

            return ['$and' => [['$ne' => [$field, null]], $op === 'like' ? $regex : ['$not' => [$regex]]]];
        }

        return ['$and' => [['$ne' => [$field, null]], [$this->operator($op) => [$field, $this->literal($value)]]]];
    }

    private function inPredicate(array $where, mixed $field, array $aliases): array
    {
        $values = array_map(fn ($value) => $this->coerce((string) $where['column'], $value, $aliases), $where['values']);
        $not = str_starts_with($where['type'], 'Not');
        if ($not && in_array(null, $values, true)) {
            return ['$literal' => false];
        }
        $expr = ['$in' => [$field, $this->literal(array_values(array_filter($values, fn ($value) => $value !== null)))]];

        return ['$and' => [['$ne' => [$field, null]], $not ? ['$not' => [$expr]] : $expr]];
    }

    private function betweenPredicate(array $where, mixed $field, array $aliases): array
    {
        $expr = ['$and' => [
            ['$gte' => [$field, $this->literal($this->coerce((string) $where['column'], $where['values'][0], $aliases))]],
            ['$lte' => [$field, $this->literal($this->coerce((string) $where['column'], $where['values'][1], $aliases))]],
        ]];

        return ['$and' => [['$ne' => [$field, null]], ($where['not'] ?? false) ? ['$not' => [$expr]] : $expr]];
    }

    public function exists(): bool
    {
        return (clone $this)->limit(1)->get()->isNotEmpty();
    }

    public function getCountForPagination($columns = ['*']): int
    {
        return $this->cloneWithout(['orders', 'limit', 'offset'])->get()->count();
    }

    public function cursor(): LazyCollection
    {
        return LazyCollection::make(fn () => yield from $this->get());
    }

    public function insert(array $values): bool
    {
        if ($values === []) {
            return true;
        }
        [$table] = $this->source();
        $values = array_is_list($values) ? $values : [$values];

        return $this->connection->write($table, function () use ($table, $values) {
            $documents = [];
            $key = $this->connection->key($table);
            foreach ($values as $row) {
                $supplied = isset($row[$key]);
                if (! isset($row[$key])) {
                    $row[$key] = $this->connection->nextId($table);
                }
                $document = Arr::undot($this->connection->encode($table, $row, true));
                if ($supplied && is_int($document[$key])) {
                    $this->connection->mongo->selectCollection('_cmc_sequences')->updateOne(['_id' => $table], ['$max' => ['value' => $document[$key]]], $this->connection->options() + ['upsert' => true]);
                }
                $document['_id'] = $document[$key];
                $documents[] = $document;
            }
            $this->connection->mongo->selectCollection($table)->insertMany($documents, $this->connection->options());
            $this->connection->logQuery('mongodb.insert '.$table, []);

            return true;
        });
    }

    public function insertGetId(array $values, $sequence = null): int|string
    {
        [$table] = $this->source();

        return $this->connection->write($table, function () use ($table, $values) {
            $key = $this->connection->key($table);
            $values[$key] ??= $this->connection->nextId($table);
            $this->insert($values);

            return $values[$key];
        });
    }

    public function insertOrIgnore(array $values): int
    {
        $count = 0;
        foreach (array_is_list($values) ? $values : [$values] as $row) {
            [$table] = $this->source();
            $count += $this->connection->write($table, function () use ($row, $table) {
                foreach ($this->connection->definitions[$table]['indexes'] as $index) {
                    if (! $index['unique'] || count(array_filter($index['columns'], fn ($name) => isset($row[$name]))) !== count($index['columns'])) {
                        continue;
                    }
                    if ($this->newQuery()->from($table)->where(Arr::only($row, $index['columns']))->exists()) {
                        return 0;
                    }
                }
                $this->insert($row);

                return 1;
            });
        }

        return $count;
    }

    public function update(array $values): int
    {
        [$table] = $this->source();

        return $this->connection->write($table, function () use ($table, $values) {
            $key = $this->connection->key($table);
            $ids = (clone $this)->pluck($key)->all();
            if ($ids === []) {
                return 0;
            }

            return $this->connection->mongo->selectCollection($table)->updateMany([$key => ['$in' => $ids]], ['$set' => $this->connection->encode($table, $values)], $this->connection->options())->getModifiedCount();
        });
    }

    public function delete($id = null): int
    {
        if ($id !== null) {
            $this->where('id', $id);
        }
        [$table] = $this->source();

        return $this->connection->write($table, function () use ($table) {
            $key = $this->connection->key($table);
            $rows = $this->get()->all();
            if (! $this->connection->constraintsDisabled) {
                foreach ($this->connection->definitions as $child => $definition) {
                    foreach ($definition['foreign_keys'] as $foreign) {
                        if ($foreign['foreign_table'] !== $table) {
                            continue;
                        }
                        foreach ($rows as $row) {
                            $query = $this->connection->table($child);
                            foreach ($foreign['columns'] as $i => $column) {
                                $query->where($column, $row->{$foreign['foreign_columns'][$i]});
                            }
                            if (strtolower($foreign['on_delete']) === 'cascade') {
                                $query->delete();
                            } elseif (strtolower($foreign['on_delete']) === 'set null') {
                                $query->update(array_fill_keys($foreign['columns'], null));
                            }
                        }
                    }
                }
            }

            return $this->connection->mongo->selectCollection($table)->deleteMany([$key => ['$in' => array_map(fn ($row) => $row->$key, $rows)]], $this->connection->options())->getDeletedCount();
        });
    }

    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        $count = 0;
        foreach (array_is_list($values) ? $values : [$values] as $row) {
            $query = $this->newQuery()->from($this->from);
            $query->updateOrInsert(Arr::only($row, (array) $uniqueBy), $update === null ? $row : Arr::only($row, $update));
            $count++;
        }

        return $count;
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        [$table] = $this->source();

        return $this->connection->write($table, function () use ($column, $amount, $extra) {
            $key = $this->connection->key($this->source()[0]);
            $count = 0;
            foreach ($this->get() as $row) {
                $count += $this->newQuery()->from($this->from)->where($key, $row->$key)->update([$column => bcadd((string) $row->$column, (string) $amount, 4)] + $extra);
            }

            return $count;
        });
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        return $this->increment($column, -$amount, $extra);
    }
}
