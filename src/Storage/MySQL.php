<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use PDO;
use Throwable;
use Dof\Framework\Collection;

class MySQL implements StorageInterface
{
    /** @var \Dof\Framework\Collection: Data used for SQL querying */
    private $annotations = [];

    /** @var object|null: PDO Connection Instance */
    private $connection;

    /** @var \Dof\Framework\Storage\MySQLBuilder: Query builder based on table */
    private $builder;

    /** @var bool: Used database or not */
    private $database;

    /** @var bool: Queries need a database used or not */
    private $needdb;

    /** @var array: Sqls executed in this instance lifetime */
    private $sqls = [];

    public function builder() : MySQLBuilder
    {
        return singleton(MySQLBuilder::class)->reset()->setOrigin($this);
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
            $this->getConnection()->beginTransaction();

            $sql = $this->generate($sql);

            $start = microtime(true);

            $this->appendSql($sql, $start, $values);
            $statement = $this->getConnection()->prepare($sql);
            $statement->execute($values);

            $id = $this->getConnection()->lastInsertId();

            $this->getConnection()->commit();

            return (int) $id;
        } catch (Throwable $e) {
            $this->getConnection()->rollBack();

            exception('InsertToMySQLFailed', compact('sql', 'value'), $e);
        }
    }

    public function deletes(...$pks) : int
    {
        return $this->builder()->where('id', $pks, 'in')->delete();
    }

    /**
     * Delete a record by primary key
     */
    public function delete(int $pk) : int
    {
        return $this->builder()->where('id', $pk)->delete();
    }

    /**
     * Update a single record by primary key
     */
    public function update(int $pk, array $data) : int
    {
        return $this->builder()->where('id', $pk)->update($data);
    }

    /**
     * Find a single record by primary key
     */
    public function find(int $pk) : ?array
    {
        return $this->builder()->where('id', $pk)->first();
    }

    public function __construct(array $annotations)
    {
        $this->annotations = collect($annotations);
    }

    public function rawExec(string $sql)
    {
        $start = microtime(true);

        $result = $this->getConnection()->exec($sql);

        $this->appendSQL($sql, $start);

        return $result;
    }

    public function rawGet(string $sql)
    {
        $start = microtime(true);

        $statement = $this->getConnection()->query($sql);

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

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
            exception('OperationsToMySQLFailed', ['sql' => $sql], $e);
        }
    }

    public function use(string $dbname)
    {
        $this->database = $dbname;

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

    public function getSelectColumns() : ?string
    {
        $columns = array_keys($this->annotations->columns->getData());
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

        return "`{$prefix}{$table}`";
    }

    public function quote(string $val)
    {
        $type = $this->getPDOValueConst($val);

        return $this->getConnection()->quote($val, $type);
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

    public function setNeeddb(bool $needdb)
    {
        $this->needdb = $needdb;

        return $this;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    public function getConnection()
    {
        if (! $this->connection) {
            exception('MissingMySQLConnection');
        }

        if ((! $this->database) && ($this->needdb)) {
            $dbname = $this->annotations->meta->get('DATABASE', null, ['need', 'string']);
            $this->use($dbname);
        }

        return $this->connection;
    }

    public function callbackOnConnected(Collection $config)
    {
        if ($db = $config->get('database', null)) {
            $this->appendSql("USE {$db}", 0);
        }
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
