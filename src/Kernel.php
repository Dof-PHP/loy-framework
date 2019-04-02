<?php

declare(strict_types=1);

namespace Loy\Framework;

use Closure;
use Loy\Framework\Facade\Log;
use Loy\Framework\Web\Kernel as WebKernel;
use Loy\Framework\Cli\Kernel as CliKernel;

/**
 * Loy Framework Core Kernel
 */
final class Kernel
{
    const RUNTIME_DIR = 'var';

    /** @var string: Project Root Directory */
    private static $root;

    /** @var float: Kernel boot time */
    private static $uptime;

    /** @var int: Kernel memory usage at beginning */
    private static $upmemory;

    /** @var array: Callbacks registered on kernel */
    private static $callbacks = [
        'shutdown' => [],
    ];

    /**
     * Core kernel handler - The genesis of application
     *
     * 1. Load framework and domain configurations
     * 2. Compile components and build application container
     *
     * @param string $root
     * @return null
     */
    public static function boot(string $root)
    {
        self::$uptime   = microtime(true);
        self::$upmemory = memory_get_usage();

        if (! is_dir(self::$root = $root)) {
            exception('InvalidProjectRoot', ['root' => $root]);
        }

        ConfigManager::init(self::$root);

        // Do some cleaning works before PHP process exit, like:
        // - Clean up database locks
        // - Rollback uncommitted transactions
        // - Reset some file permissions
        register_shutdown_function(function () {
            $error = error_get_last();
            if (! is_null($error)) {
                Log::error('LAST_ERROR_SHUTDOWN', $error);
            }
            foreach (self::$callbacks['shutdown'] as $callback) {
                if (is_callable($callback)) {
                    $callback();
                }
            }
        });

        // Record every uncatched exceptions
        set_exception_handler(function ($throwable) {
            $context = [
                'trace' => explode(PHP_EOL, $throwable->getTraceAsString()),
                'sapi'  => Kernel::getSapiContext(),
            ];

            Log::log('exception', $throwable->getMessage(), $context);
        });
        // Record every uncatched error regardless to the setting of the error_reporting setting
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $context = [
                'file' => $errfile,
                'line' => $errline,
                'sapi' => Kernel::getSapiContext(),
            ];

            Log::log('error', $errstr, $context);
        });

        DomainManager::compile(self::$root);

        ConfigManager::load(DomainManager::getMetas());
        
        $domains = DomainManager::getDirs();

        EntityManager::compile($domains);

        StorageManager::compile($domains);

        RepositoryManager::compile($domains);

        CommandManager::compile($domains);

        RouteManager::compile($domains);
    }

    public static function register(string $event, Closure $callback)
    {
        $event = trim(strtolower($event));

        if (in_array($event, ['shutdown'])) {
            self::$callbacks[$event][] = $callback;
        }
    }

    public static function getUpmemory()
    {
        return self::$upmemory;
    }

    public static function getUptime()
    {
        return self::$uptime;
    }

    public static function getRoot()
    {
        return self::$root;
    }

    public static function getSapiContext() : ?array
    {
        return WebKernel::isBooted() ? WebKernel::getContext() : (
            CliKernel::isBooted() ? CliKernel::getContext() : null
        );
    }
}
