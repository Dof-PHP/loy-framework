<?php

declare(strict_types=1);

namespace Dof\Framework\Cli;

use Throwable;
use Dof\Framework\Collection;

class Console
{
    const INFO_COLOR = 'LIGHT_GRAY';
    const FAIL_COLOR = 'RED';
    const SUCCESS_COLOR = 'GREEN';
    const WARNING_COLOR = 'YELLOW';
    const ERROR_COLOR = 'LIGHT_RED';

    /** @var string: Entry of command */
    private $entry;

    /** @var string: Command name */
    private $name;

    /** @var array: Options of command */
    private $options;

    /** @var array: Parameters of command */
    private $params;

    /**
     * Console usable colors
     *
     * See: <http://blog.lenss.nl/2012/05/adding-colors-to-php-cli-script-output>
     */
    private $colors = [
        'BLACK'  => '0;30',
        'BLUE'   => '0;34',
        'GREEN'  => '0;32',
        'CYAN'   => '0;36',
        'RED'    => '0;31',
        'PURPLE' => '0;35',
        'BROWN'  => '0;33',
        'YELLOW' => '1;33',
        'WHITE'  => '1;37',
        'LIGHT_GRAY'   => '0;37',
        'DARK_GRAY'    => '1;30',
        'LIGHT_BLUE'   => '1;34',
        'LIGHT_GREEN'  => '1;32',
        'LIGHT_CYAN'   => '1;36',
        'LIGHT_RED'    => '1;31',
        'LIGHT_PURPLE' => '1;35',
    ];

    public function line($line = null, int $cnt = 1)
    {
        $lines = str_repeat(PHP_EOL, $cnt);

        echo is_null($line) ? $lines : (stringify($line).$lines);
    }

    public function output($result)
    {
        echo stringify($result);
    }

    public function title(string $text)
    {
        $this->line($this->render($text, 'LIGHT_BLUE'));
    }

    public function info(string $text, bool $exit = false)
    {
        $this->line($this->render($text, self::INFO_COLOR));

        if ($exit) {
            $this->exit();
        }
    }

    public function warning(string $text, bool $exit = false)
    {
        $this->line($this->render($text, self::WARNING_COLOR));

        if ($exit) {
            $this->exit();
        }
    }

    public function success(string $text, bool $exit = false)
    {
        $this->line($this->render($text, self::SUCCESS_COLOR));

        if ($exit) {
            $this->exit();
        }
    }

    public function error(string $text, bool $exit = false)
    {
        $this->line($this->render($text, self::ERROR_COLOR));

        if ($exit) {
            $this->exit();
        }
    }

    public function fail(string $text, bool $exit = false)
    {
        $this->line($this->render($text, self::FAIL_COLOR));

        if ($exit) {
            $this->exit();
        }
    }

    public function render(string $text, string $color, bool $output = false) : ?string
    {
        if (! isset($this->colors[$color])) {
            $this->exception('ConsoleColorNotFound', compact('color'));
        }

        $_color = $this->colors[$color];

        $text = "\033[{$_color}m{$text}\033[0m";
        if ($output) {
            return $this->output($text);
        }

        return $text;
    }

    public function exception(string $message, array $context = [], Throwable $previous = null)
    {
        $context = parse_throwable($previous, $context);

        $this->error(json_pretty([$message, $context]), true);
    }

    public function exit()
    {
        exit;
    }

    /**
     * Get the first parameter or option value by name
     */
    public function first(string $option = null)
    {
        if ($option && $this->hasOption($option)) {
            return $this->getOption($option);
        }

        return $this->getParams()[0] ?? null;
    }

    public function hasOption(string $name, $default = null) : bool
    {
        return $this->options->has($name);
    }

    public function getOption(string $name, $default = null)
    {
        return $this->options->get($name, $default);
    }

    /**
     * Getter for entry
     *
     * @return string
     */
    public function getEntry(): string
    {
        return $this->entry;
    }
    
    /**
     * Setter for entry
     *
     * @param string $entry
     * @return Console
     */
    public function setEntry(string $entry)
    {
        $this->entry = $entry;
    
        return $this;
    }

    /**
     * Getter for name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Setter for name
     *
     * @param string $name
     * @return Console
     */
    public function setName(string $name)
    {
        $this->name = $name;
    
        return $this;
    }

    /**
     * Getter for options
     *
     * @return array
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }
    
    /**
     * Setter for options
     *
     * @param array $options
     * @return Console
     */
    public function setOptions(array $options)
    {
        $this->options = collect($options);
    
        return $this;
    }

    /**
     * Getter for params
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
    
    /**
     * Setter for params
     *
     * @param array $params
     * @return Console
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    
        return $this;
    }
}
