<?php

declare(strict_types=1);

namespace Loy\Framework\Base\Database;

use PDO;
use Exception;
use Loy\Framework\Base\Validator;

class MySQL
{
    private $config = [];
    private $conn   = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function validateConfig(array $config)
    {
        try {
            $result = [];
            Validator::execute($config, [
                'host' => 'need|host',
                'port' => ['int', 'default' => 3306],
                'user' => 'need|string',
                'pswd' => 'need|string',
                'dbname'  => 'string|min:1',
                'charset' => ['string', 'default' => 'utf8mb4'],
            ], $result);

            return $result;
        } catch (\Exception $e) {
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
        $dsn .= $dbname ? ";dbname={$dbname}" : '';


        try {
            $this->conn = new PDO($dsn, $user, $pswd, [
                PDO::ATTR_PERSISTENT => true,
            ]);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            dd($e->getMessage());
        }
    }
}
