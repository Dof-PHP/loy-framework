<?php

declare(strict_types=1);

namespace Loy\Framework;

use Throwable;

abstract class Facade
{
    protected static $singleton = true;
    private static $__pool = [];

    public function __call(string $method, array $argvs = [])
    {
        $instance = self::getInstance();

        if (method_exists($instance, '__setDynamicProxyNamespace')) {
            $instance->__setDynamicProxyNamespace(static::class);
        }

        return call_user_func_array([$instance, $method], $argvs);
    }

    public static function __callStatic(string $method, array $argvs = [])
    {
        $instance = self::getInstance();

        if (true
            && method_exists(static::class, '__getDynamicProxyNamespace')
            && method_exists($instance, '__setDynamicProxyNamespace')
        ) {
            $instance->__setDynamicProxyNamespace(static::__getDynamicProxyNamespace());
        }

        return call_user_func_array([$instance, $method], $argvs);
    }

    public static function getInstance()
    {
        $namespace = static::__getProxyNamespace();
        $singleton = static::__isProxySingleton();
        if (! $singleton) {
            return new $namespace;
        }

        if (! (self::$__pool[static::class] ?? false) instanceof $namespace) {
            self::$__pool[static::class] = new $namespace;
        }

        return self::$__pool[static::class];
    }

    /**
     * Get proxy singleton from private static property $singleton by default
     * Or get from overrided method __isProxySingleton() of subclass
     */
    protected static function __isProxySingleton()
    {
        $child = static::class;

        try {
            return $child::$singleton;
        } catch (Throwable $e) {
            return true;
        }
    }


    /**
     * Get proxy namesapce from private static property $namespace by default
     * Or get from overrided method __getProxyNamespace() of subclass
     */
    protected static function __getProxyNamespace()
    {
        $child = static::class;

        try {
            return $child::$namespace;
        } catch (Throwable $e) {
            return get_called_class();
        }
    }

    public static function new(...$params)
    {
        $proxy = static::__getProxyNamespace();

        return new $proxy(...$params);
    }
}
