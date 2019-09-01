<?php

declare(strict_types=1);

namespace Dof\Framework;

use Closure;
use ReflectionClass;
use ReflectionException;

class Annotation
{
    private $regex = '#@([a-zA-z\d]+)\((.*)\)(\{(.*)\})?#';

    /**
     * Parse class files or interface files annotations by directory paths
     *
     * @param array $dirs: a list of php class or interface directory paths
     * @param Closure $callback: The callback when parse given file finished
     * @return array: A list of a annotations of class/interface, properties and methods of the whole directories
     */
    public function parseClassDirs(array $dirs, Closure $callback = null, $origin = null) : array
    {
        $result = [];

        foreach ($dirs as $dir) {
            if ((! is_string($dir)) || (! is_dir($dir))) {
                exception('InvalidAnnotationDir', ['dir' => stringify($dir)]);
            }

            $result[$dir] = $this->parseClassDir($dir, $callback, $origin);
        }

        return $result;
    }

    /**
     * Parse class files or interface files annotations by directory path
     *
     * @param string $dir: the php class or interface directory path
     * @param Closure $callback: The callback when parse given file finished
     * @return array: A list of a annotations of class/interface, properties and methods of the whole directory
     */
    public function parseClassDir(string $dir, Closure $callback = null, $origin = null) : array
    {
        $result = [];

        walk_dir($dir, function ($path) use ($callback, $origin, &$result) {
            $realpath = $path->getRealpath();
            if ($path->isFile() && ('php' === $path->getExtension())) {
                $result[$realpath] = $this->parseClassFile($realpath, $callback, $origin);
                return;
            }
            if ($path->isDir()) {
                $this->parseClassDir($realpath, $callback, $origin);
                return;
            }
        });

        return $result;
    }

    /**
     * Parse class file or interface file annotations by filepath
     *
     * @param string $path: the php class or interface file path
     * @param Closure $callback: The callback when parse given file finished
     * @return array: A list of a annotations of class/interface, properties and methods
     */
    public function parseClassFile(string $path, Closure $callback = null, $origin = null)
    {
        $ns = get_namespace_of_file($path, true);
        if (! $ns) {
            exception('InvalidAnnotationNamespace', [
                'filepath'  => $path,
                'namespace' => $ns,
            ]);
        }

        $annotations = $this->parseNamespace($ns, $origin);

        if ($callback) {
            $callback($annotations);
        }

        return $annotations;
    }

    /**
     * Parse class or interface annotations by namespace
     *
     * @param string $namespace
     * @param mixed:object/string $origin: Object/Class using annotation parsing
     * @return array: A list of annotations of class/interface, properties and methods
     */
    public function parseNamespace(string $namespace, $origin = null) : array
    {
        try {
            $reflector = new ReflectionClass($namespace);
        } catch (ReflectionException $e) {
            return [];
        }

        $classDocComment = $reflector->getDocComment();
        $ofClass = [];
        if (false !== $classDocComment) {
            $ofClass['doc'] = $this->parseComment($classDocComment, $origin, $namespace);
        }
        $ofClass['namespace'] = $namespace;
        $ofProperties = $this->parseProperties($reflector->getProperties(), $namespace, $origin);
        $ofMethods    = $this->parseMethods($reflector->getMethods(), $namespace, $origin);

        return [
            $ofClass,
            $ofProperties,
            $ofMethods,
        ];
    }

    public function parseProperties(array $properties, string $namespace, $origin = null) : array
    {
        if (! $properties) {
            return [];
        }

        $res = [];
        foreach ($properties as $property) {
            $_res = Reflector::formatClassProperty($property, $namespace);
            $_res['doc'] = $this->parseComment((string) ($_res['doc'] ?? ''), $origin, $namespace);

            $res[$property->name] = $_res;
        }

        return $res;
    }

    public function parseMethods(array $methods, string $namespace, $origin = null) : array
    {
        if (! $methods) {
            return [];
        }

        $res = [];
        foreach ($methods as $method) {
            $_res = Reflector::formatClassMethod($method, $namespace);
            $_res['doc'] = $this->parseComment((string) ($_res['doc'] ?? ''), $origin, $namespace);
            $res[$method->name] = $_res;
        }

        return $res;
    }

    public function parseComment(string $comment, $origin = null, string $namespace = null) : array
    {
        if (! $comment) {
            return [];
        }

        $res = [];
        $arr = array_trim_from_string($comment, PHP_EOL);
        foreach ($arr as $line) {
            $matches = [];
            if (1 !== preg_match($this->regex, $line, $matches)) {
                continue;
            }
            $key = $matches[1] ?? false;
            $val = $matches[2] ?? null;
            $ext = $matches[4] ?? null;
            if ((! $key) || (is_null($val))) {
                continue;
            }
            $_ext = [];
            if ($ext) {
                parse_str($ext, $_ext);
            }
            $valueMultiple = false;
            $suffix = ucfirst(strtolower($key));
            if (! is_null($origin)) {
                $filterCallback = '__annotationFilter'.$suffix;
                if (method_exists($origin, $filterCallback)) {
                    $val = call_user_func_array([$origin, $filterCallback], [$val, $_ext, $namespace]);
                }
                $parameterCallback = '__annotationParameterFilter'.$suffix;
                if (method_exists($origin, $parameterCallback)) {
                    $_ext = call_user_func_array([$origin, $parameterCallback], [$_ext, $namespace]);
                }
                $multipleCallback = '__annotationMultiple'.$suffix;
                $valueMultiple = (
                    true
                    && method_exists($origin, $multipleCallback)
                    && call_user_func_array([$origin, $multipleCallback], [$namespace])
                );
            }

            $key = strtoupper($key);
            if ($valueMultiple) {
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if (is_string($v)) {
                            $res['__ext__'][$key][$v] = $_ext;    // The only lowercase key in annotation
                        }
                    }
                } elseif (is_string($val)) {
                    $res['__ext__'][$key][$val] = $_ext;
                }

                $_val = $res[$key] ?? [];
                $multipleFormatMergeCallback = '__annotationMultipleMerge'.$suffix;
                if (true
                    && $origin
                    && is_array($val)
                    && method_exists($origin, $multipleFormatMergeCallback)
                    && ($merge = call_user_func_array([$origin, $multipleFormatMergeCallback], [$namespace]))
                ) {
                    if ($merge === 'kv') {
                        // !!! array_flip() can only flip STRING and INTEGER values
                        // !!! So annotation filter method MUST NOT return as array
                        $val = array_flip($val);
                    }

                    if ($_val) {
                        // avoid merge([], ['k1' => 'v1']) resulted unexpected array
                        $val = ($merge === 'index') ? array_merge($_val, $val) : $val + $_val;
                    }
                } else {
                    $_val[] = $val;
                    $val = $_val;
                }
            } else {
                $res['__ext__'][$key] = $_ext;
            }

            $res[$key] = $val;
        }

        return $res;
    }

    /**
     * Setter for regex
     *
     * @param string $regex
     * @return Annotation
     */
    public function setRegex(string $regex)
    {
        $this->regex = $regex;
    
        return $this;
    }
}
