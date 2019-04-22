<?php

declare(strict_types=1);

namespace Dof\Framework\Cli;

use Dof\Framework\Collection;

class Console
{
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

    public function output($result)
    {
        echo stringify($result), PHP_EOL;
    }

    public function info(string $text) : string
    {
        $this->output($this->render($text, 'BLUE'));
    }

    public function success(string $text) : string
    {
        $this->output($this->render($text, 'GREEN'));

        exit;
    }

    public function fail(string $text) : string
    {
        $this->output($this->render($text, 'RED'));

        exit;
    }

    public function render(string $text, string $color) : string
    {
        if (! isset($this->colors[$color])) {
            $this->exception('ConsoleColorNotFound', compact('color'));
        }

        $_color = $this->colors[$color];

        return "\033[{$_color}m{$text}\033[0m";
    }

    public function exception(string $message, array $context = [])
    {
        if ($previous = ($context['__previous'] ?? null)) {
            array_unset($previous, '__previous', '__trace');
            ksort($previous);

            $this->output($this->render(enjson($previous), 'LIGHT_RED'));
        } else {
            $this->output($this->render($message, 'LIGHT_RED'));
        }

        exit;
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
