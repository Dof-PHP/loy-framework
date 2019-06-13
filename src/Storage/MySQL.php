<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use PDO;
use Closure;
use Throwable;
use Dof\Framework\Collection;
use Dof\Framework\TypeHint;

class MySQL implements StorageInterface
{
    /** @var \Dof\Framework\Collection: Data used for SQL querying */
    private $annotations = [];

    /** @var object|null: PDO Connection Instance */
    private $connection;

    /** @var Closure: The getter to get acutal connection */
    private $connectionGetter;

    /** @var \Dof\Framework\Storage\MySQLBuilder: Query builder based on table */
    private $builder;

    /** @var array: Sqls executed in this instance lifetime */
    private $sqls = [];

    public function builder() : MySQLBuilder
    {
        return (new MySQLBuilder)->setOrigin($this);
        // return singleton(MySQLBuilder::class)->reset()->setOrigin($this);
    }

    /**
     * Add a single record and return primary key
     */
    public function add(array $data) : int
    {
        return $this->builder()->add($data);
    }

    /**
     * Insert a single record and return parimary key
     */
    public function insert(string $sql, array $values) : int
    {
        try {
            array_walk($values, function (&$val) {
                if (! is_scalar($val)) {
                    $val = enjson($val);
                }
            });

            $sql = $this->generate($sql);

            $start = microtime(true);

            $connection = $this->getConnection();
            $statement = $connection->prepare($sql);
            $statement->execute($values);
            $id = $connection->lastInsertId();

            $this->appendSql($sql, $start, $values);

            // NOTES:
            // - lastInsertId() only work after the INSERT query
            // - In transaction, lastInsertId() should be called before commit()
            return intval($id);
        } catch (Throwable $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                exception('ViolatedUniqueConstraint', compact('sql', 'values'), $e);
            }
            exception('InsertToMySQLFailed', compact('sql', 'values'), $e);
        }
    }

    public function deletes(...$pks) : int
    {
        $pks = array_unique(array_filter($pks, function ($pk) {
            return TypeHint::isPint($pk);
        }));

        return $this->builder()->in('id', $pks)->delete();
    }

    /**
     * Delete a record by primary key
     */
    public function delete(int $pk) : int
    {
        if ($pk < 1) {
            return 0;
        }

        return $this->builder()->where('id', $pk)->delete();
    }

    /**
     * Update a single record by primary key
     */
    public function update(int $pk, array $data) : int
    {
        if ($pk < 1) {
            return 0;
        }

        return $this->builder()->where('id', $pk)->update($data);
    }

    /**
     * Find a single record by primary key
     */
    public function find(int $pk) : ?array
    {
        if ($pk < 1) {
            return null;
        }

        return $this->builder()->where('id', $pk)->first();
    }

    public function __construct(array $annotations = [])
    {
        $this->annotations = collect($annotations);
    }

    public function rawExec(string $sql)
    {
        $start = microtime(true);

        try {
            $result = $this->getConnection(false)->exec($sql);
        } catch (Throwable $e) {
            exception('RawExecMySQLFailed', compact('sql'), $e);
        }

        $this->appendSQL($sql, $start);

        return $result;
    }

    public function rawGet(string $sql)
    {
        $start = microtime(true);

        try {
            $statement = $this->getConnection(false)->query($sql);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            exception('RawExecMySQLFailed', compact('sql'), $e);
        }

        $this->appendSQL($sql, $start);

        return $result;
    }

    /**
     * Execute a query with given sql template and parameters
     */
    public function get(string $sql, array $params = null)
    {
        try {
            $sql = $this->generate($sql);

            $start = microtime(true);

            if (is_null($params)) {
                $statement = $this->getConnection()->query($sql);
            } else {
                $statement = $this->getConnection()->prepare($sql, [
                    PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY,
                ]);

                $idx = 0;
                foreach ($params as $key => $val) {
                    $_key = is_int($key) ? ++$idx : $key;
                    $statement->bindValue($_key, $val, $this->getPDOValueConst($val));
                }

                $statement->execute();
            }

            $this->appendSQL($sql, $start, $params);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            exception('QueryMySQLFailed', ['sql' => $sql, 'params' => $params], $e);
        }
    }

    public function exec(string $sql, array $params = null)
    {
        try {
            $sql = $this->generate($sql);

            $start = microtime(true);

            if (is_null($params)) {
                $result = $this->getConnection()->exec($sql);
            } else {
                $statement = $this->getConnection()->prepare($sql);
                $statement->execute($params);

                $result = $statement->rowCount();
            }

            $this->appendSql($sql, $start, $params);

            return $result;
        } catch (Throwable $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                exception('ViolatedUniqueConstraint', compact('sql', 'params'), $e);
            }

            exception('OperationsToMySQLFailed', ['sql' => $sql], $e);
        }
    }

    public function use(string $dbname)
    {
        $sql = "USE `{$dbname}`";

        $this->exec($sql);

        return $this;
    }

    /**
     * Generate base sql statement from sql template
     *
     * @param string $sql
     * @return string
     */
    public function generate(string $sql) : string
    {
        $table = $this->getFullTable();
        if (! $table) {
            exception('MissingTableName');
        }

        $sql = str_replace('#{TABLE}', $table, $sql);
        if ($columns = $this->getSelectColumns()) {
            $sql = str_replace('#{COLUMNS}', $columns, $sql);
        }

        return $sql;
    }

    public function getSelectColumns(bool $asString = true)
    {
        $columns = $this->annotations->columns->getData();
        $columns = array_keys($columns);
        if (! $asString) {
            return $columns;
        }

        if (! $columns) {
            return '*';
        }

        return join(',', array_map(function ($column) {
            return "`{$column}`";
        }, $columns));
    }

    public function getFullTable() : ?string
    {
        $prefix = $this->annotations->meta->get('PREFIX', '', ['string']);
        $table  = $this->annotations->meta->get('TABLE', null, ['need', 'string']);

        if ((! $prefix) && (! $table)) {
            return null;
        }

        $db = $this->annotations->meta->DATABASE ?? null;

        return $db ? "`{$db}`.`{$prefix}{$table}`" : "`{$prefix}{$table}`";
    }

    public function quote(string $val)
    {
        $type = $this->getPDOValueConst($val);

        return $this->getConnection(false)->quote($val, $type);
    }

    public function getPDOValueConst($val)
    {
        switch (gettype($val)) {
            case 'integer':
                return PDO::PARAM_INT;
            case 'boolean':
                return PDO::PARAM_BOOL;
            case 'NULL':
                return PDO::PARAM_NULL;
            case 'string':
            default:
                return PDO::PARAM_STR;
        }
    }

    public function setConnectionGetter(Closure $getter)
    {
        $this->connectionGetter = $getter;

        return $this;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection(bool $needdb = true)
    {
        if ((! $this->connection) && $this->connectionGetter) {
            $this->connection = ($this->connectionGetter)();
        }
        if (! $this->connection) {
            exception('MissingMySQLConnection');
        }

        if ($needdb) {
            $db = $this->annotations->meta->DATABASE ?? null;
            if (! $db) {
                exception('MissingDatabaseInMySQLAnnotations', uncollect($this->annotations->meta ?? []));
            }

            $useDb = "USE `{$db}`";
            $this->connection->exec($useDb);
        }

        return $this->connection;
    }

    public function annotations()
    {
        return $this->annotations;
    }

    public function showSessionId()
    {
        $res = $this->get('SELECT CONNECTION_ID() as session_id');

        return $res[0]['session_id'] ?? '-1';
    }

    public function showTableLocks()
    {
        return $this->get('SHOW OPEN TABLES WHERE IN_USE >= 1');
    }

    private function appendSQL(string $sql, $start, array $params = null)
    {
        $sql = trim($sql);

        $this->sqls[] = [microftime('T Ymd His', '.', $start), $sql, $params, microtime(true)-$start];

        return $this;
    }

    public function __cleanup()
    {
        // Unlock tables of current session
        $this->exec('UNLOCK TABLES');

        // Rollback uncommited transactions
        // TODO
    }

    public function __logging()
    {
        return $this->sqls;
    }
}
