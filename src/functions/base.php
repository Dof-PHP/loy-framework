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
        foreach ($vars as $var) {
            print_r($var);
            echo PHP_EOL;
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
        var_dump(...$vars);

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
if (! function_exists('get_namespace_of_file')) {
    function get_namespace_of_file(string $path, bool $withClass = false)
    {
        if (! is_file($path)) {
            return false;
        }

        $tokens = token_get_all(file_get_contents($path));
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
            if ($findingCN && ($tname === T_CLASS)) {
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
        return class_exists($cn) ? $cn : false;
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
    function dexml(string $xml, bool $loaded = false) : array
    {
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
            // exception('Illegal XML format', ['error' => $error->message]);
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
    function dejson(string $json, bool $assoc = true)
    {
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
    function array_trim_from_string(string $str, string $explode)
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

        return 'unknown variable type';
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
    function array_get_by_chain_key(array $haystack, string $key, string $explode = '.')
    {
        if ((! $haystack) || (! $key)) {
            return null;
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
    function parse_throwable($throwable, array &$context = [])
    {
        if (is_throwable($throwable)) {
            $message = is_anonymous($throwable) ? $throwable->getMessage() : objectname($throwable);
            $context['__previous'] = $throwable->context;
        } elseif (is_scalar($throwable)) {
            $message = $throwable;
        } else {
            $message = string_literal($throwable);
        }

        return $message;
    }
}
if (! function_exists('exception')) {
    function exception($throwable, array $context = [])
    {
        throw new class($throwable, $context) extends \Exception {
            public $context = [];
            public function __construct($throwable, array $context = [])
            {
                $this->message = parse_throwable($throwable, $context);
                $this->context = $context;
            }
        };
    }
}
