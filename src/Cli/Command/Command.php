<?php

declare(strict_types=1);

namespace Loy\Framework\Cli\Command;

class Command
{
    /**
     * @cmd(app.start)
     * @comment(Start/Restart application)
     */
    public function start($console)
    {
        $console->output('Starting application...: TODO');
    }

    /**
     * @cmd(app.stop)
     * @comment(Stop/Halt application)
     */
    public function stop($console)
    {
        $console->output('Stopping application... TODO');
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
