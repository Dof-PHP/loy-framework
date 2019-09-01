<?php

declare(strict_types=1);

namespace Dof\Framework\Log\Logger;

use Dof\Framework\Kernel;
use Dof\Framework\ConfigManager;
use Dof\Framework\Log\LoggerInterface;

class File implements LoggerInterface
{
    use LoggerTrait;

    /** @var string: Where Log files will be stored, relative to project root */
    private $directory = 'log';

    /** @var string: The dirname for achieving log files */
    private $archive =  'archive';

    /** @var int: The filesize limit of live log file */
    private $filesize = 4194304;    // 4M (default)

    /** @var string: The suffix of log file */
    private $suffix = 'log';

    /** @var bool: Debug mode */
    private $debug = false;

    /** @var string: Debug key used to name log file */
    private $debugKey;

    /** @var int: Permission of log dirs and files */
    private $permission = 0755;

    /** @var string: Log level keyword */
    private $level;

    /** @var string: Separator of each single log text */
    private $separator = PHP_EOL;

    public function log($level, $message, array $context = [])
    {
        $this->level = stringify($level);
        if (! $this->level) {
            exception('MissingLogLevel');
        }

        // Hard-code an index array to shorten log text
        $this->save(enjson([
            microftime('T Ymd His', ' '),
            stringify($message),
            $context,
        ]));
    }

    public function save(string $log)
    {
        $user = get_php_user();
        $pid  = $this->processId();
        $dirs = $this->debug ? [$this->directory, $user, 'debug'] : [$this->directory, $user];
        $path = ospath(Kernel::getRoot(), Kernel::RUNTIME, join('-', $dirs));
        $file = ospath($path, join('.', [$this->level, PHP_SAPI, $user, $pid, $this->suffix]));
        if (! is_dir($path)) {
            mkdir($path, $this->permission, true);
        }

        if (is_file($file) && (filesize($file) >= $this->filesize)) {
            $time  = time();
            $year  = date('Y', $time);
            $month = date('m', $time);
            $day   = date('d', $time);
            $archive = ospath($path, $this->archive, $year, $month, $day, $user, $this->level, PHP_SAPI);
            if (! is_dir($archive)) {
                mkdir($archive, $this->permission, true);
            }

            $_archive = join('.', [microftime('Ymd-His', '-'), $user, $this->level, PHP_SAPI, $pid, $this->suffix]);
            $_archive = ospath($archive, $_archive);

            rename($file, $_archive);
        }

        $fp = fopen($file, 'a+');
        if (false !== $fp) {
            // stream_set_blocking($fp, 0);
            // if (flock($fp, LOCK_EX)) {
            fwrite($fp, $this->seperate($log));
            // }
            // flock($fp, LOCK_UN);
            fclose($fp);

            // file_put_contents($file, $this->seperate($log), FILE_APPEND | LOCK_EX);
            // error_log($this->seperate($log), 3, $file);
        }
    }

    private function processId()
    {
        if ($this->debug && $this->debugKey) {
            return $this->debugKey;
        }

        return ConfigManager::getEnv('FILE_LOGGING_SINGLE', false) ? 'single' : getmypid();
    }

    public function seperate(string $log) : string
    {
        return sprintf("%s%s", $log, $this->separator);
    }

    /**
     * Setter for directory
     *
     * @param array $directory
     * @return File
     */
    public function setDirectory(array $directory)
    {
        if ($dir = trim($directory)) {
            $this->directory = $directory;
        }
    
        return $this;
    }

    /**
     * Setter for suffix
     *
     * @param string $suffix
     * @return File
     */
    public function setSuffix(string $suffix)
    {
        if ($suffix = trim($suffix)) {
            $this->suffix = $suffix;
        }
    
        return $this;
    }

    /**
     * Setter for archive
     *
     * @param string $archive
     * @return File
     */
    public function setArchive(string $archive)
    {
        if ($archive = trim($archive)) {
            $this->archive = $archive;
        }
    
        return $this;
    }

    /**
     * Setter for filesize
     *
     * @param int $filesize
     * @return File
     */
    public function setFilesize(int $filesize)
    {
        if ($filesize > 0) {
            $this->filesize = $filesize;
        }
    
        return $this;
    }

    /**
     * Setter for separator
     *
     * @param string $separator
     * @return File
     */
    public function setSeparator(string $separator)
    {
        $this->separator = $separator;
    
        return $this;
    }

    /**
     * Setter for debug
     *
     * @param bool $debug
     * @return File
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
    
        return $this;
    }

    /**
     * Setter for debugKey
     *
     * @param string $debugKey
     * @return File
     */
    public function setDebugKey(string $debugKey)
    {
        $this->debugKey = $debugKey;
    
        return $this;
    }
}
