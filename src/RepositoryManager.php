<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\DDD\Entity;
use Dof\Framework\DDD\Model;
use Dof\Framework\DDD\Storage;
use Dof\Framework\DDD\ORMStorage;
use Dof\Framework\DDD\KVStorage;
use Dof\Framework\DDD\Repository;
use Dof\Framework\DDD\ORMRepository;
use Dof\Framework\DDD\KVRepository;
use Dof\Framework\Facade\Annotation;

final class RepositoryManager
{
    const REPOSITORY_DIR = 'Repository';

    private static $dirs = [];
    private static $repositories = [];

    public static function load(array $dirs)
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$repositories) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_REPOSITORY_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([self::$dirs, self::$repositories], $cache);
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (is_file($cache)) {
            unlink($cache);
        }
    }

    public static function compile(array $dirs, bool $cache = false)
    {
        // Reset
        self::$dirs = [];
        self::$repositories = [];

        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = ospath($item, self::REPOSITORY_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Exceptions may thrown but let invoker to catch for different scenarios
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, , ) = $annotations;
                self::assemble($ofClass);
            }
        }, __CLASS__);

        if ($cache) {
            array2code([self::$dirs, self::$repositories], Kernel::formatCompileFile(__CLASS__));
        }
    }

    /**
     * Assemble Repository From Annotations
     */
    public static function assemble(array $ofClass)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if (! is_subclass_of($namespace, Repository::class)) {
            exception('InvalidRepositoryInterface', compact('namespace'));
        }
        if ($exists = (self::$repositories[$namespace] ?? false)) {
            exception('DuplicateRepositoryInterface', compact('exists'));
        }
        if (is_subclass_of($namespace, ORMRepository::class) && (! ($ofClass['doc']['ENTITY'] ?? false))) {
            exception('ORMRepositoryNoEntityToManage', compact('namespace'));
        }
        if (! ($ofClass['doc']['IMPLEMENTOR'] ?? false)) {
            exception('RepositoryNoImplementorToStorage', compact('namespace'));
        }

        self::$repositories[$namespace] = $ofClass['doc'] ?? [];
    }

    public static function __annotationFilterModel(string $model, array $ext, string $repository) : string
    {
        $model  = trim($model);
        $_model = get_annotation_ns($model, $repository);

        if ((! $_model) || (! class_exists($_model))) {
            exception('MissingOrModelNotExists', compact('model', 'repository'));
        }

        if (! is_subclass_of($_model, Model::class)) {
            exception('InvalidModelClass', compact('model', 'repository'));
        }

        return $_model;
    }

    public static function __annotationFilterEntity(string $entity, array $ext, string $repository) : string
    {
        $entity  = trim($entity);
        $_entity = get_annotation_ns($entity, $repository);

        if ((! $_entity) || (! class_exists($_entity))) {
            exception('MissingOrEntityNotExists', compact('entity', 'repository'));
        }

        if (! is_subclass_of($_entity, Entity::class)) {
            exception('InvalidEntityClass', compact('entity', 'repository'));
        }

        return $_entity;
    }

    public static function __annotationFilterImplementor(string $storage, array $ext, string $repository) : string
    {
        $storage  = trim($storage);
        $_storage = get_annotation_ns($storage, $repository);

        if ((! $_storage) || (! class_exists($_storage))) {
            exception('MissingOrStorageNotExists', compact('storage', 'repository'));
        }
        $__storage = Storage::class;
        if (is_subclass_of($repository, ORMRepository::class)) {
            $__storage = ORMStorage::class;
        } elseif (is_subclass_of($repository, KVRepository::class)) {
            $__storage = KVStorage::class;
        }
        if (! (is_subclass_of($storage, $__storage))) {
            $error = "Storage Not SubClass Of {$__storage}";
            exception('InvalidStorageClass', compact('error', 'storage', 'repository'));
        }
        if (! is_subclass_of($_storage, $repository)) {
            $error = "Storage Not SubClass Of {$repository}";
            exception('InvalidStorageClass', compact('error', 'storage', 'repository'));
        }

        return $_storage;
    }

    public static function get(string $namespace)
    {
        return self::$repositories[$namespace] ?? null;
    }

    public static function getRepositories()
    {
        return self::$repositories;
    }

    /**
     * Add ORM storage record into cache
     *
     * @param string $storage: Namespace of ORM storage class
     * @param Entity $entity: Entity object
     */
    public static function add(string $storage, Entity $entity)
    {
        self::update($storage, $entity);
    }

    /**
     * Remove ORM storage record from cache
     *
     * @param string $storage: Namespace of ORM storage class
     * @param Entity|int $entity: Entity object
     */
    public static function remove(string $storage, $entity)
    {
        if ((! is_int($entity)) && (! ($entity instanceof Entity))) {
            return false;
        }

        if (! self::isORMCacheEnabled($storage)) {
            return null;
        }

        $pk = is_int($entity) ? $entity : $entity->getId();
        if ($pk < 1) {
            return false;
        }

        $key = self::formatStorageCacheKey($storage, $pk);
        $cache = CacheManager::get(
            $storage,
            $key,
            ConfigManager::getDomainFinalEnvByNamespace($storage, 'ORM_STORAGE_CACHE')
        );
        if (! $cache) {
            return;
        }

        $cache->del($key);
    }

    public static function removes(string $storage, array $pks)
    {
        if (! self::isORMCacheEnabled($storage)) {
            return null;
        }

        $_cache = ConfigManager::getDomainFinalEnvByNamespace($storage, 'ORM_STORAGE_CACHE');

        foreach ($pks as $pk) {
            if (! is_int($pk)) {
                continue;
            }

            $key = self::formatStorageCacheKey($storage, $pk);
            $cache = CacheManager::get($storage, $key, $_cache);

            if (! $cache) {
                continue;
            }

            $cache->del($key);
        }
    }

    /**
     * Update/Reset ORM storage record in cache
     *
     * @param string $storage: Namespace of ORM storage class
     * @param Entity $entity: Entity object
     */
    public static function update(string $storage, Entity $entity)
    {
        if (! self::isORMCacheEnabled($storage)) {
            return null;
        }

        $key = self::formatStorageCacheKey($storage, $entity->getPk());
        $cache = CacheManager::get(
            $storage,
            $key,
            ConfigManager::getDomainFinalEnvByNamespace($storage, 'ORM_STORAGE_CACHE')
        );
        if (! $cache) {
            return;
        }

        $cache->set($key, $entity);
    }

    /**
     * Find ORM storage record in cacche and convert it into enity object
     *
     * @param string $storage: Namespace of ORM storage class
     * @param int $pk: Entity identity
     */
    public static function find(string $storage, int $pk) : ?Entity
    {
        if (! self::isORMCacheEnabled($storage)) {
            return null;
        }

        $key = self::formatStorageCacheKey($storage, $pk);
        $cache = CacheManager::get(
            $storage,
            $key,
            ConfigManager::getDomainFinalEnvByNamespace($storage, 'ORM_STORAGE_CACHE')
        );
        if (! $cache) {
            return null;
        }

        return $cache->get($key);
    }

    public static function isORMCacheEnabled(string $storage) : bool
    {
        $meta = StorageManager::get($storage)['meta'] ?? [];
        if (array_key_exists('CACHE', $meta)) {
            return boolval($meta['CACHE'] ?? 0);
        }

        return (bool) ConfigManager::getDomainFinalEnvByNamespace($storage, 'ENABLE_ORM_CACHE', false);
    }

    public static function formatStorageCacheKey(string $storage, int $pk) : string
    {
        $domain = DomainManager::getKeyByNamespace($storage);
        $driver = StorageManager::get($storage)['meta']['DRIVER'];
        $ns = array_trim_from_string($storage, '\\');
        $name = $ns[count($ns) - 1] ?? 'unknown';

        return join(':', [CacheManager::CACHE_PREFIX, strtolower(join('_', [$domain, $driver, $name, $pk]))]);
    }

    /**
     * Mapping a storage result into an entity/model object
     *
     * @param string $storage: Namespace of storage class
     * @param array $result: An assoc array holds entity/model data
     */
    public static function map(string $storage, array $result = null) : ?Model
    {
        if (! $result) {
            return null;
        }

        $_storage = Storage::class;
        if (! is_subclass_of($storage, $_storage)) {
            $error = "Not SubClass Of {$_storage}";
            exception('InvalidStorageToConvert', compact('storage', 'error'));
        }

        $_storage = StorageManager::get($storage);
        if (! $_storage) {
            exception('StorageClassNotFound', compact('storage'));
        }
        $repository = $_storage['meta']['REPOSITORY'] ?? null;
        if (! $repository) {
            exception('NoRepositoryBoundStorage', compact('storage'));
        }
        $_repository = self::get($repository);
        if (! $_repository) {
            exception('StorageRepositoryNotFound', compact('repository', 'storage'));
        }
        $implementor = $_repository['IMPLEMENTOR'] ?? null;
        if ($implementor !== $storage) {
            exception('InvalidStorageImplementor', compact('repository', 'implementor', 'storage'));
        }
        $model = $_repository['ENTITY'] ?? ($_repository['MODEL'] ?? null);
        if (! $model) {
            exception('NoEntityOrModelBindToRepository', compact('repository'));
        }
        if (is_subclass_of($model, Entity::class)) {
            $entity = $model;
            $_entity = EntityManager::get($model);
            if (! $_entity) {
                exception('BadEntityWithoutAnnotation', compact('repository', 'entity'));
            }
            $instance = new $model;
            foreach ($result as $column => $val) {
                if (! isset($_storage['columns'][$column])) {
                    continue;
                }
                $attribute = $_storage['columns'][$column];
                if (! isset($_entity['properties'][$attribute])) {
                    continue;
                }
                $property = $_entity['properties'][$attribute] ?? [];
                if ($property['NOMAP'] ?? false) {
                    continue;
                }
                $type = $property['TYPE'] ?? null;
                if (! TypeHint::support($type)) {
                    exception('UnsupportedEntityType', compact('type', 'attribute', 'entity'));
                }

                $instance->{$attribute} = TypeHint::convert($val, $type, true);
            }

            if (is_null($instance->getId())) {
                exception('EntityIdentityMissing', compact('entity', 'result'));
            }
        } elseif (is_subclass_of($model, Model::class)) {
            $_model = ModelManager::get($model);
            if (! $_model) {
                exception('BadModelWithoutAnnotation', compact('repository', 'model'));
            }
            $instance = new $model;
            foreach ($result as $key => $val) {
                if (! property_exists($instance, $key)) {
                    continue;
                }
                $property = $_model['properties'][$key] ?? [];
                if ($property['NOMAP'] ?? false) {
                    continue;
                }
                $type = $property['TYPE'] ?? null;
                if (! TypeHint::support($type)) {
                    exception('UnsupportedEntityType', compact('type', 'attribute', 'model'));
                }

                $instance->{$key} = TypeHint::convert($val, $type, true);
            }
        } else {
            exception('InvalidEntityOrModelBindToRepository', compact('repository', 'model'));
        }

        return $instance;
    }
}
