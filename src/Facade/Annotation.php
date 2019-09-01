<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\Facade;
use Dof\Framework\Annotation as Instance;

class Annotation extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;

    /** @var array: Annotations result cache of class or interface */
    private static $results  = [];

    /** @var array: Annotation namespace of class or interface and file map */
    private static $filenskv = [];

    /**
     * Get annotations of class or interface by namespace
     *
     * @param string $filepath
     * @param string $origin
     * @param bool $cache
     * @return array
     */
    public static function getByFilepath(string $filepath, string $origin = null, bool $cache = true) : array
    {
        $namespace = self::$filenskv[$filepath] ?? false;
        if (! $namespace) {
            $namespace = get_namespace_of_file($filepath, true);
        }

        return self::getByNamespace($namespace, $origin, $cache);
    }

    /**
     * Get annotations of class or interface by namespace
     *
     * @param string $namespace
     * @param string $origin
     * @param bool $cache
     * @return array
     */
    public static function getByNamespace(string $namespace, string $origin = null, bool $cache = true) : array
    {
        if (! $namespace) {
            return [];
        }
        if (! $cache) {
            return self::getInstance()->parseNamespace($namespace, $origin);
        }

        $result = self::$results[$namespace] ?? false;
        if ($result) {
            return $result;
        }

        $filepath = get_file_of_namespace($namespace);
        if ($filepath) {
            self::$filenskv[$filepath] = $namespace;
        }

        return self::$results[$namespace] = self::getInstance()->parseNamespace($namespace, $origin);
    }

    public static function get(string $target, string $origin = null, bool $file = false, bool $cache = true) : array
    {
        return $file ? self::getByFilepath($target, $origin, $cache) : self::getByNamespace($target, $origin, $cache);
    }
}
