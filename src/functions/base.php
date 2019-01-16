<?php

declare(strict_types=1);

if (! function_exists('pt')) {
    function pt(...$vars)
    {
        foreach ($vars as $var) {
            print_r($var);
            echo PHP_EOL;
        }

        return new class {
            public function die()
            {
                exit;
            }
        };
    }
} if (! function_exists('pp')) {
    function pp(...$vars)
    {
        var_dump(...$vars);
        return new class {
            public function die()
            {
                exit;
            }
        };
    }
}
if (! function_exists('dd')) {
    function dd(...$vars)
    {
        pp(...$vars);

        exit(0);
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
    function list_dir(string $dir, $callback)
    {
        if (! is_dir($dir)) {
            throw new \Exception('LIST_DIR_NOT_EXISTS');
        }

        $list = (array) scandir($dir);

        $callback($list, $dir);
    }
}
if (! function_exists('walk_dir')) {
    function walk_dir(string $dir, $callback)
    {
        if (! is_dir($dir)) {
            throw new \Exception('WALK_DIR_NOT_EXISTS');
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
        for ($j = $nsIdx; $j < $cnt; ++$j) {
            $token = $tokens[$j + 1] ?? false;
            if ($token === ';') {
                break;
            }
            $ns .= ($token[1] ?? '');
        }
        if (! $withClass) {
            return trim($ns);
        }

        $cnLine = [];
        for ($k = $cnIdx; $k < $cnt; ++$k) {
            $token = $tokens[$k + 1] ?? false;
            if ($token === '{') {
                break;
            }
            $cnLine[] = ($token[1] ?? '');
        }
        $cnLine = array_values(array_filter($cnLine, function ($item) {
            return ! empty(trim($item));
        }));
        $cn = $cnLine[0] ?? '';

        return join('\\', [trim($ns), $cn]);
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
    function array_trim(array $arr) : array
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

        return $arr;
    }
}
if (! function_exists('stringify')) {
    function stringify($value)
    {
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
