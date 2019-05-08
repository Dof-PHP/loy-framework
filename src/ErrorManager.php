<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Web\ERR;

final class ErrorManager
{
    const DOMAIN_ERR = ['Http', 'ERR.php'];

    private static $default = [];
    private static $domains = [];

    public static function loadDefault()
    {
        self::$default = get_class_consts(ERR::class);
    }

    public static function loadDomains()
    {
        $dirs = DomainManager::getDirs();
        foreach ($dirs as $dir) {
            $err = get_namespace_of_file(ospath($dir, self::DOMAIN_ERR), true);
            if ($err) {
                $domain = DomainManager::getKeyByNamespace($err);
                $consts = get_class_consts($err);
                self::$domains[$domain] = $consts;
            }
        }
    }

    public static function getDefault()
    {
        if (! self::$default) {
            self::loadDefault();
        }

        return self::$default;
    }

    public static function getDomains()
    {
        if (! self::$domains) {
            self::loadDomains();
        }

        return self::$domains;
    }
}
