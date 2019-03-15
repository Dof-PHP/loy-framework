<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Storage\MySQL;
use Loy\Framework\Facade\Annotation;

final class StorageManager
{
    const SUPPORT_DRIVERS = [
        'mysql' => MySQL::class,
    ];
    private static $pool = [];

    /**
     * Initialize storage driver instance for storage class
     *
     * @param string $namespace: Namespace of storage class
     * @return Storage Instance
     */
    public static function get(string $namespace)
    {
        $domain = DomainManager::getDomainRootByNamespace($namespace);
        list($config, , ) = Annotation::parseNamespace($namespace);
        $config = $config['doc'] ?? [];
        $conn   = $config['CONNECTION'] ?? null;
        $dbname = $config['DATABASE'] ?? null;
        $table  = $config['TABLE'] ?? false;
        $prefix = $config['PREFIX'] ?? '';

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
        $storage = self::SUPPORT_DRIVERS[$driver] ?? false;
        if (! $storage) {
            exception('DatabaseDriverNotSuppert', ['driver' => stringify($driver)]);
        }
        $key = join(':', [$driver, $conn]);
        $instance = self::$pool[$key] ?? false;
        if ((! $instance) || (! ($instance instanceof $storage))) {
            if ($dbname) {
                $config['dbname'] = $dbname;
            }
            $instance = new $storage($config);
            $instance
            ->setDbname($dbname)
            ->setTable($table)
            ->setPrefix($prefix);

            self::$pool[$key] = $instance;
        }

        return $instance;
    }
}
