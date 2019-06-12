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
        $cache = Kernel::formatCacheFile(__CLASS__);
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
        $cache = Kernel::formatCacheFile(__CLASS__);
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
            array2code([self::$dirs, self::$repositories], Kernel::formatCacheFile(__CLASS__));
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
        // TODO
    }

    /**
     * Remove ORM storage record from cache
     *
     * @param string $storage: Namespace of ORM storage class
     * @param Entity|int $entity: Entity object
     */
    public static function remove(string $storage, $entity)
    {
        // TODO
    }

    /**
     * Update/Reset ORM storage record in cache
     *
     * @param string $storage: Namespace of ORM storage class
     * @param Entity $entity: Entity object
     */
    public static function update(string $storage, Entity $entity)
    {
        // TODO
    }

    /**
     * Find ORM storage record in cacche and convert it into enity object
     *
     * @param string $storage: Namespace of ORM storage class
     * @param int $pk: Entity identity
     */
    public static function find(string $storage, int $pk) : ?Entity
    {
        // TODO
        return null;
    }

    /**
     * Mapping a storage result into an entity object
     *
     * @param string $storage: Namespace of ORM storage class
     * @param array $result: An assoc array holds entity data
     */
    public static function map(string $storage, array $result = null) : ?Entity
    {
        if (! $result) {
            return null;
        }

        $_storage = ORMStorage::class;
        if (! is_subclass_of($storage, $_storage)) {
            $error = "Not SubClass Of {$_storage}";
            exception('InvalidStorageToConvert', compact('storage', 'error'));
        }

        $orm = StorageManager::get($storage);

        if ($orm['meta']['SOFTDELETE'] ?? false) {
            $orm['columns']['is_deleted'] = 'isDeleted';
        }

        if (! $orm) {
            exception('ORMStorageClassNotFound', compact('orm'));
        }
        $repository = $orm['meta']['REPOSITORY'] ?? null;
        if (! $repository) {
            exception('NoRepositoryBoundStorage', compact('storage'));
        }
        $_repository = self::get($repository);
        if (! $_repository) {
            exception('ORMStorageRepositoryNotFound', compact('repository', 'storage'));
        }
        $implementor = $_repository['IMPLEMENTOR'] ?? null;
        if ($implementor !== $storage) {
            exception('InvalidStorageImplementor', compact('repository', 'implementor', 'storage'));
        }
        $entity = $_repository['ENTITY'] ?? null;
        if (! $entity) {
            exception('NoEntityBindToORMRepository', compact('repository'));
        }
        if (! is_subclass_of($entity, Entity::class)) {
            exception('InvalidEntityBindToORMRepository', compact('repository', 'entity'));
        }
        $_entity = EntityManager::get($entity);
        if (! $_entity) {
            exception('BadEntityWithoutAnnotation', compact('repository', 'entity'));
        }
        $instance = new $entity;
        foreach ($result as $column => $val) {
            if (! isset($orm['columns'][$column])) {
                continue;
            }
            $attribute = $orm['columns'][$column];
            if (! isset($_entity['properties'][$attribute])) {
                continue;
            }
            $property = $_entity['properties'][$attribute];
            if ($property['NOTORM'] ?? false) {
                continue;
            }
            $type = $property['TYPE'] ?? null;
            if (! TypeHint::support($type)) {
                exception('UnsupportedEntityType', compact('type', 'attribute', 'entity'));
            }
            $setter = 'set'.ucfirst($attribute);

            $instance->{$setter}(TypeHint::convert($val, $type));
        }

        if (is_null($instance->getId())) {
            exception('EntityIdentityMissing', compact('entity', 'result'));
        }

        return $instance;
    }
}
