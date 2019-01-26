<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Storage\Database\MySQL;

final class DatabaseManager
{
    const CONFIG_FILE = 'database.php';
    const SUPPORT_DRIVERS = [
        'mysql' => MySQL::class,
    ];

    public static function validateConn(array $conn)
    {
        $_driver = $conn['driver'] ?? null;
        if ((! $_driver) || (! ($driver = self::SUPPORT_DRIVERS[$_driver] ?? false))) {
            throw new \Exception('Database Driver Not Suppert Yet: '.stringify($_driver));
        }

        $database = new $driver($conn);
        dd($database->connect());
    }
}
