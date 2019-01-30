<?php

declare(strict_types=1);

namespace Loy\Framework\Storage\Database;

use PDO;
use Error;
use Exception;
use PDOException;
use Loy\Framework\Base\Validator;
use Loy\Framework\Base\Exception\ValidationFailureException;

class MySQL
{
    private $config = [];
    private $conn   = null;
    private $dbname = null;
    private $table  = null;
    private $prefix = null;

    public function find(int $id)
    {
        $sql = 'SELECT * FROM '.$this->table().' WHERE `id` = ? LIMIT 1';

        return $this->get($sql, [$id]);
    }

    public function table() : string
    {
        return "`{$this->prefix}{$this->table}`";
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

    public function useDatabase(string $dbname)
    {
        $sql = "USE `{$dbname}`";

        $this->set($sql);

        return $this;
    }

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function validateConfig(array $config)
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
        } catch (ValidationFailureException $e) {
            dd($e->getMessage());
        }
    }

    public function conn()
    {
        if (! $this->conn) {
            $this->connect();
        }

        return $this->conn;
    }

    public function get(string $sql, array $params = null)
    {
        try {
            if (is_null($params)) {
                $res = $this->conn()->query($sql);
                dd($res);
            }

            $statement = $this->conn()->prepare($sql, [
                PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY,
            ]);

            $statement->execute($params);
            dd($statement->fetchAll());
        } catch (PDOException $e) {
            dd($e->getMessage(), $sql, $params);
        }
    }

    public function set(string $sql)
    {
        try {
            $this->conn()->exec($sql);
        } catch (PDOException $e) {
            dd($e->getMessage());
        } catch (Exception | Error $e) {
            dd($e->getMessage());
        }
    }

    public function connect(array $config = [])
    {
        $config = $config ?: $this->config;
        $config = $this->validateConfig($config);

        $host = $config['host'] ?? '';
        $port = $config['port'] ?? 3306;
        $user = $config['user'] ?? '';
        $pswd = $config['pswd'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $dbname  = $config['dbname']  ?? null;

        $dsn  = "mysql:host={$host};port={$port};charset={$charset}";

        if ($dbname) {
            $this->setDbname($dbname);
            $dsn .= ";dbname={$dbname}";
        }

        try {
            $this->conn = new PDO($dsn, $user, $pswd, [
                PDO::ATTR_PERSISTENT => true,
            ]);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $this->conn;
        } catch (Exception $e) {
            dd($e->getMessage());
        }
    }
}
