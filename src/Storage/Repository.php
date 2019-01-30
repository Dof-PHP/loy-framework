<?php

declare(strict_types=1);

namespace Loy\Framework\Storage;

use Closure;
use Loy\Framework\Base\OrmManager;
use Loy\Framework\Base\Validator;
use Loy\Framework\Base\RepositoryManager;
use Loy\Framework\Base\DomainManager;
use Loy\Framework\Base\ConfigManager;
use Loy\Framework\Base\DatabaseManager;
use Loy\Framework\Base\Exception\MethodNotExistsException;
use Loy\Framework\Storage\Exception\OrmNotExistsException;
use Loy\Framework\Base\Exception\ValidationFailureException;

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
        return $this->execute('find', [$id]);
    }

    public function execute(string $api, array $argvs = [])
    {
        $storage = $this->prepare();

        if (! method_exists($storage, $api)) {
            dd('ApiNotSupportedByStorageException');
            // throw new ApiNotSupportedByStorageException($orm);
        }

        return call_user_func_array($storage, $argvs);
    }

    public function prepare()
    {
        $repository = RepositoryManager::get($this->__dynamicProxyNamespace);
        if (! is_array($repository)) {
            dd('Repository not found');
        }

        try {
            Validator::execute($repository, [
                'meta' => function () {
                    return [
                        'CONNECTION' => [
                            'string'
                        ],
                        'DATABASE' => [
                            'string'
                        ],
                        'PREFIX' => [
                            'string'
                        ],
                        'TABLE' => [
                            'need', 'string'
                        ],
                        'ORM' => [
                            'need', 'namespace'
                        ],
                    ];
                },
            ]);
        } catch (ValidationFailureException $e) {
            throw new \Exception('Bad Repository Annotation: '.$e->getMessage());
        }

        $connection = $repository['meta']['CONNECTION'] ?? null;
        $database   = $repository['meta']['DATABASE']   ?? null;
        $prefix     = $repository['meta']['PREFIX']     ?? null;
        $table      = $repository['meta']['TABLE']      ?? null;

        pp($connection, $database, $prefix, $table);

        $connDefault = ConfigManager::getLatestByNamespace(
            $this->__dynamicProxyNamespace,
            'database.conn_default'
        );
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
