<?php

namespace App\Mongo;

use Closure;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;
use MongoDB\BSON\Regex;

/** Native aggregation adapter for the existing application's finite query vocabulary. */
class Builder extends BaseBuilder
{
    /** @var array<string, self> */
    protected array $mongoSubqueries = [];

    /** @var array<string, string> */
    private const EmbeddedParents = [
        'quote_items' => 'quote_id', 'order_items' => 'order_id',
        'purchase_items' => 'purchase_order_id', 'stock_document_items' => 'stock_document_id',
    ];

    public function newQuery(): static
    {
        return new static($this->connection, $this->grammar, $this->processor);
    }

    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false): static
    {
        if ($query instanceof Closure) {
            $callback = $query;
            $query = $this->newQuery();
            $callback($query);
        }
        if (! $query instanceof self || $where) {
            throw new LogicException('Only native builder equality join subqueries are supported.');
        }
        $this->mongoSubqueries[$as] = clone $query;

        return $this->join($as, $first, $operator, $second, $type);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false): static
    {
        if ($values instanceof Closure) {
            $callback = $values;
            $values = $this->newQuery();
            $callback($values);
        }
        if ($values instanceof self) {
            $this->wheres[] = ['type' => 'MongoInQuery', 'column' => $column, 'query' => clone $values, 'boolean' => $boolean, 'not' => $not];

            return $this;
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    public function get($columns = ['*']): Collection
    {
        $query = clone $this;
        $query->columns ??= is_array($columns) ? $columns : func_get_args();
        if ($query->lock) {
            $query->store()->lock($query->from, $query->filter($query->wheres ?? [], 'logical'));
        }
        $source = DocumentStore::source($query->from);
        $options = $query->store()->options() + ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'], 'collation' => ['locale' => 'vi', 'strength' => 1]];
        $cursor = $this->connection->getCollection($source['collection'])->aggregate($query->pipeline(), $options);

        return collect($cursor->toArray())->map(fn ($row) => DocumentStore::normalize($query->from, $row));
    }

    public function pluck($column, $key = null): Collection
    {
        $query = clone $this;
        $query->columns ??= $key === null ? [$column] : [$column, $key];
        $items = $query->get();

        return $items->pluck($this->outputName($column), $key === null ? null : $this->outputName($key));
    }

    public function value($column): mixed
    {
        $query = clone $this;
        $query->columns = [$column];
        $row = $query->first();

        return $row?->{$this->outputName($column)};
    }

    public function aggregate($function, $columns = ['*']): mixed
    {
        $function = strtolower($function);
        if (! in_array($function, ['count', 'sum', 'min', 'max'], true)) {
            throw new LogicException('Unsupported native aggregate: '.$function);
        }
        $query = clone $this;
        $query->orders = null;
        $query->limit = null;
        $query->offset = null;
        $query->columns = [$this->raw(strtoupper($function).'('.$columns[0].') as aggregate')];
        $query->groups = null;
        $value = $query->first()?->aggregate;

        return $value ?? match ($function) {
            'count' => 0, 'sum' => '0', default => null
        };
    }

    public function exists(): bool
    {
        $query = clone $this;
        $query->columns = ['id'];
        $query->limit = 1;

        return $query->get()->isNotEmpty();
    }

    public function getCountForPagination($columns = ['*']): int
    {
        $query = clone $this;
        $query->orders = null;
        $query->limit = null;
        $query->offset = null;
        $query->lock = null;
        $source = DocumentStore::source($query->from);
        $pipeline = $query->pipeline();
        $pipeline[] = ['$count' => 'total'];
        $rows = $this->connection->getCollection($source['collection'])->aggregate($pipeline, $this->store()->options() + ['collation' => ['locale' => 'vi', 'strength' => 1]])->toArray();

        return (int) ($rows[0]['total'] ?? 0);
    }

    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null, $total = null): LengthAwarePaginator
    {
        $page ??= Paginator::resolveCurrentPage($pageName);
        $total = value($total) ?? $this->getCountForPagination();
        $perPage = $perPage instanceof Closure ? $perPage($total) : $perPage;
        $query = clone $this;
        $results = $total ? $query->forPage($page, $perPage)->get($columns) : collect();

        return new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName,
        ]);
    }

    public function insert(array $values): bool
    {
        foreach ($this->rows($values) as $row) {
            $this->store()->insert($this->from, $row);
        }

        return true;
    }

    public function insertGetId(array $values, $sequence = null): int|string
    {
        return $this->store()->insert($this->from, $values);
    }

    public function insertOrIgnore(array $values): int
    {
        $inserted = 0;
        foreach ($this->rows($values) as $row) {
            $inserted += $this->store()->insertOrIgnore($this->from, $row);
        }

        return $inserted;
    }

    public function update(array $values): int
    {
        $this->assertWritable();

        return $this->store()->update($this->from, $this->filter($this->wheres ?? [], 'logical'), $values);
    }

    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        $this->assertWritable();
        if ($this->wheres) {
            throw new LogicException('Upserts must specify their complete identity in the attributes argument.');
        }
        if (is_callable($values)) {
            $query = clone $this;
            $values = $values($query->where($attributes)->exists());
        }

        return $this->store()->updateOrInsert($this->from, $attributes, $values);
    }

    public function delete($id = null): int
    {
        $query = clone $this;
        if ($id !== null) {
            $query->where('id', $id);
        }
        $query->assertWritable();

        return $query->store()->delete($query->from, $query->filter($query->wheres ?? [], 'logical'));
    }

    public function truncate(): void
    {
        throw new LogicException('Use the isolated Mongo test reset or explicit schema command; application truncate is unsupported.');
    }

    public function toSql(): string
    {
        throw new LogicException('This connection executes native MongoDB pipelines, not SQL.');
    }

    /** @return list<array<string, mixed>> */
    public function pipeline(?array $leadingMatch = null): array
    {
        if ($this->unions || $this->havings) {
            throw new LogicException('Union and having are outside the application query vocabulary.');
        }
        $source = DocumentStore::source($this->from);
        $pipeline = $leadingMatch ? [['$match' => $leadingMatch]] : [];
        $safe = $this->rootPredicates();
        if ($safe !== []) {
            $pipeline[] = ['$match' => $this->filter($safe, 'physical')];
        }
        $pipeline = array_merge($pipeline, $source['pipeline']);
        $pipeline[] = ['$replaceRoot' => ['newRoot' => [$this->from => '$$ROOT']]];
        foreach ($this->joins ?? [] as $join) {
            $pipeline = array_merge($pipeline, $this->joinPipeline($join));
        }
        $whereInAliases = [];
        $this->appendSubqueryLookups($this->wheres ?? [], $pipeline, $whereInAliases);
        $filter = $this->filter($this->wheres ?? [], 'namespaced', $whereInAliases);
        if ($filter !== []) {
            $pipeline[] = ['$match' => $filter];
        }
        $columns = $this->expressions();
        $hasAggregate = collect($columns)->contains(fn ($column) => $column['kind'] === 'aggregate');
        if ($this->groups || $hasAggregate) {
            $groupId = [];
            foreach ($this->groups ?? [] as $column) {
                $groupId[$this->outputName($column)] = '$'.$this->path($column);
            }
            $group = ['_id' => $groupId === [] ? null : $groupId];
            foreach ($columns as $column) {
                if ($column['kind'] === 'star') {
                    throw new LogicException('Grouped wildcard projections are unsupported.');
                }
                $group[$column['name']] = $column['kind'] === 'aggregate'
                    ? ['$'.($column['function'] === 'count' ? 'sum' : $column['function']) => $column['field'] === '*' ? 1 : '$'.$this->path($column['field'])]
                    : ['$first' => $this->expressionValue($column)];
            }
            $pipeline[] = ['$group' => $group];
            $projection = ['_id' => 0];
            foreach ($columns as $column) {
                $projection[$column['name']] = 1;
            }
            $pipeline[] = ['$project' => $projection];
            $pipeline = array_merge($pipeline, $this->sortAndLimit(true));
        } else {
            $pipeline = array_merge($pipeline, $this->sortAndLimit(false));
            $objects = [];
            foreach ($columns as $column) {
                $objects[] = $column['kind'] === 'star'
                    ? '$'.($column['field'] === '*' ? $this->from : substr($column['field'], 0, -2))
                    : [$column['name'] => ['$ifNull' => [$this->expressionValue($column), null]]];
            }
            $pipeline[] = ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => $objects]]];
        }
        if ($this->distinct) {
            $pipeline[] = ['$group' => ['_id' => '$$ROOT']];
            $pipeline[] = ['$replaceRoot' => ['newRoot' => '$_id']];
        }

        return $pipeline;
    }

    private function store(): DocumentStore
    {
        return new DocumentStore($this->connection);
    }

    private function rows(array $values): array
    {
        return $values === [] ? [] : (array_is_list($values) ? $values : [$values]);
    }

    private function assertWritable(): void
    {
        if ($this->joins || $this->groups || $this->limit !== null || $this->offset !== null) {
            throw new LogicException('Joined, grouped and bounded mutations are unsupported.');
        }
    }

    private function path(string $column, string $mode = 'namespaced'): string
    {
        $column = str_replace('`', '', $column);
        [$table, $field] = str_contains($column, '.') ? explode('.', $column, 2) : [$this->from, $column];
        if ($mode === 'namespaced') {
            return $table.'.'.$field;
        }
        if ($table !== $this->from) {
            throw new LogicException('Only root fields can be used in this mutation or indexed predicate.');
        }
        if ($mode === 'logical') {
            return $field;
        }
        if (isset(self::EmbeddedParents[$this->from])) {
            return $field === self::EmbeddedParents[$this->from] ? '_id' : 'items.'.($field === 'id' ? 'item_id' : $field);
        }

        return $field === DocumentStore::schema($this->from)['primary'] ? '_id' : $field;
    }

    private function encodedValue(string $column, mixed $value): mixed
    {
        [$table, $field] = str_contains($column, '.') ? explode('.', str_replace('`', '', $column), 2) : [$this->from, $column];
        if (isset($this->mongoSubqueries[$table])) {
            return $value;
        }
        $encoded = $this->store()->encode($table, [$field => $value]);

        return $encoded[$field] ?? $encoded[$field === 'id' ? (isset(self::EmbeddedParents[$table]) ? 'item_id' : '_id') : $field] ?? $value;
    }

    private function rootPredicates(): array
    {
        $wheres = $this->wheres ?? [];
        if (collect($wheres)->contains(fn ($where) => ($where['boolean'] ?? 'and') !== 'and')) {
            return [];
        }

        return array_values(array_filter($wheres, function ($where) {
            if (! in_array(ucfirst($where['type']), ['Basic', 'In', 'NotIn', 'Null', 'NotNull', 'Between', 'NotBetween', 'InRaw', 'NotInRaw'], true)) {
                return false;
            }
            $column = $where['column'];

            return ! str_contains($column, '.') || str_starts_with($column, $this->from.'.');
        }));
    }

    private function filter(array $wheres, string $mode, array $whereInAliases = []): array
    {
        $or = [];
        $and = [];
        foreach ($wheres as $where) {
            if (($where['boolean'] ?? 'and') === 'or' && $and !== []) {
                $or[] = count($and) === 1 ? $and[0] : ['$and' => $and];
                $and = [];
            }
            $type = ucfirst($where['type']);
            if ($type === 'Nested') {
                $condition = $this->filter($where['query']->wheres ?? [], $mode, $whereInAliases);
            } elseif ($type === 'MongoInQuery') {
                if ($mode !== 'namespaced') {
                    throw new LogicException('Subquery mutation predicates are unsupported.');
                }
                $alias = $whereInAliases[spl_object_id($where['query'])];
                $condition = ['$expr' => [($where['not'] ? '$eq' : '$gt') => [['$size' => '$'.$alias], 0]]];
            } else {
                $column = $where['column'] ?? throw new LogicException('Unsupported predicate: '.$type);
                $path = $this->path($column, $mode);
                $value = $where['value'] ?? null;
                $condition = match ($type) {
                    'Basic' => $this->basicCondition($column, $path, $where['operator'], $value),
                    'Column' => ['$expr' => [$this->operator($where['operator']) => ['$'.$path, '$'.$this->path($where['second'], $mode)]]],
                    'Null' => [$path => null],
                    'NotNull' => [$path => ['$ne' => null]],
                    'In', 'InRaw', 'NotIn', 'NotInRaw' => $this->membershipCondition($column, $path, $where['values'], str_starts_with($type, 'Not')),
                    'Between', 'NotBetween' => $this->betweenCondition($column, $path, $where),
                    default => throw new LogicException('Unsupported predicate: '.$type),
                };
            }
            if ($condition !== []) {
                $and[] = $condition;
            }
        }
        if ($and !== []) {
            $or[] = count($and) === 1 ? $and[0] : ['$and' => $and];
        }

        return $or === [] ? [] : (count($or) === 1 ? $or[0] : ['$or' => $or]);
    }

    private function basicCondition(string $column, string $path, string $operator, mixed $value): array
    {
        if ($value instanceof ExpressionContract) {
            throw new LogicException('Raw mutation/predicate expressions are unsupported.');
        }
        if (in_array(strtolower($operator), ['like', 'not like'], true)) {
            $parts = explode('.', $path);
            $field = array_pop($parts);
            $parts[] = '_search_'.$field;
            $pattern = Str::lower(Str::ascii((string) $value));
            $regex = '';
            for ($index = 0; $index < strlen($pattern); $index++) {
                $character = $pattern[$index];
                if ($character === '\\' && $index + 1 < strlen($pattern)) {
                    $regex .= preg_quote($pattern[++$index], '/');
                } else {
                    $regex .= match ($character) {
                        '%' => '.*', '_' => '.', default => preg_quote($character, '/')
                    };
                }
            }
            $match = new Regex('^'.$regex.'$', 's');

            return [implode('.', $parts) => strtolower($operator) === 'not like' ? ['$not' => $match] : $match];
        }
        $value = $this->encodedValue($column, $value);
        if (in_array($operator, ['!=', '<>'], true)) {
            return ['$and' => [[$path => ['$ne' => $value]], [$path => ['$ne' => null]]]];
        }

        return [$path => $operator === '=' ? $value : [$this->operator($operator) => $value]];
    }

    private function membershipCondition(string $column, string $path, array $values, bool $not): array
    {
        if ($values === []) {
            return $not ? [] : ['$expr' => false];
        }
        if ($not && in_array(null, $values, true)) {
            return ['$expr' => false];
        }
        $values = array_values(array_filter($values, static fn ($value) => $value !== null));
        $match = [$path => [$not ? '$nin' : '$in' => array_map(fn ($value) => $this->encodedValue($column, $value), $values)]];

        return $not ? ['$and' => [$match, [$path => ['$ne' => null]]]] : $match;
    }

    private function betweenCondition(string $column, string $path, array $where): array
    {
        $values = $where['values'];
        $low = $this->encodedValue($column, $values[0]);
        $high = $this->encodedValue($column, $values[1]);

        return ($where['not'] ?? false)
            ? ['$or' => [[$path => ['$lt' => $low]], [$path => ['$gt' => $high]]]]
            : [$path => ['$gte' => $low, '$lte' => $high]];
    }

    private function operator(string $operator): string
    {
        return match ($operator) {
            '=' => '$eq', '!=', '<>' => '$ne', '>' => '$gt', '>=' => '$gte', '<' => '$lt', '<=' => '$lte',
            default => throw new LogicException('Unsupported comparison: '.$operator),
        };
    }

    private function joinPipeline(object $join): array
    {
        if (! in_array($join->type, ['inner', 'left'], true) || count($join->wheres ?? []) !== 1) {
            throw new LogicException('Only single equality inner/left joins are supported.');
        }
        $on = $join->wheres[0];
        if ($on['type'] !== 'Column' || $on['operator'] !== '=') {
            throw new LogicException('Only equality column joins are supported.');
        }
        $alias = (string) $join->table;
        $foreign = str_starts_with($on['first'], $alias.'.') ? $on['first'] : $on['second'];
        $local = $foreign === $on['first'] ? $on['second'] : $on['first'];
        $foreign = substr($foreign, strlen($alias) + 1);
        $subquery = $this->mongoSubqueries[$alias] ?? null;
        if ($subquery) {
            $source = DocumentStore::source($subquery->from);
            $leading = ['$expr' => ['$eq' => ['$'.$subquery->path($foreign, 'physical'), '$$joinValue']]];
            $lookup = ['from' => $source['collection'], 'let' => ['joinValue' => '$'.$this->path($local)],
                'pipeline' => $subquery->pipeline($leading), 'as' => $alias];
        } else {
            $source = DocumentStore::source($alias);
            $other = $this->newQuery()->from($alias);
            $lookup = ['from' => $source['collection'], 'localField' => $this->path($local),
                'foreignField' => $other->path($foreign, 'physical'),
                'let' => ['joinValue' => '$'.$this->path($local)],
                'pipeline' => array_merge($source['pipeline'], [['$match' => ['$expr' => ['$and' => [
                    ['$ne' => ['$$joinValue', null]], ['$eq' => ['$'.$foreign, '$$joinValue']],
                ]]]]]), 'as' => $alias];
        }

        return [['$lookup' => $lookup], ['$unwind' => ['path' => '$'.$alias, 'preserveNullAndEmptyArrays' => $join->type === 'left']]];
    }

    private function appendSubqueryLookups(array $wheres, array &$pipeline, array &$aliases): void
    {
        foreach ($wheres as $where) {
            if ($where['type'] === 'Nested') {
                $this->appendSubqueryLookups($where['query']->wheres ?? [], $pipeline, $aliases);
            } elseif ($where['type'] === 'MongoInQuery') {
                $query = $where['query'];
                $columns = $query->expressions();
                if (count($columns) !== 1 || $columns[0]['kind'] !== 'field') {
                    throw new LogicException('Membership subqueries must select one field.');
                }
                $selected = $columns[0];
                $source = DocumentStore::source($query->from);
                $alias = '__membership_'.count($aliases);
                $aliases[spl_object_id($query)] = $alias;
                $joinFields = [];
                if (! str_contains($selected['field'], '.') || str_starts_with($selected['field'], $query->from.'.')) {
                    $joinFields = ['localField' => $this->path($where['column']), 'foreignField' => $query->path($selected['field'], 'physical')];
                }
                $subPipeline = $query->pipeline();
                $subPipeline[] = ['$match' => ['$expr' => ['$eq' => ['$'.$selected['name'], '$$candidate']]]];
                $subPipeline[] = ['$limit' => 1];
                $pipeline[] = ['$lookup' => ['from' => $source['collection'], 'let' => ['candidate' => '$'.$this->path($where['column'])], 'pipeline' => $subPipeline, 'as' => $alias] + $joinFields];
            }
        }
    }

    /** @return list<array<string, string>> */
    private function expressions(): array
    {
        $expressions = [];
        foreach ($this->columns ?? ['*'] as $column) {
            if ($column instanceof ExpressionContract) {
                $raw = $column->getValue($this->grammar);
                $parts = preg_split('/,(?![^()]*\))/', $raw);
                foreach ($parts as $part) {
                    $part = trim(str_replace('`', '', $part));
                    if (preg_match('/^(SUM|COUNT|MIN|MAX)\((\*|[\w.]+)\)\s+as\s+(\w+)$/i', $part, $match)) {
                        $expressions[] = ['kind' => 'aggregate', 'function' => strtolower($match[1]), 'field' => $match[2], 'name' => $match[3]];
                    } elseif (preg_match('/^COALESCE\(([\w.]+),\s*0\)\s+as\s+(\w+)$/i', $part, $match)) {
                        $expressions[] = ['kind' => 'coalesce', 'field' => $match[1], 'name' => $match[2]];
                    } else {
                        throw new LogicException('Unsupported raw expression: '.$part);
                    }
                }
            } else {
                $column = str_replace('`', '', $column);
                $parts = preg_split('/\s+as\s+/i', $column);
                $field = $parts[0];
                $expressions[] = ['kind' => $field === '*' || str_ends_with($field, '.*') ? 'star' : 'field',
                    'field' => $field, 'name' => $parts[1] ?? $this->outputName($field)];
            }
        }

        return $expressions;
    }

    private function expressionValue(array $column): mixed
    {
        $value = '$'.$this->path($column['field']);

        return $column['kind'] === 'coalesce' ? ['$ifNull' => [$value, 0]] : $value;
    }

    private function outputName(string $column): string
    {
        $parts = preg_split('/\s+as\s+/i', str_replace('`', '', $column));
        $column = end($parts);

        return str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
    }

    private function sortAndLimit(bool $grouped): array
    {
        $pipeline = [];
        $sort = [];
        foreach ($this->orders ?? [] as $order) {
            if (($order['type'] ?? 'Basic') !== 'Basic') {
                throw new LogicException('Raw ordering is unsupported.');
            }
            $sort[$grouped ? $this->outputName($order['column']) : $this->path($order['column'])] = $order['direction'] === 'desc' ? -1 : 1;
        }
        if ($sort !== []) {
            $pipeline[] = ['$sort' => $sort];
        }
        if ($this->offset) {
            $pipeline[] = ['$skip' => (int) $this->offset];
        }
        if ($this->limit !== null) {
            $pipeline[] = ['$limit' => max(1, (int) $this->limit)];
            if ($this->limit === 0) {
                $pipeline[] = ['$match' => ['$expr' => false]];
            }
        }

        return $pipeline;
    }
}
