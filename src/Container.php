<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;

/**
 * Classes container - the key of dependency injection
 */
final class Container
{
    private static $classes    = [];
    private static $filenskv   = [];    // filepath => namespace
    private static $interfaces = [];

    /**
     * Dependency injection for injectable class or interface
     *
     * @param string $namespace: expected namespace of expected class|interface
     * @return object
     */
    public static function di(string $namespace)
    {
        $class = self::get($namespace);
        if (! ($ns = $class['namespace'] ?? false)) {
            exception('ClassNamespaceMissing', compact('namespace', 'class'));
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
                'error' => 'Non-public constructor',
                'class' => $ns
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
                    'error' => 'Constructor has builtin required parameter',
                    'class' => $ns,
                    'type'  => $type,
                    'name'  => $name,
                ]);
            }
            if (class_exists($type) || interface_exists($type)) {
                $_params[] = self::di($type);
            }
        }

        return new $ns(...$_params);
    }

    /**
     * Build method parameters of class with possible values
     *
     * @param string $class: Namespace of class
     * @param string $method: Name of class method
     * @param iterable $values: Possible values of Methods/Functions parameters
     */
    public static function build(string $class, string $method, $values = null)
    {
        if (! class_exists($class)) {
            exception('ClassToBuildMethodParameterNotExists', compact('class'));
        }
        if (! method_exists($class, $method)) {
            exception('ClassMethodToBuildParameterNotExists', compact('class', 'method'));
        }
        if ($values && (! is_iterable($values))) {
            exception('UnIterableParametersValuesToBuild', compact('values'));
        }

        $parameters = Reflector::getClassMethod($class, $method);
        $parameters = $parameters['self']['parameters'] ?? ($parameters['parent']['parameters'] ?? []);

        return self::complete($parameters, $values);
    }

    /**
     * Complete method/function actual require parameters from target values according do definition
     *
     * @param iterable $methods: Methods/Function parameters definition list from annotation reflection results
     * @param iterable $values: Possible values of Methods/Functions parameters
     * @return array: Final parameters method/funciton required
     */
    public static function complete($parameters, $values = null)
    {
        if (! is_iterable($parameters)) {
            exception('UnIterableMethodParametersToComplete', compact('parameters'));
        }
        if ($values && (! is_iterable($values))) {
            exception('UnIterableParametersValuesToComplete', compact('values'));
        }

        $params = [];
        $count  = count($parameters);
        foreach ($parameters as $idx => $parameter) {
            $name = $parameter['name'] ?? null;
            $type = $parameter['type']['type'] ?? null;
            $builtin  = $parameter['type']['builtin'] ?? false;
            $optional = $parameter['optional'] ?? false;
            $default  = $parameter['default']['status'] ?? false;

            $paramNameExists = is_collection($values) ? $values->has($name) : (
                is_array($values) ? array_key_exists($name, $values) : false
            );
            $paramIdxExists  = isset($values[$idx]);
            if ($paramNameExists || $paramIdxExists) {
                $val = $paramNameExists ? ($values[$name] ?? null) : ($values[$idx] ?? null);
                if (is_null($val) && (! $optional)) {
                    exception('MissingMethodParameter', compact('parameter'));
                }

                try {
                    $val = TypeHint::convert($val, $type);
                } catch (Throwable $e) {
                    exception('TypeHintFailedWhenCompleteParameters', compact('parameter', 'val'), $e);
                }
                $params[] = $val;
                continue;
            } elseif ((! $optional) && $builtin && (! $default)) {
                exception('MissingMethodParametersToComplete', compact('parameter', 'name', 'type'));
            } elseif ($optional && (($idx + 1) !== $count)) {
                break;    // Ignore optional parameters check
            } elseif ($builtin && $default) {
                $params[] = $parameter['default']['value'] ?? null;
                continue;
            }

            try {
                $params[] = Container::di($type);
            } catch (Throwable $e) {
                exception('BrokenMethodDefinitionForCompleting', compact('parameter'), $e);
            }
        }

        return $params;
    }

    /**
     * Compile classes or interfaces in domain's paths
     *
     * @param array $dirs: Domain root list
     */
    public static function compile(array $dirs)
    {
        foreach ($dirs as $domain) {
            self::load($domain, $domain);
        }
    }

    /**
     * Load classes by domain
     */
    private static function load(string $dir, string $domain)
    {
        walk_dir($dir, function ($path) use ($domain) {
            $realpath = $path->getRealpath();
            if ($path->isDir()) {
                return self::load($realpath, $domain);
            }

            if ($path->isFile() && ('php' === $path->getExtension())) {
                $ns = get_namespace_of_file($realpath, true);
                if ($ns) {
                    self::add($ns, $realpath, $domain);
                }
            }
        });
    }

    /**
     * Get class in container by namespace
     */
    public static function get(string $namespace) : array
    {
        $class = self::$classes[$namespace] ?? false;
        if ($class) {
            return $class;
        }

        $implementor = self::$interfaces[$namespace]['implementor'] ?? false;
        if ($implementor) {
            $class = self::$classes[$implementor] ?? false;
            if ($class) {
                return $class;
            }
        }

        // Lazy loading - add class in container when really need it
        return self::add($namespace);
    }

    /**
     * Add one class information to container by the namespace of class or interface
     */
    public static function add(string $namespace, string $realpath = null, string $domain = null)
    {
        if (class_exists($namespace)) {
            return self::addByClass($namespace, $realpath, $domain);
        }

        if (interface_exists($namespace)) {
            return self::addByInterface($namespace, null, null);
        }

        exception('ClassOrInterfaceNotExists', ['namespace' => $namespace]);
    }

    /**
     * Add one class information to container by the namespace of interface
     */
    public static function addByInterface(string $namespace, string $realpath = null, string $domain = null)
    {
        if (! interface_exists($namespace)) {
            exception('InterfaceAddingToContainerNotFound', ['interface' => $namespace]);
        }

        list($reflection, , ) = Annotation::parseNamespace($namespace);
        $implementor = $reflection['doc']['IMPLEMENTOR'] ?? false;
        if ((! $implementor) || (! class_exists($implementor))) {
            exception('ImplementorNotExists', ['implementor' => $implementor]);
        }

        $class = self::$classes[$implementor] ?? false;
        if ($class) {
            return $class;
        }

        $_implementor = self::addByClass($implementor);

        self::$interfaces[$namespace] = $implementor;

        return $_implementor;
    }

    /**
     * Add one class information to container by the namespace of class
     */
    public static function addByClass(string $namespace, string $realpath = null, string $domain = null)
    {
        if (! class_exists($namespace)) {
            exception('ClassAddingToContainerNotFound', ['class' => $namespace]);
        }

        $realpath = $realpath ?: get_file_of_namespace($namespace);
        if (! $realpath) {
            exception('ClassFileNotFound', ['class' => $namespace]);
        }
        $domain = $domain ?: DomainManager::getByFile($realpath);
        if (! $domain) {
            exception('DomainNotFound', ['filepath' => $realpath]);
        }

        self::$filenskv[$realpath] = $namespace;
        self::$classes[$namespace] = $class = [
            'filepath'    => $realpath,
            'domain'      => $domain,
            'namespace'   => $namespace,
            'constructor' => Reflector::getClassConstructor($namespace),
        ];

        return $class;
    }

    public static function getClass($ns)
    {
        return self::$classes[$ns] ?? null;
    }

    public static function getFilenskv()
    {
        return self::$filenskv;
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
