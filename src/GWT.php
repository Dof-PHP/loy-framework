<?php

declare(strict_types=1);

namespace Loy\Framework;

/**
 * Given-When-Then descriptive model
 */
final class GWT
{
    private static $success = [];
    private static $failure = [];
    private static $exception = [];

    public static function execute($given, $when, $then, &$result) : bool
    {
        // See: <https://stackoverflow.com/questions/7067536/how-to-call-a-closure-that-is-a-class-variable>
        $given  = is_closure($given) ? ($given)() : $given;
        $result = is_closure($when) ? ($when)($given) : $when;

        if (is_closure($then)) {
            $then = $then($result);
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
