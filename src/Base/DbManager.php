<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\ConfigManager;
use Loy\Framework\Storage\Database\MySQL;

final class DbManager
{
    const SUPPORT_DRIVERS = [
        'mysql' => MySQL::class,
    ];
    private static $pool = [];

    public static function init(string $domain, string $conn = null, string $dbname = null)
    {
        $conn = $conn ?: ConfigManager::getLatestByDomainRoot($domain, 'database.conn_default');
        if (! $conn) {
            throw new \Exception('Missing Database Connnection for Domain: '.$domain);
        }
        $pool   = ConfigManager::getLatestByDomainRoot($domain, 'database.conn_pool');
        $config = $pool[$conn] ?? false;
        if (false === $config) {
            throw new \Exception('Database Connnection Not Found: '."{$conn} ($domain)");
        }
        $driver = strtolower($config['driver'] ?? '');
        if (! $driver) {
            throw new \Exception('Missing Database Driver');
        }
        $db = self::SUPPORT_DRIVERS[$driver] ?? false;
        if (! $db) {
            throw new \Exception('Database Driver Not Suppert Yet: '.stringify($driver));
        }
        $key = join(':', [$driver, $conn]);
        $instance = self::$pool[$key] ?? false;
        if ((! $instance) || (! ($instance instanceof $db))) {
            if ($dbname) {
                $config['dbname'] = $dbname;
            }
            self::$pool[$key] = $instance = new $db($config);
        }

        return $instance;
    }
}
