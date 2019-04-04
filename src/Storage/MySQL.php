<?php

declare(strict_types=1);

namespace Loy\Framework\Storage;

use PDO;
use Throwable;
use Loy\Framework\Validator;

class MySQL implements StorageInterface
{
    /** @var \Loy\Framework\Collection: Configuration collection instance */
    private $config = [];

    /** @var \Loy\Framework\Collection: SQL Query used data, collection instance */
    private $query = [];

    /** @var object|null: Connection Instance */
    private $connection;

    public function find(int $pk) : ?array
    {
        $sql = 'SELECT #{COLUMNS} FROM #{TABLE} WHERE `id` = ?';
        $res = $this->get($sql, [$pk]);

        return $res[0] ?? null;
    }

    public function delete(int $pk) : void
    {
        $sql = 'DELETE FROM #{TABLE} WHERE `id` = ?';
        $this->exec($sql, [$pk]);
    }

    public function __construct(array $config = [])
    {
        $this->config = collect($config);
    }

    public function get(string $sql, array $params = null)
    {
        try {
            $sql = $this->generate($sql);

            if (is_null($params)) {
                return $this->getConnection()->query($sql);
            }

            $statement = $this->getConnection()->prepare($sql, [
                PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY,
            ]);

            $statement->execute($params);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            exception('QueryMySQLFailed', ['sql' => $sql, 'params' => $params], $e);
        }
    }

    public function exec(string $sql)
    {
        try {
            $this->getConnection()->exec($sql);
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

    public function connect()
    {
        $host = (string) $this->config->get('host');
        $port = (int) $this->config->get('port', 3306);
        $user = (string) $this->config->get('user');
        $pswd = (string) $this->config->get('passwd');
        $charset = (string) $this->config->get('charset', 'utf8mb4');
        $dbname  = (string) $this->config->get('dbname');

        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        if ($dbname) {
            $dsn .= ";dbname={$dbname}";
        }

        try {
            $this->connection = new PDO($dsn, $user, $pswd, [
                PDO::ATTR_PERSISTENT => true,
                // PDO::ATTR_TIMEOUT    => 3,
            ]);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            return $this->connection;
        } catch (Throwable $e) {
            exception('ConnectionToMySQLFailed', compact('dsn'), $e);
        }
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

    /**
     * Getter for conn
     *
     * @return PDO
     */
    public function getConnection()
    {
        if (! $this->connection) {
            $this->connect();
        }

        return $this->connection;
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

    public function showSeesionId()
    {
        return $this->get('SELECT CONNECTION_ID()');
    }

    public function showTableLocks()
    {
        return $this->get('SHOW OPEN TABLES WHERE IN_USE >= 1');
    }

    public function __cleanup()
    {
        // Unlock tables of current session
        $this->exec('UNLOCK TABLES');

        // Rollback uncommited transactions
        // TODO
    }
}
