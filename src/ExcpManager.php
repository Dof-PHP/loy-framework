<?php

declare(strict_types=1);

namespace Dof\Framework;

final class ExcpManager
{
    const DOMAIN_EXCP = 'EXCP.php';
    const EXCP_CODE_REGEX = '#^\d{8}$#';

    private static $excps = [
        'default' => [],
        'domains' => [],
    ];

    private static $codes = [];

    public static function load(array $dirs)
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (file_exists($cache)) {
            self::$excps = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_EXCP_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code(self::$excps, $cache);
        }
    }

    public static function compile(array $dirs, bool $cache = false)
    {
        // Reset
        self::$excps = [
            'default' => [],
            'domains' => [],
        ];
        self::$codes = [];

        if (count($dirs) < 1) {
            return;
        }

        self::loadDefault();
        self::loadDomains($dirs);

        if ($cache) {
            array2code(self::$excps, Kernel::formatCompileFile(__CLASS__));
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (is_file($cache)) {
            unlink($cache);
        }
    }

    public static function loadDefault()
    {
        $class = EXCP::class;

        $default = get_class_consts($class);

        self::validate($default, $class);

        self::$excps['default'] = $default;
    }

    private static function validate(array $excps, string $class)
    {
        foreach ($excps as $const => $excp) {
            if (! $excp) {
                exception('MissingExcpData', compact('const', 'class', 'excp'));
            }
            if (! (is_index_array($excp))) {
                $err = 'NotAnIndexArray';
                exception('InvalidExcpData', compact('const', 'err', 'class'));
            }
            if (count($excp) < 2) {
                $err = 'ExcpDataLengthLowerThanTwo';
                exception('InvalidExcpData', compact('const', 'err', 'class'));
            }
            $code = $excp[0] ?? null;
            if (1 !== preg_match(self::EXCP_CODE_REGEX, strval($code))) {
                $err = 'NotAnEightLengthInteger';
                exception('InvalidExcpData', compact('const', 'err', 'class', 'code', 'name'));
            }
            if ($conflict = (self::$codes[$code] ?? false)) {
                $current = join('::', [$class, $const]);
                exception('ExcpCodeConflict', compact('code', 'conflict', 'current'));
            }

            $name = $excp[1] ?? null;
            if (! is_string($name)) {
                $err = 'NameIsNotAString';
                exception('InvalidExcpData', compact('const', 'err', 'class', 'code', 'name'));
            }

            $desc = $excp[2] ?? null;
            $info = $excp[3] ?? null;
            $status = $excp[4] ?? null;
            if ((! is_null($desc)) && (! is_string($desc))) {
                $err = 'DescriptionIsNotAString';
                exception('InvalidExcpData', compact('const', 'err', 'class', 'code', 'name'));
            }
            if ((! is_null($info)) && (! is_string($info))) {
                $err = 'SuggestionIsNotAString';
                exception('InvalidExcpData', compact('const', 'err', 'class', 'code', 'name'));
            }
            if ((! is_null($status)) && (! is_int($status))) {
                $err = 'StatusIsNotAnInteger';
                exception('InvalidExcpData', compact('const', 'err', 'class', 'code', 'name'));
            }

            self::$codes[$code] = join('::', [$class, $const]);
        }
    }

    public static function loadDomains(array $dirs = null)
    {
        $dirs = is_null($dirs) ? DomainManager::getDirs() : $dirs;

        foreach ($dirs as $dir) {
            $file = ospath($dir, self::DOMAIN_EXCP);
            if (! is_file($file)) {
                continue;
            }

            $excp = get_namespace_of_file($file, true);
            if (! $excp) {
                exception('InvalidNamespaceOfDomainExcpFile', compact('file'));
            }

            $domain = DomainManager::getKeyByNamespace($excp);
            $consts = get_class_consts($excp);
            self::validate($consts, $excp);
            self::$excps['domains'][$domain] = $consts;
        }
    }

    public static function getDefault()
    {
        return self::$excps['default'] ?? [];
    }

    public static function getDomains()
    {
        return self::$excps['domains'] ?? [];
    }
}
