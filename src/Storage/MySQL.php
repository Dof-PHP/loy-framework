<?php

declare(strict_types=1);

namespace Loy\Framework\Storage;

use PDO;
use Throwable;
use Loy\Framework\Validator;

class MySQL
{
    private $config = [];
    private $conn;
    private $dbname;
    private $table;
    private $prefix;

    public function find(int $pk) : ?array
    {
        $sql = 'SELECT * FROM #{TABLE} WHERE `id` = ?';
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
        $this->config = $config;
    }

    public function validate(array $config)
    {
        try {
            $result = [];
            Validator::execute($config, [
                'host' => ['need', 'host'],
                'port' => ['int', 'default' => '3306'],
                'user' => ['need', 'string'],
                'pswd' => ['need', 'string'],
                'dbname'  => 'string|min:1',
                'charset' => ['string', 'default' => 'utf8mb4'],
            ], $result);

            return $result;
        } catch (Throwable $e) {
            exception('InvalidMySQLServerConfig', $config, $e);
        }
    }

    public function get(string $sql, array $params = null)
    {
        try {
            $sql = $this->buildSql($sql);

            if (is_null($params)) {
                return $this->getConn()->query($sql);
            }

            $statement = $this->getConn()->prepare($sql, [
                PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY,
            ]);

            $statement->execute($params);

            return $statement->fetchAll();
        } catch (Throwable $e) {
            exception('QueryMySQLFailed', ['sql' => $sql, 'params' => $params], $e);
        }
    }

    public function exec(string $sql)
    {
        try {
            $this->getConn()->exec($sql);
        } catch (Throwable $e) {
            exception('OperationsToMySQLFailed', ['sql' => $sql], $e);
        }
    }

    public function useDatabase(string $dbname)
    {
        $sql = "USE `{$dbname}`";

        $this->exec($sql);

        return $this;
    }

    public function connect(array $config = [])
    {
        $config = $config ?: $this->config;
        $config = $this->validate($config);

        $host = $config['host'] ?? '';
        $port = $config['port'] ?? 3306;
        $user = $config['user'] ?? '';
        $pswd = $config['pswd'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $dbname  = $config['dbname']  ?? null;

        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        if ($dbname) {
            $this->setDbname($dbname);
            $dsn .= ";dbname={$dbname}";
        }

        try {
            $this->conn = new PDO($dsn, $user, $pswd, [
                PDO::ATTR_PERSISTENT => true,
                // PDO::ATTR_TIMEOUT    => 3,
            ]);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            return $this->conn;
        } catch (Throwable $e) {
            exception('ConnectionToMySQLFailed', $config, $e);
        }
    }

    public function buildSql(string $sql) : string
    {
        if (! $this->dbname) {
            exception('NoDatabaseSelected');
        }

        $table = $this->getFullTableName();
        if (! $table) {
            exception('MissingTableName');
        }

        return str_replace('#{TABLE}', $table, $sql);
    }

    public function getFullTableName() : string
    {
        return "`{$this->prefix}{$this->table}`";
    }

    /**
     * Getter for conn
     *
     * @return PDO
     */
    public function getConn()
    {
        if (! $this->conn) {
            $this->connect();
        }

        return $this->conn;
    }

    /**
     * Setter for prefix
     *
     * @param string $prefix
     * @return MySQL
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
    
        return $this;
    }

    /**
     * Setter for table
     *
     * @param string $table
     * @return MySQL
     */
    public function setTable(string $table)
    {
        $this->table = $table;
    
        return $this;
    }

    /**
     * Setter for dbname
     *
     * @param string $dbname
     * @return MySQL
     */
    public function setDbname(string $dbname)
    {
        if ($dbname != $this->dbname) {
            $this->dbname = $dbname;
            $this->useDatabase($dbname);
        }
    
        return $this;
    }
}
