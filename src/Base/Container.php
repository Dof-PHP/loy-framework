<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\Reflector;

/**
 * Classes container - the key of dependency injection
 */
class Container
{
    private static $classes  = [];
    private static $filenskv = [];    // filepath => namespace

    /**
     * Dependency injection for injectable class
     *
     * @param mixed $ns: expected namespace of expected class
     */
    public static function di(string $ns)
    {
        $class = self::$classes[$ns] ?? false;
        if (! $class) {
            // Lazy loading - add class in container when really need it
            $class = self::add($ns);
        }

        // Get class constructor definition
        $constructor = $class['constructor']['self'] ?? false;
        if (! $constructor) {
            $constructor = $class['constructor']['parent'] ?? false;
            // If class constructor not defined(simpliest)
            // Then just initialize that class and return
            if (! $constructor) {
                return new $ns;
            }
        }

        // Do not initialize non-public constructor
        if (! in_array('public', ($constructor['modifiers'] ?? []))) {
            exception('UnInjectableDependency', [
                '__error' => 'Non-public constructor',
                'class'   => $ns
            ]);
        }

        // Parse class constructor parameters and di more classes recursively if necessary
        $params  = $constructor['parameters'] ?? [];
        $_params = [];    // Final parameters that $class constructor need
        foreach ($params as $param) {
            $name = $param['name'] ?? false;
            $type = $param['type']['type'] ?? false;
            if ((! $name) || (! $type)) {
                continue;
            }
            if ($param['optional'] ?? false) {
                break;
            }
            if ($param['type']['builtin'] ?? false) {
                if ($param['nullable'] ?? false) {
                    $_params[] = null;
                    continue;
                }
                exception('UnInjectableDependency', [
                    '__error' => 'Constructor has builtin required parameter',
                    'class' => $ns,
                    'type'  => $type,
                    'name'  => $name,
                ]);
            }
            if (class_exists($type)) {
                $_params[] = self::di($type);
            }
        }

        return new $ns(...$_params);
    }

    /**
     * Build container classes by directories
     */
    public static function build(array $dirs)
    {
        foreach ($dirs as $domain => $meta) {
            self::load($domain, $domain, [$meta]);
        }
    }

    /**
     * Load classes by domain
     */
    private static function load(string $dir, string $domain, array $exclude = [])
    {
        walk_dir($dir, function ($path) use ($domain, $exclude) {
            $realpath = $path->getRealpath();
            if ($path->isDir()) {
                if ($exclude && in_array($realpath, $exclude)) {
                    return;
                }
                return self::load($realpath, $domain);
            }

            if ($path->isFile() && ('php' === $path->getExtension())) {
                $ns = get_namespace_of_file($realpath, true);
                self::add($ns, $realpath, $domain);
            }
        });
    }

    /**
     * Add one class information to container
     */
    public static function add(string $namespace, string $realpath = null, string $domain = null)
    {
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('ClassNotExists', ['class' => $namespace]);
        }

        $realpath = $realpath ?: get_file_of_namespace($namespace);
        if (! $realpath) {
            exception('ClassFileNotFound', ['class' => $namespace]);
        }
        $domain = $domain ?: DomainManager::getDomainRootByFilePath($realpath);
        if (! $domain) {
            exception('DomainNotFound', ['filepath' => $realpath]);
        }

        self::$filenskv[$realpath] = $namespace;
        self::$classes[$namespace] = $class = [
            'filepath'    => $realpath,
            'domain'      => $domain,
            'constructor' => Reflector::getClassConstructor($namespace),
        ];

        return $class;
    }

    public static function getFilenskv()
    {
        return self::$filenskv;
    }

    public static function getClass($ns)
    {
        return self::$classes[$ns] ?? null;
    }

    public static function getClasses()
    {
        return self::$classes;
    }
}
