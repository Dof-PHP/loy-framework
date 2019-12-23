<?php

declare(strict_types=1);

namespace DOF;

use Throwable;
use DOF\Tracer;
use DOF\Traits\CompileCache;
use DOF\Exceptor\ContainerExceptor;
use DOF\Exceptor\DependencyInjectionFailed;
use DOF\Util\FS;
use DOF\Util\Arr;
use DOF\Util\Reflect;
use DOF\Util\TypeCast;
use DOF\Util\Annotation;

/**
 * Stateless classes/interface container - the key of dependency injection
 *
 * Compile logic of container is just load class/interface annotations
 * rather than pre-initialize objects, so it's stateless and no any side-effects
 */
final class Container
{
    use CompileCache;

    private static $classes = [];
    private static $interfaces = [];

    /**
     * Dependency injection for injectable class or interface
     *
     * @param string $namespace: expected namespace of expected class|interface
     * @param \DOF\Tracer $tracer
     * @return object
     */
    public static function di(string $namespace, Tracer $tracer = null)
    {
        $target = self::get($namespace);
        if (! $target) {
            throw new DependencyInjectionFailed('NAMESPACE_WITHOUT_CLASS', \compact('namespace'));
        }
        $class = $target['namespace'] ?? null;
        if (! $class) {
            throw new DependencyInjectionFailed('TARGET_WITHOUT_NAMESPACE', \compact('target', 'namespace'));
        }

        // Get class constructor definition
        // If class constructor not defined(simpliest)
        // Then just initialize that class and return
        $constructor = $target['constructor'] ?? null;
        if (! $constructor) {
            $instance = new $class;
            if ($tracer) {
                $instance->__TRACE_ROOT__ = $tracer->root;
                $instance->__TRACE_ID__ = $tracer->id + 1;
            }
            return $instance;
        }

        // DO NOT initialize non-public constructor
        if (! \in_array('public', ($constructor['modifiers'] ?? []))) {
            throw new DependencyInjectionFailed('NONPUBLIC_CONSTRUCTOR', \compact('class', 'namespace'));
        }

        // Parse class constructor parameters and di more classes recursively if necessary
        $params  = $constructor['parameters'] ?? [];
        $_params = [];    // Final parameters that $class constructor need
        foreach ($params as $param) {
            $name = $param['name'] ?? null;
            $type = $param['type'] ?? null;
            if (! $name) {
                continue;
            }
            if (! $type) {
                throw new DependencyInjectionFailed('METHOD_PARAMETER_TYPE_MISSING', \compact('class', 'namespace', 'name'));
            }
            if ($param['optional'] ?? false) {
                break;
            }
            if ($param['builtin'] ?? false) {
                if ($param['nullable'] ?? false) {
                    $_params[] = null;
                    continue;
                }
                throw new DependencyInjectionFailed('CONSTRUCTOR_REQUIRE_BUILTIN_PARAMETER', \compact('class', 'namespace', 'name', 'type'));
            }
            if (\class_exists($type) || interface_exists($type)) {
                if ($tracer) {
                    $tracer->id += 1;
                }
                $_params[] = self::di($type, $tracer);
            } else {
                throw new DependencyInjectionFailed('METHOD_PARAMETER_TYPE_MISSING', \compact('class', 'namespace', 'name', 'type'));
            }
        }

        try {
            $instance = new $class(...$_params);
            if ($tracer) {
                $instance->__TRACE_ROOT__ = $tracer->root;
                $instance->__TRACE_ID__ = $tracer->id + 1;
            }
            return $instance;
        } catch (Throwable $th) {
            throw new ContainerExceptor('DEPENDENCY_INJECTION_ERROR', \compact('namespace'), $th);
        }
    }

    /**
     * Build method parameters of class with possible values
     *
     * @param string $class: Namespace of class
     * @param string $method: Name of class method
     * @param array $values: Possible values of Methods/Functions parameters
     * @param \DOF\Tracer $tracer
     */
    public static function build(string $class, string $method, array $values = [], Tracer $tracer = null)
    {
        if (! \class_exists($class)) {
            throw new ContainerExceptor('CLASS_NOT_EXIST', \compact('class'));
        }
        if (! \method_exists($class, $method)) {
            throw new ContainerExceptor('CLASS_METHOD_NOT_EXIST', \compact('class', 'method'));
        }

        $parameters = Reflect::parseClassMethod($class, $method);
        $parameters = $parameters['parameters'] ?? [];

        return self::complete($parameters, $values, $tracer);
    }

