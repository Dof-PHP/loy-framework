<?php

declare(strict_types=1);

if (! function_exists('bomb_class')) {
    function bomb_object()
    {
        return new class {
            public function __get($key)
            {
                exit;
            }
            public function __call($method, $argvs)
            {
                exit;
            }
        };
    }
}
if (! function_exists('zombie_object')) {
    function zombie_object()
    {
        return new class {
            public function __get(string $key)
            {
                return $this;
            }
            public function __call(string $method, array $argvs = [])
            {
                return $this;
            }
        };
    }
}
if (! function_exists('pt')) {
    function pt(...$vars)
    {
        try {
            throw new \Exception;
        } catch (\Exception $e) {
            $last = $e->getTrace()[1] ?? false;
            extract($last);
            if ($last) {
                print_r([
                    sprintf('%s#%s:%s', $file, $line, $function),
                    $args,
                ]);
            }
        }

        return bomb_object();
    }
}
if (! function_exists('pd')) {
    function pd(...$vars)
    {
        pt(...$vars)->die();
    }
}
if (! function_exists('et')) {
    function et(...$vars)
    {
        foreach ($vars as $var) {
            $next = next($vars);
            if (is_scalar($var)) {
                echo $var;
                if ($next) {
                    echo is_scalar($next) ? ' => ' : PHP_EOL;
                }
            } else {
                print_r($var);
            }
        }

        echo PHP_EOL;

        return bomb_object();
    }
}
if (! function_exists('ee')) {
    function ee(...$vars)
    {
        et(...$vars)->die();
    }
}
if (! function_exists('pp')) {
    function pp(...$vars)
    {
        try {
            throw new \Exception;
        } catch (\Exception $e) {
            $last = $e->getTrace()[1] ?? false;
            extract($last);
            if ($last) {
                var_dump([
                    sprintf('%s#%s:%s', $file, $line, $function),
                    $args,
                ]);
            }
        }

        return bomb_object();
    }
}
if (! function_exists('dd')) {
    function dd(...$vars)
    {
        pp(...$vars)->die();
    }
}
if (! function_exists('load_php')) {
    function load_php(string $path) : array
    {
        if (! is_file($path)) {
            return [];
        }

        $ret = include $path;

        return (array) $ret;
    }
}
if (! function_exists('list_dir')) {
    function list_dir(string $dir, \Closure $callback)
    {
        if (! is_dir($dir)) {
            exception('ListDirNotExists', ['dir' => $dir]);
        }

        $list = (array) scandir($dir);

        $callback($list, $dir);
    }
}
if (! function_exists('walk_dir_recursive')) {
    function walk_dir_recursive(string $dir, \Closure $callback)
    {
        walk_dir($dir, function ($path) use ($callback) {
            if (in_array($path->getFileName(), ['.', '..'])) {
                return;
            }
            if ($path->isDir()) {
                walk_dir_recursive($path->getRealpath(), $callback);
                return;
            }

            $callback($path);
        });
    }
}
if (! function_exists('walk_dir')) {
    function walk_dir(string $dir, \Closure $callback)
    {
        if (! is_dir($dir)) {
            exception('WalkDirNotExists', ['dir' => $dir]);
        }

        $fsi = new \FilesystemIterator($dir);
        foreach ($fsi as $path) {
            $callback($path);
        }

        unset($fsi);
    }
}
if (! function_exists('get_file_of_namespace')) {
    function get_file_of_namespace(string $ns)
    {
        if (! class_exists($ns)) {
            return false;
        }

        return (new ReflectionClass($ns))->getFileName();
    }
}
if (! function_exists('get_namespace_of_file')) {
    function get_namespace_of_file(string $path, bool $withClass = false)
    {
        if (! is_file($path)) {
            return false;
        }

        $tokens = token_get_all(php_strip_whitespace($path));
        $cnt = count($tokens);
        $ns  = $cn = '';
        $nsIdx = $cnIdx = 0;
        $findingNS = $findingCN = true;
        for ($i = 0; $i < $cnt; ++$i) {
            $token = $tokens[$i] ?? false;
            $tname = $token[0] ?? false;
            if ($findingNS && ($tname === T_NAMESPACE)) {
                $nsIdx = $i;
                $findingNS = false;
                continue;
            }
            if ($findingCN && in_array($tname, [T_CLASS, T_INTERFACE, T_TRAIT])) {
                $cnIdx = $i;
                $findingCN = false;
                continue;
            }
        }
        if ($findingNS === false) {
            for ($j = $nsIdx; $j < $cnt; ++$j) {
                $token = $tokens[$j + 1] ?? false;
                if ($token === ';') {
                    break;
                }
                $ns .= ($token[1] ?? '');
            }
        }
        $ns = trim($ns);
        if (! $ns) {
            return false;
        }
        if (! $withClass) {
            return $ns ?: '\\';
        }
        $cnLine = [];
        if ($findingCN === false) {
            for ($k = $cnIdx; $k < $cnt; ++$k) {
                $token = $tokens[$k + 1] ?? false;
                if ($token === '{') {
                    break;
                }
                $cnLine[] = ($token[1] ?? '');
            }
        }
        $cnLine = array_values(array_filter($cnLine, function ($item) {
            return ! empty(trim($item));
        }));
        $cn = $cnLine[0] ?? '';
        if (! $cn) {
            return false;
        }
        $cn = join('\\', [$ns, $cn]);

        return (class_exists($cn) || interface_exists($cn)) ? $cn : false;
    }
}
if (! function_exists('get_used_classes')) {
    /**
     * Get classes used by class or interface
     *
     * @param string $target
     * @param bool $namespace: if target is a namespace
     * @return array|null
     */
    function get_used_classes(string $target, bool $namespace = true) : ?array
    {
        if ($namespace && (! ($target = get_file_of_namespace($target)))) {
            return null;
        }
        if (! is_file($target)) {
            return null;
        }

        $tokens = token_get_all(php_strip_whitespace($target));
        $usedClasses = [];
        $foundNamespace = false;
        $findingUsedClass = false;
        $findingAlias = false;
        $usedClass = [];
        foreach ($tokens as $token) {
            $tokenId = $token[0] ?? false;
            $hasNamespace = $tokenId === T_NAMESPACE;
            if ((! $foundNamespace) && (! $hasNamespace)) {
                continue;
            } else {
                $foundNamespace = true;
            }

            $foundClassname = $tokenId === T_CLASS;
            if ($foundClassname) {
                break;
            }
            if ($tokenId === T_USE) {
                $findingUsedClass = true;
                $findingAlias = false;
                continue;
            }
            if ($findingUsedClass) {
                if ($token === ';') {
                    $findingUsedClass = false;
                    if ($usedClass) {
                        $ns = join('', $usedClass['nspath']);
                        $alias = $usedClass['alias'] ?? null;
                        $usedClasses[$ns] = $alias;
                        $usedClass = [];
                    }
                    continue;
                }
                if (($tokenId !== T_WHITESPACE) && ($tokenName = ($token[1] ?? false))) {
                    if ($tokenId === T_AS) {
                        $findingAlias = true;
                        continue;
                    }

                    if ($findingAlias) {
                        $usedClass['alias'] = $tokenName;
                    } else {
                        $usedClass['nspath'][] = $tokenName;
                    }
                }
            }
        }

        return $usedClasses;
    }
}
if (! function_exists('enxml')) {
    function enxml($data)
    {
        $xml = $_xml = '';
        $arr2xml = function (
            array $array,
            string &$xml,
            callable $arr2xml
        ): string {
            foreach ($array as $key => &$val) {
                if (is_array($val)) {
                    $_xml = '';
                    $val  = $arr2xml($val, $_xml, $arr2xml);
                }
                $xml .= "<{$key}>{$val}</{$key}>";
            }
            unset($val);
            return $xml;
        };

        if (true
            && is_object($data)
            && (
                false
            || (method_exists($data, '__toArray') && is_array($ret = $data->__toArray()))
            || (method_exists($data, 'toArray') && is_array($ret = $data->toArray()))
            )
        ) {
            $_xml = $arr2xml($ret, $xml, $arr2xml);
        } elseif (is_array($data)) {
            $_xml = $arr2xml($data, $xml, $arr2xml);
        }
        unset($xml);

        return '<?xml version="1.0" encoding="utf-8"?><xml>'.$_xml.'</xml>';
    }
}
if (! function_exists('dexml')) {
    function dexml(string $xml, bool $loaded = false, bool $file = false) : array
    {
        if ($file) {
            if (is_file($xml)) {
                $xml = file_get_contents($xml);
                $loaded = false;
            }

            exception('XmlFileNotExists', ['file' => $xml]);
        }

        if (! extension_loaded('libxml')) {
            exception('MissingPHPExtension', ['extension' => 'libxml']);
        }
        
        libxml_use_internal_errors(true);
        $xml = $loaded
        ? $xml
        : simplexml_load_string(
            $xml,
            'SimpleXMLElement',
            LIBXML_NOCDATA
        );
        if (($error = libxml_get_last_error()) && isset($error->message)) {
            libxml_clear_errors();
            // exception('IllegalXMLformat', ['xml' => $xml, 'error' => $error->message]);
            return [];
        }

        return dejson(enjson($xml), true);
    }
}
if (! function_exists('enjson')) {
    function enjson($data)
    {
        if (is_object($data)) {
            if (method_exists($data, '__toArray')) {
                $data = $data->__toArray();
            } elseif (method_exists($data, 'toArray')) {
                $data = $data->toArray();
            }

            if (! is_array($data)) {
                return '';
            }
        }

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json ?: '';
    }
}
if (! function_exists('dejson')) {
    function dejson(string $json, bool $assoc = true, bool $file = false)
    {
        if ($file) {
            if (is_file($json)) {
                $json = file_get_contents($json);
            }

            exception('JsonFileNotExists', ['file' => $json]);
        }

        $res = json_decode($json, $assoc);

        return (is_array($res) || is_object($res)) ? $res : [];
    }
}
if (! function_exists('subsets')) {
    // See: <https://stackoverflow.com/questions/6092781/finding-the-subsets-of-an-array-in-php>
    function subsets(array $data, int $minLen = 1) : array
    {
        $count   = count($data);
        $times   = pow(2, $count);
        $result  = [];
        for ($i = 0; $i < $times; ++$i) {
            // $bin = sprintf('%0'.$count.'b', $i);
            $tmp = [];
            for ($j = 0; $j < $count; ++$j) {
                // Use bitwise operation is more faster than sprintf
                if ($i >> $j & 1) {
                    // if ('1' == $bin{$j}) {    // get NO.$j letter in string $bin
                    $tmp[$j] = $data[$j];
                }
            }
            if (count($tmp) >= $minLen) {
                $result[] = $tmp;
            }
        }
        return $result;
    }
}
if (! function_exists('array_trim')) {
    function array_trim(array $arr, bool $preserveKeys = false) : array
    {
        array_filter($arr, function (&$val, $key) use (&$arr) {
            if (! is_scalar($val)) {
                return true;
            }
            $val = trim($val);
            if (empty($val)) {
                if (is_numeric($val)) {
                    return true;
                }

                unset($arr[$key]);
                return false;
            }

            $arr[$key] = $val;
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        return $preserveKeys ? $arr : array_values($arr);
    }
}
if (! function_exists('array_trim_from_string')) {
    function array_trim_from_string(string $str, string $explode = ',')
    {
        $str = trim($str);
        $arr = explode($explode, $str);

        return array_trim($arr);
    }
}
if (! function_exists('stringify')) {
    function stringify($value)
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return enjson($value);
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $res = $value->__toString();
                if (is_scalar($res)) {
                    return (string) $res;
                }
            }
            if (method_exists($value, '__toArray')) {
                $res = $value->__toArray();
                if (is_array($res)) {
                    return ensjson($res);
                }
            }

            return get_class($value);
        }

        return '__UNSTRINGABLE_VALUE__';
    }
}
if (! function_exists('string_literal')) {
    function string_literal($val)
    {
        if (is_null($val)) {
            return 'null';
        }
        if (is_bool($val)) {
            return ($val ? 'true' : 'false');
        }
        if (is_scalar($val)) {
            return (string) $val;
        }
        if (is_array($val)) {
            return enjson($val);
        }
        if ($val instanceof \Closure) {
            return 'closure';
        }
        if (is_object($val)) {
            return get_class($val);
        }

        return '__UNKNOWN_VARIABLE_TYPE__';
    }
}
if (! function_exists('is_xml')) {
    function is_xml(string $xml)
    {
        libxml_use_internal_errors(true);
        if (! ($doc = simplexml_load_string(
            $xml,
            'SimpleXMLElement',
            LIBXML_NOCDATA
        ))) {
            $error = libxml_get_last_error();    // LibXMLError object
            libxml_clear_errors();
            if ($error !== false) {
                return 'Illegal XML: '.$error->message;
            }
        }

        return true;
    }
}
if (! function_exists('ci_equal')) {
    function ci_equal(string $a, string $b) : bool
    {
        return strtolower($b) === strtolower($b);
    }
}
if (! function_exists('objectname')) {
    function objectname($object) : ?string
    {
        if (is_object($object)) {
            return (new \ReflectionClass($object))->getShortName();
        }
    }
}
if (! function_exists('ospath')) {
    function ospath(...$items) : string
    {
        $path = '';
        $ds   = DIRECTORY_SEPARATOR;
        foreach ($items as $item) {
            if (is_scalar($item)) {
                $item = (string) $item;
                $path = (0 === mb_strpos($item, $ds)) ? $path.$item : join($ds, [$path, $item]);
                continue;
            }
            if (is_array($item)) {
                $path .= ospath(...$item);
                continue;
            }
        }

        return $path;
    }
}
if (! function_exists('fdate')) {
    function fdate(int $ts = null, string $format = null) : string
    {
        $format = $format ?: 'Y-m-d H:i:s';
        $ts = $ts ?: time();

        return date($format, $ts);
    }
}
if (! function_exists('microftime')) {
    function microftime(string $format = 'Y-m-d H:i:s', string $separate = '.') : string
    {
        $mts = explode('.', (string) microtime(true));

        return join($separate, [date($format, (int) $mts[0]), $mts[1]]);
    }
}
if (! function_exists('timezone')) {
    /**
     * Get timezone abbreviation
     */
    function timezone(string $tz = null) : string
    {
        $tz = $tz ?: date_default_timezone_get();
        $dt = (new \Datetime())->setTimeZone((new \DateTimeZone($tz)));

        return $dt->format('T');
    }
}
if (! function_exists('is_date_format')) {
    function is_date_format(string $date, string $format = 'Y-m-d H:i:s') : bool
    {
        $dt = DateTime::createFromFormat($format, $date);

        return $dt && ($dt->format($format) == $date);
    }
}
if (! function_exists('is_closure')) {
    function is_closure($val) : bool
    {
        return is_object($val) && ($val instanceof \Closure);
    }
}
if (! function_exists('array_get_by_chain_key')) {
    function array_get_by_chain_key($haystack, string $key = null, string $explode = '.')
    {
        if ((! $haystack) || (! $key) || ((! is_array($haystack)) && (! is_object($haystack)))) {
            return null;
        }
        if (is_object($haystack)) {
            if (method_exists($haystack, 'toArray')) {
                $haystack->toArray();
            } elseif (method_exists($haystack, '__toArray')) {
            } else {
                return null;
            }
        }
        if (array_key_exists($key, $haystack)) {
            return $haystack[$key] ?? null;
        }
        $chain  = array_trim_from_string($key, $explode);
        $query  = null;
        $tmparr = $haystack;
        foreach ($chain as $k) {
            $query = ($tmparr = ($tmparr[$k] ?? null));
        }

        return $query;
    }
}
if (! function_exists('is_throwable')) {
    function is_throwable($throwable) : bool
    {
        return is_object($throwable) && ($throwable instanceof \Throwable);
    }
}
if (! function_exists('is_anonymous')) {
    function is_anonymous($instance) : bool
    {
        return is_object($instance) && (new ReflectionClass($instance))->isAnonymous();
    }
}
if (! function_exists('parse_throwable')) {
    /**
     * Parse throwable and get context as previous
     *
     * @param \Throwable $throwable
     * @param array|null $context: Context saver
     */
    function parse_throwable(?\Throwable $throwable, ?array $context = []) : ?array
    {
        if (! is_throwable($throwable)) {
            return $context;
        }

        $_context = $throwable->context ?? [];
        $name = objectname($throwable);
        $file = $throwable->getFile();
        $line = $throwable->getLine();
        if (is_anonymous($throwable)) {
            $previous = $throwable->getTrace()[0] ?? [];
            $file = $previous['file'] ?? null;
            $line = $previous['line'] ?? null;
            $name = $throwable->getMessage();
        }
        $_context['__name'] = $name;
        $_context['__info'] = $throwable->getMessage();
        $_context['__file'] = $file;
        $_context['__line'] = $line;

        if (is_null($context)) {
            return $_context;
        }

        $context['__trace']    = explode(PHP_EOL, $throwable->getTraceAsString());
        $context['__previous'] = $_context;

        return $context;
    }
}
if (! function_exists('exception')) {
    function exception(
        string $name,
        array $context = [],
        Throwable $previous = null
    ) {
        throw new class($name, $context, $previous) extends \Exception {
            public $context = [];
            public function __construct(
                string $name,
                array $context = [],
                Throwable $previous = null
            ) {
                $this->message = $name;
                $this->context = parse_throwable($previous, $context);
            }
        };
    }
}
if (! function_exists('getallheaders')) {
    // For nginx, compatible with apache format
    function getallheaders() : array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (mb_substr($name, 0, 5) === 'HTTP_') {
                $headers[mb_substr($name, 5)] = $value;
            }
        }
        return $headers;
    }
}
if (! function_exists('is_function_disabled')) {
    function is_function_disabled(string $name)
    {
        return in_array($name, explode(',', ini_get('disable_functions')));
    }
}
if (! function_exists('chownr')) {
    function chownr($path, string $owner)
    {
        if (is_function_disabled('chown') || is_function_disabled('lchown')) {
            return;
        }

        if (is_link($path) && is_writable($path)) {
            lchown($path, $owner);
        }
        if (is_file($path) && is_writable($path)) {
            chown($path, $owner);
            return;
        }
        if (! is_dir($path)) {
            return;
        }

        list_dir($path, function ($list) use ($path, $owner) {
            foreach ($list as $file) {
                if ($file === '.' || '..' === $file) {
                    continue;
                }
                $_path = ospath($path, $file);
                if (is_dir($_path)) {
                    chownr($_path, $owner);
                } elseif (is_link($_path) && is_writable($path)) {
                    lchown($_path, $owner);
                } elseif (is_file($_path) && is_writable($path)) {
                    chown($_path, $owner);
                }
            }
        });
    }
}
if (! function_exists('chgrpr')) {
    function chgrpr($path, string $group)
    {
        if (is_function_disabled('lchgrp') || is_function_disabled('chgrp')) {
            return;
        }

        if (is_link($path) && is_writable($path)) {
            lchgrp($path, $mode);
            return;
        }
        if (is_file($path) && is_writable($path)) {
            chgrp($path, $group);
            return;
        }
        if (! is_dir($path)) {
            return;
        }

        list_dir($path, function ($list) use ($path, $group) {
            foreach ($list as $file) {
                if ($file === '.' || '..' === $file) {
                    continue;
                }
                $_path = ospath($path, $file);
                if (is_dir($_path)) {
                    chgrpr($_path, $group);
                } elseif (is_link($_path) && is_writable($_path)) {
                    lchgrp($_path, $group);
                } elseif (is_file($_path) && is_writable($_path)) {
                    chown($_path, $group);
                }
            }
        });
    }
}
if (! function_exists('chmodr')) {
    function chmodr($path, int $mode)
    {
        if (is_function_disabled('chmod')) {
            return;
        }

        if (is_file($path) && is_writable($path)) {
            chmod($path, $mode);
            return;
        }
        if (! is_dir($path)) {
            return;
        }

        list_dir($path, function ($list) use ($path, $mode) {
            foreach ($list as $file) {
                if ($file === '.' || '..' === $file) {
                    continue;
                }
                $_path = ospath($path, $file);
                if (is_dir($_path)) {
                    chmodr($_path, $mode);
                } elseif (is_file($_path) && is_writable($_path)) {
                    chmod($_path, $mode);
                }
            }
        });
    }
}
if (! function_exists('instance_of')) {
    function instance_of(string $child = null, string $parent = null) : bool
    {
        if ((! $child) || (! $parent)) {
            return false;
        }

        $childIsClass      = class_exists($child);
        $childIsInterface  = interface_exists($child);
        $parentIsClass     = class_exists($parent);
        $parentIsInterface = interface_exists($parent);
        if ((! $childIsClass) && (! $childIsInterface)) {
            return false;
        }
        if ((! $parentIsClass) && (! $parentIsInterface)) {
            return false;
        }

        if ($childIsClass) {
            if ($parentIsClass) {
                return is_subclass_of($child, $parent);
            }
            if ($parentIsInterface) {
                return is_subinterface_of($child, $parent);
            }
        }
        if ($childIsInterface) {
            if ($parentIsClass) {
                return false;
            }
            return is_subinterface_of($child, $parent);
        }

        return false;
    }
}
if (! function_exists('is_subinterface_of')) {
    function is_subinterface_of(string $child, string $parent) : bool
    {
        if (! interface_exists($child)) {
            return false;
        }
        if (! interface_exists($parent)) {
            return false;
        }

        $reflector = new \ReflectionClass($child);

        return $reflector->implementsInterface($parent);
    }
}
if (! function_exists('get_php_user')) {
    function get_php_user() : string
    {
        $user = get_current_user();
        if ($user) {
            return $user;
        }
        if (! extension_loaded('posix')) {
            return 'nobody';
        }

        return posix_getpwuid(posix_geteuid())['name'] ?? 'nobody';
    }
}
