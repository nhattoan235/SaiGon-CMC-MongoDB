<?php

namespace App\Database;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use MongoDB\BSON\Decimal128;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\Session;
use MongoDB\Driver\WriteConcern;
use MongoDB\Operation\FindOneAndUpdate;
use RuntimeException;
use Throwable;

use function MongoDB\with_transaction;

class MongoConnection extends Connection
{
    public Client $client;

    public Database $mongo;

    public array $definitions;

    public bool $constraintsDisabled = false;

    private ?Session $session = null;

    private array $touched = [];

    private array $committedCallbacks = [];

    private bool $rollbackOnly = false;

    public function __construct(array $config)
    {
        parent::__construct(null, $config['database'], '', $config);
        $this->client = new Client($config['dsn'], ['serverSelectionTimeoutMS' => 10000]);
        $this->mongo = $this->client->selectDatabase($config['database']);
        $this->definitions = json_decode(ltrim(file_get_contents(database_path('mongodb-schema.json')), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
    }

    public function query(): MongoQuery
    {
        return new MongoQuery($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }

    public function getSchemaBuilder(): MongoSchema
    {
        return new MongoSchema($this);
    }

    public function options(): array
    {
        return array_filter(['session' => $this->session]);
    }

    public function columns(string $table): array
    {
        return array_column($this->definitions[$table]['columns'] ?? [], null, 'name');
    }

    public function key(string $table): string
    {
        return collect($this->definitions[$table]['indexes'] ?? [])->firstWhere('primary', true)['columns'][0] ?? 'id';
    }

    public function encode(string $table, array $values, bool $insert = false): array
    {
        $values = collect($values)->mapWithKeys(fn ($value, $name) => [str_starts_with($name, $table.'.') ? substr($name, strlen($table) + 1) : $name => $value])->all();
        $columns = $this->columns($table);
        if ($insert) {
            foreach ($columns as $name => $column) {
                if (! array_key_exists($name, $values)) {
                    $default = $column['default'];
                    $values[$name] = $default === null ? null : trim((string) $default, "'");
                }
            }
        }
        $search = [];
        foreach ($values as $name => &$value) {
            if (! isset($columns[$name])) {
                throw new RuntimeException('Trường dữ liệu chưa khai báo: '.$table.'.'.$name);
            }
            if ($value === null) {
                continue;
            }
            if ($value instanceof DateTimeInterface) {
                // Match Laravel SQL bindings; workflows pass strings when microseconds matter.
                $value = $value->format('Y-m-d H:i:s');
            }
            $type = $columns[$name]['type_name'];
            if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'integer'], true)) {
                if (is_bool($value)) {
                    $value = (int) $value;
                }
                if (! is_numeric($value) || bccomp((string) $value, (string) PHP_INT_MAX) > 0 || bccomp((string) $value, (string) PHP_INT_MIN) < 0) {
                    throw new RuntimeException('Số nguyên ngoài phạm vi: '.$table.'.'.$name);
                }
                $value = (int) $value;
            } elseif (in_array($type, ['decimal', 'numeric'], true)) {
                $scale = preg_match('/\(\d+,\s*(\d+)\)/', $columns[$name]['type'], $match) ? (int) $match[1] : 2;
                $value = new Decimal128(bcadd((string) $value, '0', $scale));
            } elseif ($type === 'json') {
                $value = is_string($value) ? json_decode($value, false, 512, JSON_THROW_ON_ERROR) : $value;
            } elseif (in_array($type, ['float', 'double'], true)) {
                $value = (float) $value;
            } else {
                $value = (string) $value;
                if (in_array($name, ['name', 'code', 'specification'], true)) {
                    $search[$name] = Str::lower(Str::ascii($value));
                }
            }
        }
        unset($value);
        foreach ($search as $name => $value) {
            $values['_search.'.$name] = $value;
        }

        return $values;
    }

    public function decode(mixed $value, ?string $table = null): mixed
    {
        if ($value instanceof Decimal128) {
            return (string) $value;
        }
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (! is_array($value)) {
            return $value;
        }
        $columns = $table ? $this->columns($table) : [];
        unset($value['_id'], $value['_search']);
        foreach ($value as $name => &$item) {
            if (($columns[$name]['type_name'] ?? null) === 'json' && $item !== null) {
                $item = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } else {
                $item = $this->decode($item);
            }
        }

        return $value;
    }

    public function write(string $table, Closure $callback): mixed
    {
        $operation = function () use ($table, $callback) {
            $this->touched[$table] = true;
            try {
                return $callback();
            } catch (Throwable $exception) {
                if ((int) $exception->getCode() === 11000) {
                    throw new UniqueConstraintViolationException($this->getName(), 'MongoDB unique index: '.$table, [], $exception);
                }
                throw $exception;
            }
        };

        return $this->transactions > 0 ? $operation() : $this->transaction($operation);
    }

