<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Throwable;
use Dof\Framework\Container;
use Dof\Framework\OFB\Traits\DI;

abstract class Service
{
    use DI;

    const EXCEPTION = 'DofServiceException';

    /** @var array: A config map for custom exceptions */
    protected $__errors__ = [];

    abstract public function execute();

    final public function error(
        array $error,
        int $status = null,
        int $code = null,
        string $text = null
    ) {
        $code = is_null($code) ? ($error[0] ?? -1) : $code;
        $info = $error[1] ?? -1;
        $text = is_null($text) ? ($error[2] ?? null) : $text;

        // TODO
        // $lang = 'zh';
        // $text = i18n($info, static::class, $lang);

        $this->__errors__[$info] = [$code, $status, $text];

        return $this;
    }

    /**
     * Re-Throw the exception throwed by other services
     */
    final public function throw(Throwable $previous)
    {
        if (is_anonymous($previous) && is_exception($previous, self::EXCEPTION)) {
            $context = [];
            $context = parse_throwable($previous, $context);
            $context = $context['__previous'] ?? [];
            $message = $context['message'] ?? null;
            if ($message) {
                $context = $context['context'];
                unset($context['__errors__']);
                return $this->exception($message, $context);
            }
        }

        throw $previous;
    }

    final public function excp(array $excp, array $context = [], Throwable $previous = null)
    {
        $context = parse_throwable($previous, $context);

        $message = $excp[1] ?? null;

        $context['__excp__'] = $excp;
        $context['__errors__'] = $this->__errors__;

        exception(self::EXCEPTION, compact('message', 'context'), $previous);
    }

    /**
     * Throw an exception in a serivce
     */
    final public function exception(string $message, array $context = [], Throwable $previous = null)
    {
        $context = parse_throwable($previous, $context);

        $context['__errors__'] = $this->__errors__;

        exception(self::EXCEPTION, compact('message', 'context'), $previous);
    }

    final public static function init()
    {
        return Container::di(static::class);
    }

    final public function toString() : string
    {
        return $this->__toString();
    }

    final public function __toString() : string
    {
        return enjson($this->__toArray());
    }

    final public function toArray() : array
    {
        return $this->__toArray();
    }

    final public function __toArray() : array
    {
        return get_object_vars($this);
    }

    final public static function __callStatic(string $method, array $argvs = [])
    {
        return call_user_func_array([self::init(), $method], $argvs);
    }
}
