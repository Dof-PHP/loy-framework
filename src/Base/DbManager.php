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
            exception('MissingDomainDatabaseConnnection', ['domain' => $domain]);
        }
        $pool   = ConfigManager::getLatestByDomainRoot($domain, 'database.conn_pool');
        $config = $pool[$conn] ?? false;
        if (false === $config) {
            exception('DatabaseConnnectionNotFound', [
                'connection' => $conn,
                'domain'     => $domain,
            ]);
        }
        $driver = strtolower($config['driver'] ?? '');
        if (! $driver) {
            exception('MissingDatabaseDriver');
        }
        $db = self::SUPPORT_DRIVERS[$driver] ?? false;
        if (! $db) {
            exception('Database Driver Not Suppert Yet', ['driver' => stringify($driver)]);
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
