<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Throwable;
use Redis as RedisProxy;

/**
 * API docs: <https://github.com/phpredis/phpredis/blob/develop/README.markdown>
 */
class Redis implements StorageInterface
{
    private $config;

    private $connection;

    /** @var array: Commands executed in this instance lifetime */
    private $cmds;
    
    public function __construct(array $config = [])
    {
        if (! extension_loaded('redis')) {
            exception('RedisExtensionNotEnabled');
        }

        $this->config = collect($config);
    }

    public function connect()
    {
        $auth = $this->config->get('auth', false, ['in:0,1']);
        $host = $this->config->get('host', '', ['need', 'string']);
        $port = $this->config->get('port', 6379, ['uint']);
        $pswd = $auth ? $this->config->get('password', null, ['need', 'string']) : null;
        $timeout = $this->config->get('timeout', 3, ['int', 'min:0']);

        try {
            $this->connection = new RedisProxy;
            $this->connection->connect($host, $port, $timeout);
            if ($auth) {
                $this->connection->auth($pswd);
            }

            return $this->connection;
        } catch (Throwable $e) {
            exception('ConnectionToRedisFailed', compact('host', 'port'), $e);
        }
    }

    public function __call(string $method, array $params = [])
    {
        $this->appendCMD($method, ...$params);

        return $this->getConnection()->{$method}(...$params);
    }

    public function __logging()
    {
        return $this->cmds;
    }

    public function getConnection()
    {
        if (! $this->connection) {
            $this->connect();
        }

        return $this->connection;
    }

    private function appendCMD(...$params)
    {
        $this->cmds[] = join(' ', $params);

        return $this;
    }
}
