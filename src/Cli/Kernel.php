<?php

declare(strict_types=1);

namespace Dof\Framework\Cli;

use Throwable;
use Dof\Framework\Kernel as Core;
use Dof\Framework\ConfigManager;
use Dof\Framework\CommandManager;
use Dof\Framework\DSL\CLIA;
use Dof\Framework\Facade\Console;
use Dof\Framework\Facade\Log;

final class Kernel
{
    private static $booted = false;

    private static $argvs;

    public static function handle(string $root, array $argvs)
    {
        if (PHP_SAPI !== 'cli') {
            exit('RunCmdKernelInNonCli');
        }

        self::$booted = true;
        self::$argvs  = $argvs;

        try {
            Core::register('shutdown', function () {
                // Logging
                $duration = microtime(true) - Core::getUptime();
                $memcost  = memory_get_usage() - Core::getUpmemory();
                Log::log('cli', enjson([
                    $duration,
                    $memcost,
                    memory_get_peak_usage(),
                    count(get_included_files()),
                ]), self::getContext(false));

                // Reset file/directory permission
                $runtime = ospath(Core::getRoot(), Core::RUNTIME);
                if (is_dir($runtime)) {
                    if ($owner = ConfigManager::getFramework('runtime.permission.owner')) {
                        chownr($runtime, $owner);
                    }
                    if ($group = ConfigManager::getFramework('runtime.permission.group')) {
                        chgrpr($runtime, $group);
                    }
                    if ($mode = ConfigManager::getFramework('runtime.permission.mode', 0644)) {
                        chmodr($runtime, $mode);
                    }
                }
            });

            Core::boot($root);
        } catch (Throwable $e) {
            Kernel::throw('KernelBootFailed', compact('root', 'argvs'), $e);
        }

        list($entry, $cmd, $options, $params) = CLIA::build($argvs);
        if (! $cmd) {
            $cmd = 'dof';
        }
        $isDomain = strtolower($cmd) === 'domain';
        if ($isDomain) {
            $cmd = array_shift($params);
            if (is_null($cmd)) {
                Kernel::throw('MissingDomainCommandName');
            }
        }

        $_cmd = CommandManager::get($cmd, $isDomain);
        if (! $_cmd) {
            Kernel::throw('CommandNotFound', compact('cmd', 'isDomain'));
        }
        $class = $_cmd['class'] ?? null;
        if ((! $class) || (! class_exists($class))) {
            Kernel::throw('CommandClassNotFound', compact('cmd', 'class'));
        }
        $method = $_cmd['method'] ?? null;
        if ((! $method) || (! method_exists($class, $method))) {
            Kernel::throw('CommandHandlerNotExists', compact('cmd', 'class', 'method'));
        }

        $console = Console::setEntry($entry)->setName($cmd)->setOptions($options)->setParams($params);

        try {
            $result = (new $class)->{$method}($console);

            if (! is_null($result)) {
                $console->output($result);
            }
        } catch (Throwable $e) {
            Kernel::throw('CommandExecuteFailed', compact('cmd', 'class', 'method'), $e);
        }
    }

    public static function throw(string $name, array $context = [], Throwable $previous = null)
    {
        Console::exception($name, parse_throwable($previous, $context));
    }

    public static function isBooted() : bool
    {
        return self::$booted;
    }

    public static function getContext(bool $sapi = true) : ?array
    {
        $argvs = self::$argvs;
        array_unshift($argvs, get_current_user());
        $argvs = ['cli' => $argvs];

        return $sapi ? $argvs : [$argvs, Core::getContext()];
    }
}
