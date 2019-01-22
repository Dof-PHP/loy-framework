<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\Exception\InvalidProjectRootException;

final class ConfigManager
{
    const DEFAULT_DOMAIN_DIR = ['config', 'domain'];
    const DEFAULT_FRAMEWORK_DIR = ['config', 'framework'];
    const FILENAME_REGEX = '#^([a-z]+)\.php$#';

    private static $domainDefault    = [];
    private static $frameworkDefault = [];

    public static function load(array $dirs)
    {
        dd($dirs);
    }

    public static function init(string $projectRoot)
    {
        if (! is_dir($projectRoot)) {
            throw new InvalidProjectRootException($projectRoot);
        }

        self::initDir(ospath($projectRoot, self::DEFAULT_DOMAIN_DIR), self::$domainDefault);
        self::initDir(ospath($projectRoot, self::DEFAULT_FRAMEWORK_DIR), self::$frameworkDefault);
    }

    public static function initDir(string $path, array &$result)
    {
        if (is_dir($path)) {
            list_dir($path, function ($list, $dir) use (&$result) {
                foreach ($list as $filename) {
                    if (in_array($filename, ['.', '..'])) {
                        continue;
                    }
                    $path = ospath($dir, $filename);
                    $matches = [];
                    if (1 === preg_match(self::FILENAME_REGEX, $filename, $matches)) {
                        $key = $matches[1] ?? false;
                        if ($key) {
                            $result[$key] = load_php($path);
                        }
                        continue;
                    }

                    // ignore sub-dirs
                }
            });
        }
    }
}
