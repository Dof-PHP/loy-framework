<?php

declare(strict_types=1);

namespace Loy\Framework\Cli;

class Command
{
    /** @var string: Entry of command */
    private $entry;

    /** @var string: Command name */
    private $name;

    /** @var array: Options of command */
    private $options;

    /** @var array: Parameters of command */
    private $params;
}
