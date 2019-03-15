<?php

declare(strict_types=1);

namespace Loy\Framework;

use Closure;
use ReflectionClass;
use ReflectionException;

class Annotation
{
    private $regex = '#@([a-zA-z]+)\((.*)\)#';

    public function parseClassDirs(array $dirs, Closure $callback = null, string $origin = null)
    {
        foreach ($dirs as $dir) {
            if ((! is_string($dir)) || (! is_dir($dir))) {
                exception('InvalidAnnotationDir', ['dir' => stringify($dir)]);
            }

            $this->parseClassDir($dir, $callback, $origin);
        }
    }

    public function parseClassDir(string $dir, Closure $callback = null, string $origin = null)
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

    public function parseClassFile(string $path, Closure $callback = null, string $origin = null)
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

    public function parseNamespace(string $namespace, string $origin = null) : array
    {
        try {
            $reflector = new ReflectionClass($namespace);
        } catch (ReflectionException $e) {
            return [];
        }

        $classDocComment = $reflector->getDocComment();
        $ofClass = [];
        if (false !== $classDocComment) {
            $ofClass['doc'] = $this->parseComment($classDocComment, $origin);
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

    public function parseProperties(array $properties, string $namespace, string $origin = null) : array
    {
        if (! $properties) {
            return [];
        }

        $res = [];
        foreach ($properties as $property) {
            list($type, $_res) = Reflector::formatClassProperty($property, $namespace);
            if ($_res === false) {
                continue;
            }
            $comment = (string) ($_res['doc'] ?? '');
            $_res['doc'] = $this->parseComment($comment, $origin);

            $res[$type][$property->name] = $_res;
        }

        return $res;
    }

    public function parseMethods(array $methods, string $namespace, string $origin = null) : array
    {
        if (! $methods) {
            return [];
        }

        $res = [];
        foreach ($methods as $method) {
            list($type, $_res) = Reflector::formatClassMethod($method, $namespace);
            if ($_res === false) {
                continue;
            }
            $comment = (string) ($_res['doc'] ?? '');
            $_res['doc'] = $this->parseComment($comment, $origin);
            $res[$type][$method->name] = $_res;
        }

        return $res;
    }

    public function parseComment(string $comment, string $origin = null) : array
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
            if ((! $key) || (is_null($val))) {
                continue;
            }
            $valueMultiple = false;
            if (! is_null($origin)) {
                $suffix = ucfirst(strtolower($key));
                $filterCallback = '__annotationFilter'.$suffix;
                if (method_exists($origin, $filterCallback)) {
                    $val = call_user_func_array([$origin, $filterCallback], [$val]);
                    // $val = is_object($origin) ? $origin->{$filterCallback}($val) : $origin::$filterCallback($val);
                }
                $multipleCallback = '__annotationMultiple'.$suffix;
                $valueMultiple = (
                    true
                    && method_exists($origin, $multipleCallback)
                    && call_user_func_array([$origin, $multipleCallback], [])
                );
            }

            $key = strtoupper($key);
            if ($valueMultiple) {
                $_val = $res[$key] ?? [];
                $val  = array_unique(array_merge($_val, $val));
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
