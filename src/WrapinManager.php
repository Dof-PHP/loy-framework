<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Facade\Annotation;
use Dof\Framework\Facade\Request;
use Dof\Framework\Facade\Validator;

final class WrapinManager
{
    const WRAPIN_DIR = ['Http', 'Wrapper', 'In'];
    const RESERVE_KEYS = [
        'COMPATIBLE' => 1,
        'LOCATION' => 1,
        'INROUTE' => 1,
        // 'WRAPIN'  => 1,
        '__ext__' => 1,
        'TITLE' => 1,
        'NOTES' => 1,
    ];

    private static $dirs = [];
    private static $wrapins = [];

    public static function load(array $dirs)
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$wrapins) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_WRAPIN_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([self::$dirs, self::$wrapins], $cache);
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (is_file($cache)) {
            unlink($cache);
        }
    }

    public static function compile(array $dirs, bool $cache = false)
    {
        // Reset
        self::$dirs = [];
        self::$wrapins = [];

        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = ospath($item, self::WRAPIN_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Exceptions may thrown but let invoker to catch for different scenarios
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, $ofProperties, ) = $annotations;
                self::assemble($ofClass, $ofProperties);
            }
        }, __CLASS__);

        if ($cache) {
            array2code([self::$dirs, self::$wrapins], Kernel::formatCompileFile(__CLASS__));
        }
    }

    public static function assemble(array $ofClass, array $ofProperties)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if ($exists = (self::$wrapins[$namespace] ?? false)) {
            exception('DuplicateWrapinNamespace', ['namespace' => $namespace]);
        }
        if (! ($ofClass['doc']['TITLE'] ?? false)) {
            exception('MissingWrapinTitle', ['wrapin' => $namespace]);
        }

        self::$wrapins[$namespace]['meta'] = $ofClass['doc'] ?? [];
        foreach ($ofProperties as $name => $attrs) {
            if (! ($attrs['doc']['TITLE'] ?? false)) {
                exception('MissingWrapinAttrTitle', ['wrapin' => $namespace, 'attr' => $name]);
            }
            if (! ($attrs['doc']['TYPE'] ?? false)) {
                exception('MissingWrapinAttrType', ['wrapin' => $namespace, 'attr' => $name]);
            }

            self::$wrapins[$namespace]['properties'][$name] = $attrs['doc'] ?? [];
        }
    }

    /**
     * Apply a wrapin on request parameters
     *
     * @param string $wrapin: Wrapin class to apply
     * @param iterable|null $params: The origin data to be validated
     * @return Dof\Framework\Validator
     */
    public static function apply(string $wrapin, $params = null)
    {
        if (! class_exists($wrapin)) {
            exception('WrapperInNotExists', compact('wrapin'));
        }

        $wrapins = self::get($wrapin);

        return self::execute($wrapins, $wrapin, $params);
    }

    /**
     * Execute a wrapin check by given validator arguments and origin
     *
     * @param iterable $arguments: Validate rules list
     * @param string $origin: The origin class request a wrapin check
     * @param iterable|null $params: The origin data to be validated
     * @return Dof\Framework\Validator
     */
    public static function execute($arguments, string $origin, $params = null)
    {
        if (is_collection($arguments)) {
            $arguments = uncollect($arguments);
        } elseif (is_array($arguments)) {
        } else {
            exception('UnIterableWrapinArguments', compact('arguments'));
        }

        $data = $rules = $wrapins = [];
        foreach ($arguments as $key => $argument) {
            $argument = $argument['doc'] ?? [];
            if (! $argument) {
                continue;
            }

            $_key = null;    // The field name first find a non-null value in key list
            $keys = array_keys(($argument['COMPATIBLE'] ?? []));
            array_unshift($keys, $key);
            $val  = self::match($keys, $_key, $params);
            if (! is_null($val)) {
                $data[$key] = $val;
            }

            $ext = $argument['__ext__'] ?? [];
            unset($argument['__ext__']);
            foreach ($argument as $annotation => $value) {
                $_annotation = array_trim_from_string($annotation, ':');
                $annotation = $_annotation[0] ?? $annotation;
                unset($_annotation[0]);
                $value = $_annotation ? join(':', $_annotation) : $value;
                if (self::RESERVE_KEYS[$annotation] ?? false) {
                    continue;
                }
                if ($annotation === 'WRAPIN') {
                    if ($ext['WRAPIN']['list'] ?? false) {
                        if (is_iterable($val)) {
                            foreach ($val as $item) {
                                $wrapins[] = [$value => $item];
                            }
                        }
                    } else {
                        $wrapins[] = [$value => $val];
                    }
                    continue;
                }

                $rule = $annotation;
                if ($annotation === 'DEFAULT') {
                    $rules[$key][] = sprintf('%s:%s', $annotation, $value);
                    continue;
                }
                if ($annotation === 'VALIDATOR') {
                    $rule = $value;
                    $value = $ext[$annotation]['ext'] ?? null;
                }

                $errmsg = $ext[$annotation]['err'] ?? (array_keys($ext[$annotation] ?? [])[0] ?? null);
                if (is_null($errmsg)) {
                    $rules[$key][] = sprintf('%s:%s', $rule, $value);
                } else {
                    $errmsg = sprintf($errmsg, (($argument['TITLE'] ?? $_key) ?? ''), $value);
                    $rules[$key][sprintf('%s:%s', $rule, $value)] = $errmsg;
                }
            }
        }

        $validator = Validator::setData($data)->setRules($rules)->execute();
        if ($validator->getFails()) {
            return $validator;
        }

        // Execute another wrappins from annotations
        if ($wrapins) {
            foreach ($wrapins as $__wrapin) {
                foreach ($__wrapin as $wrapin => $_data) {
                    if (! $_data) {
                        continue;
                    }

                    $_wrapin = get_annotation_ns($wrapin, $origin);
                    if (false === $_wrapin) {
                        exception('InvalidWrapinAnnotation', compact('wrapin'));
                    }

                    $validator = self::apply($_wrapin, $_data);
                    if (($fails = $validator->getFails()) && ($fail = $fails->first())) {
                        $fails   = $fails->toArray();
                        $context = $fail->value;
                        $context['wrapins'][] = $_wrapin;
                        $fails[$fail->key] = $context;

                        $validator->setFails(collect($fails, null, false));

                        return $validator;
                    }
                }
            }
        }

        return $validator;
    }

    public static function match(array $keys, string $_key = null, $data = null)
    {
        if (is_null($data)) {
            return Request::match($keys, $_key);
        }

        foreach ($keys as $key) {
            if (! is_scalar($key)) {
                continue;
            }
            $key = (string) $key;
            $val = $data[$key] ?? null;
            if (! is_null($val)) {
                $_key = $key;
                return $val;
            }
        }

        return null;
    }

    public static function __annotationFilterWrapin($wrapin, array $ext, $namespace)
    {
        $wrapin = trim($wrapin);
        if (! $wrapin) {
            exception('MissingWrapInNamespace', compact('namespace'));
        }
        if (class_exists($wrapin)) {
            return $wrapin;
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingWrapInUseClass', compact('wrapin', 'namespace'));
        }

        $_wrapin = get_annotation_ns($wrapin, $namespace);
        if ((! $_wrapin) || (! class_exists($_wrapin))) {
            exception('WrapInNotExists', compact('wrapin', 'namespace'));
        }
        if ($_wrapin === $namespace) {
            exception('WrapInEqualsToUseClass', compact('wrapin', '_wrapin', 'namespace'));
        }

        return $_wrapin;
    }

    public static function __annotationMultipleCompatible() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeCompatible()
    {
        return 'kv';
    }

    public static function getWrapins()
    {
        return self::$wrapins;
    }

    public static function getDirs()
    {
        return self::$dirs;
    }

    public static function get(string $namespace)
    {
        return self::$wrapins[$namespace] ?? null;
    }

    public static function __annotationFilterCompatible(string $compatibles) : array
    {
        return array_trim_from_string($compatibles, ',');
    }
}
