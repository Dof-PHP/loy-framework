<?php

declare(strict_types=1);

namespace DOF;

use Throwable;
use Closure;
use DOF\Util\F;
use DOF\Util\FS;
use DOF\Util\Rand;
use DOF\Util\Format;
use DOF\Traits\Tracker;

abstract class KernelInitializer
{
    use Tracker;

    /** @var float: Time kernel was instantiated */
    protected $uptime;

    /** @var float: CPU user-time occupied when web kernel was instantiated */
    protected $upcpu;

    /** @var int: Amount of memeory used by PHP process, as bytes */
    protected $upmemory;

    /** @var int: Amount of PHP files included when kernel was instantiated  */
    protected $upfiles;

    /** @var string: PHP Server API been used in kernel */
    protected $sapi;

    /** @var string: User of PHP process */
    protected $user;

    /** @var string: Language code */
    public $language = 'en';

    /** @var array: Input data for kernel, will be formatted if necessary */
    public $stdin = [];

    /** @var array: Output data of kernel - normally, will be formatted if necessary */
    public $stdout = [];

    /** @var array: Output data of kernel - unexpectedly, will be formatted if necessary */
    public $stderr = [];

    public function __construct(string $sapi = PHP_SAPI)
    {
        $this->uptime = \microtime(true);
        $this->upcpu = \getrusage()['ru_utime.tv_usec'] ?? 0;
        $this->upfiles = \count(get_included_files());
        $this->upmemory = \memory_get_usage();
        $this->sapi = $sapi;
        $this->user = F::phpuser();

        $this->__TRACE_ID__ = 0;
        $this->__TRACE_SN__ = Rand::uuid4();

        $this->logger(function ($logger) use ($sapi) {
            $logger->setSapi($sapi);
        }, false);

        $this->register('shutdown', static::class, function () use ($sapi) {
            $this->logger()->log($sapi, $this->user, [
                [
                    \microtime(true) - $this->uptime,
                    [$this->upcpu, \getrusage()['ru_utime.tv_usec'] ?? 0],
                    [$this->upmemory, \memory_get_usage(), \memory_get_peak_usage()],
                    [$this->upfiles, \count(get_included_files())],
                ],
                [$this->language, $this->stdin, $this->stdout, $this->stderr],
                $this->__CONTEXT__,
            ]);
        });

        // Do some cleaning works before PHP process exit, like:
        // - Clean up database locks
        // - Rollback uncommitted transactions
        // - Reset some file permissions
        \register_shutdown_function(function () {
            $error = error_get_last();
            if (! \is_null($error)) {
                $this->logger()->error('PHP_SHUTDOWN_LAST_ERROR', $error);
            }
            foreach (($this->__CALLBACK__['before-shutdown'] ?? []) as $item) {
                foreach ($item as $origin => $callback) {
                    if (\is_callable($callback)) {
                        try {
                            $callback();
                        } catch (Throwable $th) {
                            $this->logger()->error('PHP_BEFORE_SHUTDOWN_EXCEPTION', [$origin, Format::throwable($th)]);
                        }
                    }
                }
            }
            foreach (($this->__CALLBACK__['shutdown'] ?? []) as $item) {
                foreach ($item as $origin => $callback) {
                    if (\is_callable($callback)) {
                        try {
                            $callback();
                        } catch (Throwable $th) {
                            $this->logger()->error('PHP_SHUTDOWN_EXCEPTION', [$origin, Format::throwable($th)]);
                        }
                    }
                }
            }
        });

        // Record every uncatched exceptions
        \set_exception_handler(function ($e) {
            $this->logger()->exception('UNCATCHED_EXCEPTION', [Format::throwable($e), $this->__CONTEXT__]);
        });

        // Record every uncatched error regardless to the setting of the error_reporting setting
        \set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $this->logger()->error($errstr, [
                'code' => $errno,
                'file' => $errfile,
                'line' => $errline,
                'more' => $this->__CONTEXT__,
            ]);
        });
    }

    final public function user()
    {
        return $this->user;
    }

    final public function sapi()
    {
        return $this->sapi;
    }
}
