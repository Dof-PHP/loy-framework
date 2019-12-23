<?php

declare(strict_types=1);

namespace DOF;

use DOF\DMN;
use DOF\Convention;
use DOF\Traits\Manager;
use DOF\Exceptor\ErrManagerExceptor;
use DOF\Util\FS;
use DOF\Util\IS;
use DOF\Util\Str;
use DOF\Util\Exceptor;

/**
 * User defined errors in domain {$domain}
 *
 * #0: int; Global unique error code among domains
 * #1: string; Error default description in a language, support variable placeholder
 * #2: string; Error default suggestion in a language
 *
 * For example: `const USER_NOF_FOUND = [{$no}40401, 'User not exists', 'Please contact administrator for help'];`
 */
final class ErrManager
{
    use Manager;

    public static function throw(array $err, array $context = [], Throwable $previous = null)
    {
        $code = $err[0] ?? -1;

        $except = new Exceptor($previous);

        throw $exceptor
            ->setProxy(true)
            ->setContext($context)
            ->setNo($code)
            ->setName(self::name($code))
            ->setInfo($err[1] ?? null)
            ->setSuggestion($err[2] ?? null)
            ->setChain(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
    }

    public static function init()
    {
        foreach (DMN::list() as $domain => $dir) {
            if (\is_file($err = FS::path($dir, Convention::FILE_ERR))) {
                ErrManager::addDomain($domain, $err);
            }
        }
    }

    protected static function assemble(array $ofClass, array $ofProperties, array $ofMethods, string $type)
    {
        $class = $ofClass['namespace'] ?? null;
        $no = $ofClass['doc']['NO'] ?? null;
        if (1 !== \preg_match(Convention::REGEX_ERR_CODE, \strval($no))) {
            throw new ErrManagerExceptor('INVALID_ERR_PREFIX_NO', \compact('class', 'no'));
        }

        foreach ($ofClass['consts'] ?? [] as $const => $err) {
            if (! $err) {
                throw new ErrManagerExceptor('EMPTY_ERR_ITEM', \compact('class', 'const', 'err'));
            }
            if (! IS::array($err, 'index')) {
                throw new ErrManagerExceptor('NON_INDEX_ARRAY_ERR_ITEM', \compact('class', 'const', 'err'));
            }
            $code = $err[0] ?? null;
            if (IS::empty($code)) {
                throw new ErrManagerExceptor('ERR_CODE_MISSING', \compact('class', 'const', 'code'));
            }
            if (1 !== \preg_match(Convention::REGEX_ERR_CODE, \strval($code))) {
                throw new ErrManagerExceptor('INVALID_ERR_CODE_LENGTH', \compact('class', 'const', 'code'));
            }
            if (! Str::start($no, \strval($code))) {
                throw new ErrManagerExceptor('ERR_CODE_NOT_PREFIX_WITH_NO', \compact('class', 'const', 'code', 'no'));
            }
            if ($conflict = (self::$data[$code] ?? false)) {
                throw new ErrManagerExceptor('ERR_CODE_CONFLICT', \compact('class', 'const', 'code', 'conflict'));
            }
            $desc = $err[1] ?? null;
            if ($desc && (! \is_string($desc))) {
                throw new ErrManagerExceptor('NONSTRING_ERR_DESC', \compact('class', 'const', 'desc'));
            }
            $suggestion = $err[2] ?? null;
            if ($suggestion && (! \is_string($suggestion))) {
                throw new ErrManagerExceptor('NONSTRING_ERR_SUGGESTION', \compact('class', 'const', 'suggestion'));
            }

            self::$data[$code] = [$class, $const, $desc, $suggestion];
        }
    }

    public static function get($code) : ?array
    {
        if (\is_int($code) || \is_string($code)) {
            return self::$data[$code] ?? null;
        }

        return null;
    }

    public static function name($code, string $default = Convention::DEFAULT_ERR, bool $throw = false) : ?string
    {
        if (\is_int($code) || \is_string($code)) {
            return (string) (self::$data[$code][1] ?? $default);
        }

        if ($throw) {
            throw new ErrManagerExceptor('INVALID_ERR_CODE_TYPE', \compact('code'));
        }

        return null;
    }
}