    /**
     * Complete method/function actual require parameters from target values according do definition
     *
     * @param array $parameters: Methods/Function parameters definition list from reflection results
     * @param array $values: Possible values of Methods/Functions parameters
     * @param \DOF\Tracer $tracer
     * @return array: Final parameters method/funciton required
     */
    public static function complete(array $parameters, array $values = [], Tracer $tracer = null)
    {
        $result = [];

        $count = \count($parameters);

        foreach ($parameters as $idx => $parameter) {
            $name = $parameter['name'] ?? null;
            $type = $parameter['type'] ?? null;
            $builtin  = $parameter['builtin']  ?? false;
            $optional = $parameter['optional'] ?? false;
            $default  = $parameter['default']['status'] ?? false;

            $paramNameExists = \array_key_exists($name, $values);
            $paramIdxExists = isset($values[$idx]);
            if ($paramNameExists || $paramIdxExists) {
                $val = $paramNameExists ? ($values[$name] ?? null) : ($values[$idx] ?? null);
                if (\is_null($val) && (! $optional)) {
                    throw new ContainerExceptor('MISSING_METHOD_PARAMETER', \compact('parameter'));
                }

                try {
                    $val = TypeCast::typecast($type, $val);
                } catch (Throwable $th) {
                    throw new ContainerExceptor('PARAMETER_TYPE_MISMATCH', \compact('parameter', 'val'), $th);
                }

                $result[] = $val;
                continue;
            } elseif ((! $optional) && $builtin && (! $default)) {
                throw new ContainerExceptor('MISSING_METHOD_PARAMETER', \compact('parameter'));
            } elseif ($optional && (($idx + 1) !== $count)) {
                break;    // Ignore optional parameters
            } elseif ($builtin && $default) {
                $result[] = $parameter['default']['value'] ?? null;
                continue;
            }

            try {
                $result[] = Container::di($type, $tracer);
            } catch (Throwable $th) {
                throw new ContainerExceptor('BROKEN_METHOD_DEFINITION', \compact('parameter'), $th);
            }
        }

        return $result;
    }

    public static function init(string $path, bool $_cache = false)
    {
        if (\is_file($cache = self::formatCompileFile())) {
            list(self::$classes, self::$interfaces) = Arr::load($cache);
            return;
        }

        // reset
        self::$classes = [];
        self::$interfaces = [];

        if (! \is_dir($path)) {
            return;
        }

        self::scan($path);

        if ($_cache) {
            Arr::save([self::$classes, self::$interfaces], self::formatCompileFile());
        }
    }

    private static function scan(string $path)
    {
        FS::walk($path, function ($path) {
            $realpath = $path->getRealpath();
            if ($path->isDir()) {
                return self::scan($realpath);
            }

            if ($path->isFile() && ('php' === $path->getExtension())) {
                $ns = Reflect::getFileNamespace($realpath, true);
                if ($ns) {
                    self::add($ns);
                }
            }
        });
    }

    /**
     * Add one class or interface information by their namespace
     */
    private static function add(string $namespace)
    {
        if (\class_exists($namespace)) {
            return self::addByClass($namespace);
        }
        if (interface_exists($namespace)) {
            return self::addByInterface($namespace);
        }

        throw new ContainerExceptor('CLASS_OR_INTERFACE_NOT_EXISTS', \compact('namespace'));
    }

    /**
     * Add an interface implementor information to container by it's namespace
     */
    private static function addByInterface(string $namespace)
    {
        if (! interface_exists($namespace)) {
            throw new ContainerExceptor('INTERFACE_NOT_EXISTS', \compact('namespace'));
        }

        list($reflection, , ) = Annotation::parseNamespace($namespace);
        $implementor = $reflection['doc']['IMPLEMENTOR'] ?? null;
        if (! $implementor) {
            throw new ContainerExceptor('MISSING_INTERFACE_IMPLEMENTOR', \compact('namespace'));
        }
        if (! \class_exists($implementor)) {
            throw new ContainerExceptor('INTERFACE_IMPLEMENTOR_NOT_EXISTS', \compact('namespace', 'implementor'));
        }

        self::$interfaces[$namespace] = $implementor;

        $class = self::$classes[$implementor] ?? null;
        if ($class) {
            return $class;
        }

        return self::addByClass($implementor);
    }

    /**
     * Add a class constructor definitions to container by it's namespace
     */
    private static function addByClass(string $namespace)
    {
        if (! \class_exists($namespace)) {
            throw new ContainerExceptor('CLASS_NOT_EXISTS', \compact('namespace'));
        }

        return self::$classes[$namespace] = [
            'namespace'   => $namespace,
            'constructor' => Reflect::parseClassConstructor($namespace)
        ];
    }

    /**
     * Get class constructor definitions by namespace
     */
    public static function get(string $namespace) : array
    {
        $class = self::$classes[$namespace] ?? null;
        if ($class) {
            return $class;
        }

        $implementor = self::$interfaces[$namespace] ?? null;
        if ($implementor) {
            $class = self::$classes[$implementor] ?? null;
            if ($class) {
                return $class;
            }
        }

        // Lazy loading - add class in container when really need it
        return self::add($namespace);
    }

    public static function getInterfaces()
    {
        return self::$interfaces;
    }

    public static function getClasses()
    {
        return self::$classes;
    }
}
