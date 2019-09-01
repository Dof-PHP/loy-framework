<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use PDO;

// use Redis;

final class Connection
{
    private static $pool = [
        'mysql' => [],
        'redis' => [],
        'memcached' => [],
    ];

    public static function get(
        string $driver,
        string $connection,
        iterable $config = []
    ) {
        $_driver = strtolower($driver);

        if (! array_key_exists($_driver, self::$pool)) {
            exception('ConnectionDriverNotSupportYet', compact('driver'));
        }

        $conn = self::$pool[$_driver][$connection] ?? null;
        if ($conn) {
            return $conn;
        }

        return call_user_func_array([__CLASS__, $_driver], [$connection, $config]);
    }

    public static function mysql(string $connection, iterable $config = []) : PDO
    {
        $config = is_collection($config) ? $config : collect($config);

        $host = $config->get('host', '', ['string']);
        $port = $config->get('port', 3306, ['pint']);
        $user = $config->get('user');
        $pswd = $config->get('passwd', '');
        $charset = $config->get('charset', 'utf8mb4', ['string']);
        $dbname  = $config->get('dbname', '', ['string']);

        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        if ($dbname) {
            $dsn .= ";dbname={$dbname}";
        }

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ];

            if ($config->get('persistent')) {
                $options[PDO::ATTR_PERSISTENT] = true;
            }

            // if ($timeout = $config->get('timeout', 0, ['pint'])) {
            // $options[PDO::ATTR_TIMEOUT] = $timeout;
            // }

            $pdo = new PDO($dsn, $user, $pswd, $options);

            // $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            return self::$pool['mysql'][$connection] = $pdo;
        } catch (Throwable $e) {
            exception('ConnectionToMySQLFailed', compact('dsn'), $e);
        }
    }

    public static function redis(string $connection, iterable $config = []) : \Redis
    {
        if (! extension_loaded('redis')) {
            exception('MissingRedisExtension');
        }

        $config = is_collection($config) ? $config : collect($config);

        $auth = $config->get('auth', false, ['in:0,1']);
        $host = $config->get('host', '', ['need', 'string']);
        $port = $config->get('port', 6379, ['uint']);
        $pswd = $auth ? $config->get('password', null, ['need', 'string']) : null;
        $dbnum = $config->get('database', 15, ['uint']);
        $timeout = $config->get('timeout', 3, ['int']);

        try {
            $redis = new \Redis;
            $redis->connect($host, $port, $timeout);
            if ($auth) {
                $redis->auth($pswd);
            }
            if ($dbnum) {
                $redis->select($dbnum);
            }

            return self::$pool['redis'][$connection] = $redis;
        } catch (Throwable $e) {
            exception('ConnectionToRedisFailed', compact('host', 'port'), $e);
        }
    }

    public static function memcached(string $connection, iterable $config = []) : \Memcached
    {
        if (! extension_loaded('memcached')) {
            exception('MissingMemcachedExtension');
        }

        $config = is_collection($config) ? $config : collect($config);

        $memcached = new \Memcached($connection);
        if ($config->tcp_nodelay ?? true) {
            $memcached->setOption(\Memcached::OPT_TCP_NODELAY, true);
        }
        if (! ($config->compression ?? false)) {
            $memcached->setOption(\Memcached::OPT_COMPRESSION, false);
        }
        if ($config->binary_protocol ?? false) {
            $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
        }
        if ($config->libketama_compatible ?? true) {
            $memcached->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
        }

        $host = $config->get('host', '', ['need', 'string']);
        $port = $config->get('port', 11211, ['need', 'pint']);
        $weight = $config->get('weight', 0, ['need', 'int']);

        $memcached->addServer($host, $port, $weight);

        if ($config->get('sasl_auth', false)) {
            $user = $config->get('sasl_user', '', ['string']);
            $pswd = $config->get('sasl_pswd', '', ['string']);

            $memcached->setSaslAuthData($user, $pswd);
        }

        if (false === $memcached->getStats()) {
            exception('LostConnectionToMemcached', compact('host', 'port'));
        }

        return $memcached;
    }
}
