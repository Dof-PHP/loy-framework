<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\ORMManager;
use Loy\Framework\Base\DomainManager;
use Loy\Framework\Base\DatabaseManager;

class ORM
{
    private $__dynamicProxyNamespace;

    public function find(int $id)
    {
        $this->prepare();
    }

    public function prepare()
    {
        $ormns = $this->__dynamicProxyNamespace;
        if ((! $ormns) || (! class_exists($ormns))) {
            throw new \Exception('ORM proxy class not found: '.$ormns);
        }
        $ormcfg = ORMManager::getOrm($ormns);
        if (! $ormcfg) {
            throw new \Exception('Illegal ORM proxy: '.$ormns);
        }
        $domain = DomainManager::getDomainByNamespace($ormns);
        if (! $domain) {
            throw new \Exception('Missing Domain Configurations of ORM: '.$orm);
        }
        $_domain  = join(DIRECTORY_SEPARATOR, [$domain, DomainManager::DOMAIN_FILE]);
        $database = join(DIRECTORY_SEPARATOR, [$domain, DatabaseManager::CONFIG_FILE]);
        $dbcfg    = [];
        if ($database && is_file($database)) {
            $dbcfg = load_php($database);
        } elseif ($_domain && is_file($_domain)) {
            $dbcfg = load_php($_domain);
            $dbcfg = $dbcfg['database'] ?? [];
        }
        if (! $dbcfg) {
            throw new \Exception('Missing Database Configrutions');
        }

        $conname = $ormcfg['meta']['CONNECTION'] ?? ($dbcfg['conn_default'] ?? null);
        if (! $conname) {
            throw new \Exception('Missing database connection name');
        }
        $conncfg = $dbcfg['conn_pool'][$conname] ?? null;
        if (! $conncfg) {
            throw new \Exception('Database connection config not found: '.$conname);
        }
        dd(DatabaseManager::validateConn($conncfg));
    }

    public function __getDynamicProxyNamespace()
    {
        return $this->__dynamicProxyNamespace;
    }

    public function __setDynamicProxyNamespace($namespace)
    {
        $this->__dynamicProxyNamespace = $namespace;

        return $this;
    }
}
