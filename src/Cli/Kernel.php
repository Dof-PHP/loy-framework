<?php

declare(strict_types=1);

namespace Loy\Framework\Cli;

use Throwable;
use Loy\Framework\Kernel as Core;
use Loy\Framework\CommandManager;
use Loy\Framework\DSL\CLIA;
use Loy\Framework\Facade\Console;

final class Kernel
{
    public static function handle(string $root, array $argvs)
    {
        if (PHP_SAPI !== 'cli') {
            exit('RunCmdKernelInNonCli');
        }

        try {
            Core::boot($root);
        } catch (Throwable $e) {
            Kernel::throw('KernelBootFailed', compact('root', 'argvs'), $e);
        }

        list($entry, $cmd, $options, $params) = CLIA::build($argvs);
        $_cmd = CommandManager::get($cmd);
        if (! $_cmd) {
            Kernel::throw('CommandNotFound', compact('cmd'));
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

            $console->output($result);
        } catch (Throwable $e) {
            Kernel::throw('CommandExecuteFailed', compact('cmd', 'class', 'method'), $e);
        }
    }

    public static function throw(string $name, array $context = [], Throwable $previous = null)
    {
        Console::exception($name, parse_throwable($previous, $context));
    }
}