    public function nextId(string $table): int
    {
        $row = $this->mongo->selectCollection('_cmc_sequences')->findOneAndUpdate(['_id' => $table], ['$inc' => ['value' => 1]], $this->options() + ['upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]);

        return (int) $row['value'];
    }

    public function transaction(Closure $callback, $attempts = 1): mixed
    {
        if ($this->transactions > 0) {
            try {
                return $callback($this);
            } catch (Throwable $exception) {
                $this->rollbackOnly = true;
                throw $exception;
            }
        }
        $this->session = $this->client->startSession();
        try {
            $result = null;
            with_transaction($this->session, function () use ($callback, &$result) {
                $this->transactions = 1;
                $this->touched = [];
                $this->committedCallbacks = [];
                $this->rollbackOnly = false;
                $this->transactionsManager?->begin($this->getName(), 1);
                try {
                    $this->gate();
                    $result = $callback($this);
                    if ($this->rollbackOnly) {
                        throw new RuntimeException('Transaction MongoDB đã bị đánh dấu rollback sau lỗi lồng nhau.');
                    }
                    $this->validateReferences();
                } catch (Throwable $exception) {
                    $this->transactionsManager?->rollback($this->getName(), 0);
                    throw $exception;
                }

                return $result;
            }, ['readConcern' => new ReadConcern('snapshot'), 'writeConcern' => new WriteConcern('majority')]);
        } catch (Throwable $exception) {
            $this->transactionsManager?->rollback($this->getName(), 0);
            $this->committedCallbacks = [];
            throw $exception;
        } finally {
            $this->transactions = 0;
            $this->session->endSession();
            $this->session = null;
        }
        $this->transactionsManager?->commit($this->getName(), 1, 0);
        foreach ($this->committedCallbacks as $afterCommit) {
            $afterCommit();
        }
        $this->committedCallbacks = [];

        return $result;
    }

    public function beginTransaction(): void
    {
        if ($this->transactions > 0) {
            $this->transactions++;
            $this->transactionsManager?->begin($this->getName(), $this->transactions);

            return;
        }
        $this->session = $this->client->startSession();
        $this->session->startTransaction();
        $this->transactions = 1;
        $this->transactionsManager?->begin($this->getName(), 1);
        $this->touched = [];
        $this->committedCallbacks = [];
        $this->gate();
    }

    public function commit(): void
    {
        if ($this->transactions > 1) {
            $level = $this->transactions;
            $this->transactions--;
            $this->transactionsManager?->commit($this->getName(), $level, $this->transactions);

            return;
        }
        $this->validateReferences();
        $this->session?->commitTransaction();
        $this->session?->endSession();
        $this->session = null;
        $this->transactions = 0;
        $this->transactionsManager?->commit($this->getName(), 1, 0);
        foreach ($this->committedCallbacks as $afterCommit) {
            $afterCommit();
        }
        $this->committedCallbacks = [];
    }

    public function rollBack($toLevel = null): void
    {
        if ($this->session?->isInTransaction()) {
            $this->session->abortTransaction();
        }
        $this->session?->endSession();
        $this->session = null;
        $this->transactions = 0;
        $this->transactionsManager?->rollback($this->getName(), 0);
        $this->committedCallbacks = [];
    }

    private function gate(): void
    {
        $result = $this->mongo->selectCollection('_cmc_locks')->updateOne(['_id' => 'business'], ['$inc' => ['version' => 1]], $this->options());
        if ($result->getMatchedCount() !== 1) {
            throw new RuntimeException('Cần chạy cmms:mongo-install trước khi ghi dữ liệu.');
        }
    }

    public function validateReferences(): void
    {
        foreach ($this->definitions as $table => $definition) {
            foreach ($definition['foreign_keys'] as $key) {
                if (! isset($this->touched[$table]) && ! isset($this->touched[$key['foreign_table']])) {
                    continue;
                }
                $filter = $variables = $conditions = [];
                foreach ($key['columns'] as $index => $column) {
                    $filter[$column] = ['$ne' => null];
                    $variables['v'.$index] = '$'.$column;
                    $conditions[] = ['$eq' => ['$'.$key['foreign_columns'][$index], '$$v'.$index]];
                }
                $rows = $this->mongo->selectCollection($table)->aggregate([
                    ['$match' => $filter],
                    ['$lookup' => ['from' => $key['foreign_table'], 'let' => $variables, 'pipeline' => [['$match' => ['$expr' => ['$and' => $conditions]]], ['$limit' => 1]], 'as' => '_parent']],
                    ['$match' => ['_parent' => ['$size' => 0]]], ['$limit' => 1],
                ], $this->options())->toArray();
                if ($rows !== []) {
                    throw new QueryException($this->getName(), 'MongoDB reference: '.$table.'.'.$key['name'], [], new RuntimeException('Dữ liệu tham chiếu không hợp lệ.'));
                }
            }
        }
    }

    public function afterCommit($callback): mixed
    {
        if ($this->transactions > 0) {
            $this->committedCallbacks[] = $callback;

            return null;
        }

        return $callback();
    }
}
