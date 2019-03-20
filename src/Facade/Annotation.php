<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Annotation as Instance;

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
     * @return array
     */
    public static function getByFilepath(string $filepath) : array
    {
        $namespace = self::$filenskv[$filepath] ?? false;
        if (! $namespace) {
            $namespace = get_namespace_of_file($filepath, true);
        }

        return self::getByNamespace($namespace);
    }

    /**
     * Get annotations of class or interface by namespace
     *
     * @param string $namespace
     * @return array
     */
    public static function getByNamespace(string $namespace) : array
    {
        if (! $namespace) {
            return [];
        }

        $result = self::$results[$namespace] ?? false;
        if ($result) {
            return $result;
        }

        $filepath = get_file_of_namespace($namespace);
        if ($filepath) {
            self::$filenskv[$filepath] = $namespace;
        }

        return self::$results[$namespace] = self::getInstance()->parseNamespace($namespace);
    }

    public static function get(string $target, bool $file = false) : array
    {
        return $file ? self::getByFilepath($target) : self::getByNamespace($target);
    }
}
