<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\DDD\Entity;
use Loy\Framework\DDD\Storage;
use Loy\Framework\Facade\Annotation;

final class RepositoryManager
{
    const REPOSITORY_DIR = 'Repository';

    private static $dirs = [];
    private static $repositories = [];

    /**
     * Convert a storage result into entity object
     */
    public static function convert(string $storage, array $result = null) : ?Entity
    {
        if (! $result) {
            return null;
        }

        $_storage = Storage::class;
        if (! is_subclass_of($storage, $_storage)) {
            $error = "Not SubClass Of {$_storage}";
            exception('InvalidStorageToConvert', compact('storage', 'error'));
        }

        $orm = StorageManager::get($storage);
        if (! $orm) {
            exception('StorageClassNotFound', compact('orm'));
        }
        $repository = $orm['meta']['REPOSITORY'] ?? null;
        if (! $repository) {
            exception('NoRepositoryBoundOrm', compact('repository', 'orm'));
        }
        $_repository = self::get($repository);
        if (! $_repository) {
            exception('RepositoryNotFound', compact('repository'));
        }
        $implementor = $_repository['IMPLEMENTOR'] ?? null;
        $entity = $_repository['ENTITY'] ?? null;
        if ($implementor !== $storage) {
            exception('InvalidStorageImplementor', compact('repository', 'implementor', 'storage'));
        }
        $_entity  = EntityManager::get($entity);
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

            $instance->{$attribute} = TypeHint::convert($val, $type);
        }
        if (is_null($instance->getId())) {
            $error = 'Entity identity not exists';
            exception('ConvertEntityFailed', compact('entity', 'error', 'result'));
        }

        return $instance;
    }

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs = [];
        self::$repositories = [];

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
        if ($exists = (self::$repositories[$namespace] ?? false)) {
            exception('DuplicateRepositoryInterface', compact('exists'));
        }

        self::$repositories[$namespace] = $ofClass['doc'] ?? [];
    }

    public static function __annotationFilterEntity(string $entity) : string
    {
        if (! class_exists($entity)) {
            exception('EntityNotExists', compact('entity'));
        }
        if (! is_subclass_of($entity, Entity::class)) {
            exception('InvalidEntityClass', compact('entity'));
        }

        return trim($entity);
    }

    public static function __annotationFilterImplementor(string $storage) : string
    {
        if (! class_exists($storage)) {
            exception('StorageNotExists', compact('storage'));
        }
        if (! (is_subclass_of($storage, Storage::class))) {
            exception('InvalidStorageClass', compact('storage'));
        }

        return trim($storage);
    }

    public static function get(string $namespace)
    {
        return self::$repositories[$namespace] ?? null;
    }

    public static function getRepositories()
    {
        return self::$repositories;
    }
}
