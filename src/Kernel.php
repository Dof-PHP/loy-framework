<?php

declare(strict_types=1);

namespace Loy\Framework;

use Closure;
use Loy\Framework\Facade\Log;

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
        self::$uptime = microtime(true);

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
            Log::log('exception', $throwable->getMessage(), explode(PHP_EOL, $throwable->getTraceAsString()));
        });
        // Record every uncatched error regardless to the setting of the error_reporting setting
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            Log::log('error', $errstr, [
                'file' => $errfile,
                'line' => $errline,
                'code' => $errno,
            ]);
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

    public static function getUptime()
    {
        return self::$uptime;
    }

    public static function getRoot()
    {
        return self::$root;
    }
}
