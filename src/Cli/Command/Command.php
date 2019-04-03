<?php

declare(strict_types=1);

namespace Loy\Framework\Cli\Command;

use Loy\Framework\Kernel;
use Loy\Framework\Web\Kernel as WebKernel;

class Command
{
    /**
     * @cmd(web.start)
     * @comment(Start/Restart web application)
     */
    public function start($console)
    {
        $lock = ospath(Kernel::getRoot(), WebKernel::HALT_FLAG);
        if (! is_file($lock)) {
            $console->output('OK! Application is up and running.');
            return;
        }

        $res = unlink($lock);
        if ($res === false) {
            $console->output('Failed!');
            return;
        }
        $console->output('Success!');
    }

    /**
     * @cmd(web.stop)
     * @comment(Stop/Halt web application)
     */
    public function stop($console)
    {
        $lock = ospath(Kernel::getRoot(), WebKernel::HALT_FLAG);
        if (is_file($lock)) {
            $force = strtolower($console->getOption('force', '0'));
            if (($force === '1') || ('true' === $force)) {
                if (false === unlink($lock)) {
                    $console->output('ERROR! Force shutdown failed.');
                    return;
                }
            } else {
                $console->output('OK! Application was shutdown already.');
                return;
            }
        }

        $message = $console->getOption('message');
        $since = microftime('T Y-m-d H:i:s');
        $res = file_put_contents($lock, enjson(compact('message', 'since')));
        if (false === $res) {
            $console->output('Failed!');
            return;
        }

        $console->output('Success!');
    }

    /**
     * @cmd(list)
     * @comment(List commands)
     */
    public function list($console)
    {
        $console->output('TODO: display commands list');
    }
}
