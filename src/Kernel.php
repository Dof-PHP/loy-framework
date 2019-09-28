<?php

declare(strict_types=1);

namespace Dof\Framework;

use Closure;
use ErrorException;
use Dof\Framework\Facade\Log;
use Dof\Framework\Web\Kernel as WebKernel;
use Dof\Framework\Web\Port;
use Dof\Framework\Web\Route;
use Dof\Framework\Cli\Kernel as CliKernel;
use Dof\Framework\OFB\AUX\ASCIINonce;
use Dof\Framework\OFB\AUX\Str;
use Dof\Framework\OFB\AUX\Num;
use Dof\Framework\DSL\IFRSN;
use Dof\Framework\Facade\CURL;

/**
 * Dof Framework Core Kernel
 */
final class Kernel
{
    const RUNTIME = 'var';
    const LOCATE  = __DIR__;
    const TEMPLATE = 'templates';

    const SERVICE = 'Service';
    const ASSEMBLER = 'Assembler';
    const EVENT = 'Event';
    const LISTENER = 'Listener';
    const COMMAND = 'Command';

    /** @var string: Project Root Directory */
    private static $root;

    /** @var float: Kernel boot time */
    private static $uptime;

    /** @var bool: Inner error happened or not */
    private static $error;

    /** @var int: Kernel memory usage at beginning */
    private static $upmemory;

    /** @var array: Callbacks registered on kernel */
    private static $callbacks = [
        'shutdown' => [],
        'before-shutdown' => [],
    ];

    private static $context = [];

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
        self::$upmemory = memory_get_usage();

        self::alias();

        // Do some cleaning works before PHP process exit, like:
        // - Clean up database locks
        // - Rollback uncommitted transactions
        // - Reset some file permissions
        register_shutdown_function(function () {
            $error = error_get_last();
            if (! is_null($error)) {
                self::$error = true;
                Log::error('LAST_ERROR_SHUTDOWN', $error);
            }
            foreach (self::$callbacks['before-shutdown'] as $callback) {
                if (is_callable($callback)) {
                    $callback();
                }
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
                explode(PHP_EOL, $throwable->getTraceAsString()),
                Kernel::getSapiContext(),
            ];

            Log::log('exception', $throwable->getMessage(), $context);
        });

        // Record every uncatched error regardless to the setting of the error_reporting setting
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $context = [
                $errfile,
                $errline,
                Kernel::getSapiContext(true),
            ];

            Log::error($errstr, $context);
            // throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        if (! is_dir(self::$root = $root)) {
            exception('InvalidProjectRoot', ['root' => $root]);
        }

        ConfigManager::init(self::$root);

        if ($tz = ConfigManager::getEnv('TIMEZONE')) {
            // if (in_array($tz, timezone_identifiers_list())) {
            date_default_timezone_set($tz);
            // }
        }

        DomainManager::load(self::$root);

        ConfigManager::load(DomainManager::getMetas());
        
        $domains = DomainManager::getDirs();

        EntityManager::load($domains);

        ModelManager::load($domains);

        StorageManager::load($domains);

        RepositoryManager::load($domains);

        CommandManager::load($domains);

        WrapinManager::load($domains);

        PortManager::load($domains);

        EventManager::load($domains);

        ExcpManager::load($domains);
    }

    private static function alias()
    {
        class_alias(ConfigManager::class, 'ConfigManager');
        class_alias(DomainManager::class, 'DomainManager');
        class_alias(EntityManager::class, 'EntityManager');
        class_alias(ModelManager::class, 'ModelManager');
        class_alias(StorageManager::class, 'StorageManager');
        class_alias(RepositoryManager::class, 'RepositoryManager');
        class_alias(CommandManager::class, 'CommandManager');
        class_alias(WrapinManager::class, 'WrapinManager');
        class_alias(PortManager::class, 'PortManager');
        class_alias(EventManager::class, 'EventManager');
        class_alias(CacheManager::class, 'CacheManager');
        class_alias(ExcpManager::class, 'ExcpManager');
        class_alias(Container::class, 'Container');
        class_alias(IFRSN::class, 'IFRSN');
        class_alias(CURL::class, 'CURL');
        class_alias(Port::class, 'Port');
        class_alias(Route::class, 'Route');
        class_alias(Log::class, 'Log');
        class_alias(GWT::class, 'GWT');
        class_alias(IS::class, 'IS');
        class_alias(EXCP::class, 'EXCP');
        class_alias(Paginator::class, 'Paginator');
        class_alias(Reflector::class, 'Reflect');
        class_alias(TypeHint::class, 'TypeHint');
        class_alias(ASCIINonce::class, 'ASCIINonce');
        class_alias(Str::class, 'Str');
        class_alias(Num::class, 'Num');
    }

    public static function register(string $event, Closure $callback)
    {
        $event = trim(strtolower($event));

        if (in_array($event, ['shutdown', 'before-shutdown'])) {
            self::$callbacks[$event][] = $callback;
        }
    }

    public static function getUpmemory()
    {
        return self::$upmemory;
    }

    public static function getUptime(bool $float = true)
    {
        return $float ? self::$uptime : intval(self::$uptime);
    }

    public static function root() : string
    {
        return dirname(self::LOCATE);
    }

    public static function getRoot()
    {
        return self::$root;
    }

    public static function getError()
    {
        return self::$error;
    }

    public static function formatCompileFile(...$params) : string
    {
        $dir = ospath(
            self::$root,
            self::RUNTIME,
            'compile'
        );

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return ospath($dir, join('.', [md5(join('.', $params)), 'compile']));
    }

    public static function appendContext(string $key, array $context = null, string $_key = null)
    {
        if (! $context) {
            return;
        }
        if ($_key) {
            self::$context[$key][$_key] = $context;
        } else {
            self::$context[$key][] = $context;
        }
    }

    public static function setContext(string $key, array $context = null)
    {
        self::$context[$key] = $context;
    }

    public static function getContext() : array
    {
        return self::$context;
    }

    public static function getSapiContext(bool $sapi = true) : ?array
    {
        return WebKernel::isBooted() ? WebKernel::getContext($sapi) : (
            CliKernel::isBooted() ? CliKernel::getContext($sapi) : null
        );
    }
}
