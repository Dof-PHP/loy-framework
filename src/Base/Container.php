<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\Reflector;

class Container
{
    private static $classes  = [];
    private static $filenskv = [];    // filepath => namespace

    public static function di($ns)
    {
        if (! class_exists($ns)) {
            return $ns;
        }

        $class = self::$classes[$ns] ?? false;
        if (! $class) {
            return null;
        }

        $constructor = $class['constructor']['self'] ?? false;
        if (! $constructor) {
            $constructor = $class['constructor']['parent'] ?? false;
            if (! $constructor) {
                return new $ns;
            }
        }

        if (! in_array('public', ($constructor['modifiers'] ?? []))) {
            exception('UnInjectableDependency', [
                '__error' => 'Non-public constructor',
                'class'   => $ns
            ]);
        }

        $params  = $constructor['parameters'] ?? [];
        $_params = [];
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

    public static function build(array $dirs)
    {
        foreach ($dirs as $domain => $meta) {
            self::load($domain, $domain, [$meta]);
        }
    }

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
                if ($ns && class_exists($ns)) {
                    self::$filenskv[$realpath] = $ns;
                    self::$classes[$ns] = [
                        'filepath'    => $realpath,
                        'domain'      => $domain,
                        'constructor' => Reflector::getClassConstructor($ns),
                    ];
                }
            }
        });
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
