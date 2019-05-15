<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use PDO;
use Throwable;
use Dof\Framework\Collection;

class MySQL implements StorageInterface
{
    /** @var \Dof\Framework\Collection: SQL Query used data, collection instance */
    private $query = [];

    /** @var object|null: PDO Connection Instance */
    private $connection;

    /** @var array: Sqls executed in this instance lifetime */
    private $sqls = [];

    public function update(int $pk, array $data) : int
    {
        if (! $data) {
            return 0;
        }

        $parmas = [];
        $columns = [];
        foreach ($data as $key => $val) {
            $columns[] = "`{$key}` = ?";
            $params[] = $val;
        }

        $columns = join(', ', $columns);
        $params[] = $pk;

        $sql = "UPDATE #{TABLE} SET {$columns} WHERE `id` = ?";

        return $this->exec($sql, $params);
    }

    public function add(array $data) : int
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $count = count($values);
        $_values = join(',', array_fill(0, $count, '?'));

        $columns = join(',', array_map(function ($column) {
            return "`{$column}`";
        }, $columns));

        $sql = "INSERT INTO #{TABLE} ({$columns}) VALUES ({$_values})";

        return (int) $this->insert($sql, $values);
    }

    public function find(int $pk) : ?array
    {
        $sql = 'SELECT #{COLUMNS} FROM #{TABLE} WHERE `id` = ?';
        $res = $this->get($sql, [$pk]);

        return $res[0] ?? null;
    }

    /**
     * Delete a record by primary key
     *
     * @param int $pk: Primary ke of table
     * @return int: Number of rows affected
     */
    public function delete(int $pk) : int
    {
        $sql = 'DELETE FROM #{TABLE} WHERE `id` = ?';

        return $this->exec($sql, [$pk]);
    }

    public function count() : int
    {
        $sql = 'SELECT count(*) as `total` FROM #{TABLE}';
        $res = $this->get($sql);

        return intval($res[0]['total'] ?? 0);
    }

    public function paginate(int $page, int $size) : array
    {
        $sql = 'SELECT #{COLUMNS} FROM #{TABLE} LIMIT ?, ?';

        return $this->get($sql, [($page - 1) * $size, $size]);
    }

    public function __construct(array $config = [])
    {
        $this->config = collect($config);
    }

    public function raw(string $sql)
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

    public function insert(string $sql, array $params)
    {
        try {
            $this->getConnection()->beginTransaction();

            $sql = $this->generate($sql);

            $start = microtime(true);

            $this->appendSql($sql, $start, $params);
            $statement = $this->getConnection()->prepare($sql);
            $statement->execute($params);

            $id = $this->getConnection()->lastInsertId();

            $this->getConnection()->commit();

            return $id;
        } catch (Throwable $e) {
            $this->getConnection()->rollBack();

            exception('InsertToMySQLFailed', ['sql' => $sql], $e);
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
        $sql = "USE `{$dbname}`";

        $this->exec($sql);

        return $this;
    }

    /**
     * Generate sql statement from sql template
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
        $columns = $this->query->columns->getData();
        if (! $columns) {
            return '*';
        }

        return join(',', array_map(function ($column) {
            return "`{$column}`";
        }, $columns));
    }

    public function getFullTable() : ?string
    {
        $prefix = (string) $this->query->prefix;
        $table  = (string) $this->query->table;

        if ((! $prefix) && (! $table)) {
            return null;
        }

        return "`{$prefix}{$table}`";
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

        return $this->connection;
    }

    public function callbackOnConnected(Collection $config)
    {
        if ($db = $config->get('database', null)) {
            $this->appendSql("USE {$db}", 0);
        }
    }

    /**
     * Setter for query
     *
     * @param array $query
     * @return MySQL
     */
    public function setQuery(array $query)
    {
        $this->query = collect($query);

        if ($dbname = $this->query->get('database')) {
            $this->use($dbname);
        }
    
        return $this;
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
