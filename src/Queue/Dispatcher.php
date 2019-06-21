<?php

declare(strict_types=1);

namespace Dof\Framework\Queue;

use Throwable;
use Dof\Framework\Facade\Log;
use Dof\Framework\Cli\Console;
use Dof\Framework\QueueManager;
use Dof\Framework\Queue\Job;

class Dispatcher
{
    private $interval = 3;
    private $timeout = 0;    // 0 means no timeout limit
    private $tryTimes = 0;    // 0 means do not re-execute failed job
    private $tryDelay = -1;    // -1 means do not re-execute failed job
    private $quiet = true;
    private $debug = false;    // Do not fork process as job worker

    /** @var bool: Run queue command in background as daemon process */
    private $daemon = true;    // TODO

    private $console;
    private $queuable;
    private $queue;

    public function looping()
    {
        if ($this->debug) {
            $this->logging('RunQueueDispatcherAsJobWorkerSelf');
        } else {
            if (! extension_loaded('pcntl')) {
                $this->logging('PcntlExtensionNotFound');
                return;
            }
        }

        while (true) {
            if ($this->needRestart()) {
                $result = $this->restart();
                $this->logging('QueueRestarted', compact('result'));

                // Do not return or exit(0) here
                // Because daemon service like supervisord may not restart if process exit with status code 0
                exit(-1);
            }

            $job = $this->dequeue();
            if (! $job) {
                $this->logging('NoJobsNow');
                sleep($this->interval);
                continue;
            }

            if (! ($job instanceof Job)) {
                $this->logging('InvalidJob', compact('job'));
                continue;
            }

            if ($this->debug) {
                Worker::process($job, function ($e) {
                    $this->logging('JobExecuteExceptionInDebugMode', parse_throwable($e));
                });
            } else {
                $worker = Worker::new(function () {
                    $this->logging('UnableToForkChildWorker');
                });
                if (is_null($worker)) {
                    continue;
                }

                // Child process
                if ($worker === 0) {
                    $result = Worker::process($job, function ($e) {
                        $this->logging('JobExecuteException', parse_throwable($e));
                    });
                    exit(0);    // Exit normally as child process
                }

                // Parent process, waiting for child process
                if ($worker > 0) {
                    // Wait until the child process finishes before continuing
                    $stauts = null;
                    pcntl_wait($status);

                    if (pcntl_wifexited($status) !== true) {
                        $this->logging('JobFailedAbnormally', compact('status'));
                        // job failed
                        if (method_exists($job, 'onFailed')) {
                            try {
                                $job->onFailed();
                            } catch (Throwable $e) {
                                $this->logging('JobOnFailedCallbackFailedWhenAbnormally', parse_throwable($e));
                            }
                        }
                    } elseif (($code = pcntl_wexitstatus($status)) !== 0) {
                        $this->logging('JobFailedExitUnexpectedStatusCode', compact('code'));
                        if (method_exists($job, 'onFailed')) {
                            try {
                                $job->onFailed();
                            } catch (Throwable $e) {
                                $this->logging('JobOnFailedCallbackFailedWithBadCode', parse_throwable($e));
                            }
                        }
                    }

                    $this->logging('ProcessedSuccessfully', ['job' => get_class($job)]);
                }

                unset($worker);
            }
        };
    }

    public function logging(string $message, array $context = [])
    {
        $context['__queue'] = $this->queue;

        if ($this->quiet) {
            Log::log('queue-dispatcher', $message, $context);
            return;
        }

        $this->console->info(enjson([microftime('T Y-m-d H:i:s', ' '), $message, $context]));
    }

    public function dequeue()
    {
        return $this->queuable->dequeue(QueueManager::formatQueueName($this->queue, QueueManager::QUEUE_NORMAL));
    }

    public function restart()
    {
        return $this->queuable->restart(QueueManager::formatQueueName($this->queue, QueueManager::QUEUE_RESTART));
    }

    public function needRestart()
    {
        return $this->queuable->needRestart(QueueManager::formatQueueName($this->queue, QueueManager::QUEUE_RESTART));
    }

    /**
     * Setter for console
     *
     * @param Console $console
     * @return Dispatcher
     */
    public function setConsole(Console $console)
    {
        $this->console = $console;
    
        return $this;
    }

    /**
     * Setter for queuable
     *
     * @param Queuable $queuable
     * @return Dispatcher
     */
    public function setQueuable(Queuable $queuable)
    {
        $this->queuable = $queuable;
    
        return $this;
    }

    /**
     * Setter for queue
     *
     * @param string $queue
     * @return Dispatcher
     */
    public function setQueue(string $queue)
    {
        $this->queue = $queue;
    
        return $this;
    }

    /**
     * Setter for daemon
     *
     * @param bool $daemon
     * @return Dispatcher
     */
    public function setDaemon(bool $daemon)
    {
        $this->daemon = $daemon;
    
        return $this;
    }

    /**
     * Setter for quiet
     *
     * @param bool $quiet
     * @return Dispatcher
     */
    public function setQuiet(bool $quiet)
    {
        $this->quiet = $quiet;
    
        return $this;
    }

    /**
     * Setter for debug
     *
     * @param bool $debug
     * @return Dispatcher
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
    
        return $this;
    }

    /**
     * Setter for timeout
     *
     * @param int $timeout
     * @return Dispatcher
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
    
        return $this;
    }

    /**
     * Setter for interval
     *
     * @param int $interval
     * @return Dispatcher
     */
    public function setInterval(int $interval)
    {
        $this->interval = $interval;
    
        return $this;
    }

    /**
     * Setter for tryDelay
     *
     * @param int $tryDelay
     * @return Dispatcher
     */
    public function setTryDelay(int $tryDelay)
    {
        $this->tryDelay = $tryDelay;
    
        return $this;
    }

    /**
     * Setter for tryTimes
     *
     * @param int $tryTimes
     * @return Dispatcher
     */
    public function setTryTimes(int $tryTimes)
    {
        $this->tryTimes = $tryTimes;
    
        return $this;
    }
}
