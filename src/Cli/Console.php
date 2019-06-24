<?php

declare(strict_types=1);

namespace Dof\Framework\Cli;

use Throwable;
use Closure;
use Dof\Framework\Container;
use Dof\Framework\Collection;

class Console
{
    const INFO_COLOR = 'LIGHT_GRAY';
    const TITLE_COLOR = 'LIGHT_BLUE';
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
    const COLORS = [
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

    public function di(string $namespace)
    {
        return Container::di($namespace);
    }

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
        $this->line($this->render($text, self::TITLE_COLOR));
    }

    public function progress(iterable $tasks, Closure $do, bool $outputProgress = false)
    {
        $current = 1;
        $total = count($tasks);
        $output = [];

        $title = get_buffer_string(function () use ($total) {
            $this->info(sprintf("[%s] %s", microftime('T Y-m-d H:i:s'), "Progress Tasks: {$total}"));
        });

        if (! $outputProgress) {
            $this->output($title);
        }

        $_output = '';
        foreach ($tasks as $key => $task) {
            $percent = ($current / $total) * 100;
            $_percent = intval($percent);

            $done = $this->render(str_repeat('*', $_percent), self::SUCCESS_COLOR);
            $left = $this->render(str_repeat('·', (100 - $_percent)), self::INFO_COLOR);

            $__output = get_buffer_string(function () use ($do, $key, $task) {
                $do($key, $task);
            });

            if ($outputProgress) {
                $_output .= $__output;
                $this->clear($title);
                $this->output($_output);
                $this->line();
            }

            printf("\r(%d/%d) [%-100s] (%01.2f%%)", $current, $total, $done.$left, $percent);

            ++$current;
        }

        $this->line();

        $this->info(sprintf("[%s] %s", microftime('T Y-m-d H:i:s'), 'Progress Finished.'));
    }

    public function clear(string $output = null)
    {
        // See: <https://stackoverflow.com/questions/37774983/clearing-the-screen-by-printing-a-character>
        printf("\033c");

        if (! is_null($output)) {
            $this->output($output);
        }
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
        if (! isset(self::COLORS[$color])) {
            $this->exception('ConsoleColorNotFound', compact('color'));
        }

        $_color = self::COLORS[$color];

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

    public function setOption(string $name, $val)
    {
        $this->options->set($name, $val);

        return $this;
    }

    public function getOption(string $option, $default = null, bool $exception = false)
    {
        $_option = $this->options->get($option, $default);

        if (is_collection($_option) && ($_option->count() === 0)) {
            $_option = null;
        }

        if (is_null($_option) && $exception) {
            $this->exception('MissingOption', compact('option'));
        }
        
        return $_option;
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
