<?php

declare(strict_types=1);

namespace Loy\Framework\Storage;

use Closure;
use Loy\Framework\Base\OrmManager;
use Loy\Framework\Base\RepositoryManager;
use Loy\Framework\Base\DomainManager;
use Loy\Framework\Base\DatabaseManager;
use Loy\Framework\Base\Exception\MethodNotExistsException;
use Loy\Framework\Storage\Exception\OrmNotExistsException;

/**
 * The repository abstract persistence access, whatever storage it is. That is its purpose.
 * THe fact that you're using a db or xml files or an ORM doesn't matter. The Repository allows the rest of the application to ignore persistence details.
 * This way, you can easily test the app via mocking or stubbing and you can change storages if it's needed.
 *
 * Repositories deal with Domain/Business objects (from the app point of view), an ORM handles db objects.
 * A business objects IS NOT a db object, first has behaviour, the second is a glorified DTO, it only holds data.
 */
class Repository
{
    private $__dynamicProxy;
    private $__dynamicProxyNamespace;

    public function find(int $id)
    {
        $this->execute('find', [$id]);
    }

    public function execute(string $api, array $argvs = [])
    {
        $storage = $this->prepare();

        if (! method_exists($storage, $api)) {
            dd('ApiNotSupportedByStorageException');
            // throw new ApiNotSupportedByStorageException($orm);
        }
    }

    public function prepare()
    {
        $repository = RepositoryManager::get($this->__dynamicProxyNamespace);
        $orm = $repository['meta']['ORM'] ?? false;
        if ((! $orm) || (! class_exists($orm))) {
            throw new OrmNotExistsException($orm);
        }

        $domain = DomainManager::initFromNamespace($orm);
        dd($domain->parent());
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

    public function __call(string $method, array $argvs = [])
    {
        $proxy = $this->__getDynamicProxy();
        if (! $proxy) {
            throw new MethodNotExistsException(join('@', [__CLASS__, $method]));
        }
        if (! method_exists($proxy, $method)) {
            throw new MethodNotExistsException(join('@', [$this->__dynamicProxyNamespace, $method]));
        }

        return call_user_func_array([$proxy, $method], $argvs);
    }

    public function __getDynamicProxy()
    {
        if (! $this->__dynamicProxy) {
            if ($this->__dynamicProxyNamespace && class_exists($this->__dynamicProxyNamespace)) {
                return $this->__dynamicProxy = new $this->__dynamicProxyNamespace;
            }

            return null;
        }

        return $this->__dynamicProxy;
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
