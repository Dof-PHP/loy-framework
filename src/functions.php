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
if (! function_exists('enjson')) {
    function enjson($data)
    {
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
if (! function_exists('collect')) {
    function collect(array $data, $origin = null)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = collect($value, $origin);
            }
        }

        return new \Loy\Framework\Core\Collection($data, $origin);
    }
}
