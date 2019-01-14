<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Core\Collection;

class Route
{
    private static $data = [];
    private static $instance = null;

    public static function setData(array $data)
    {
        $data['all'] = RouteManager::getRoutes();
        
        self::$data = $data;
    }

    public static function getInstance()
    {
        if (! self::$instance) {
            self::$instance = new Collection(self::$data, __CLASS__);
        }

        return self::$instance;
    }
}
