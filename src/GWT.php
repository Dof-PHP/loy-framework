<?php

declare(strict_types=1);

namespace Dof\Framework;

/**
 * Given-When-Then descriptive model
 */
final class GWT
{
    private static $success = [];
    private static $failure = [];
    private static $exception = [];

    /**
     * Run GWT test cases by directory and exclude for some files
     *
     * @param string $dir: The directory stores tests cases
     * @param array $excludes: The realpath of files to exclude
     */
    public static function run(string $dir, array $excludes = [])
    {
        walk_dir($dir, function ($path) use ($excludes) {
            $realpath = $path->getRealPath();
            foreach ($excludes as $exclude) {
                $exclude = realpath($exclude);
                if (! $exclude) {
                    continue;
                }
                if ($realpath === $exclude) {
                    return;
                }
            }

            if ($path->isDir()) {
                run_gwt_tests($realpath, $excludes);
                return;
            }
            if ($path->isFile() && ci_equal($path->getExtension(), 'php')) {
                include_once $realpath;
            }
        });
    }

    public static function execute($given, $when, $then, &$result) : bool
    {
        $tester = new Tester;

        // See: <https://stackoverflow.com/questions/7067536/how-to-call-a-closure-that-is-a-class-variable>
        $given  = is_closure($given) ? ($given)() : $given;
        $result = is_closure($when) ? ($when)($given) : $when;

        if (is_closure($then)) {
            $then = $then($result, $tester);
            return $then === true;
        }

        return $then !== $result;
    }

    public static function append(string $title, string $file, int $line, $result, bool $success = null)
    {
        if ($success) {
            self::$success[] = [$title, $file, $line];
        } elseif (is_null($success)) {
            self::$exception[] = [$title, $file, $line, $result];
        } else {
            self::$failure[] = [$title, $file, $line, $result];
        }
    }

    public static function getSuccess() : array
    {
        return self::$success;
    }

    public static function getFailure() : array
    {
        return self::$failure;
    }

    public static function getException() : array
    {
        return self::$exception;
    }
}
