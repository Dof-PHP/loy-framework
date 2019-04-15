<?php

declare(strict_types=1);

namespace Loy\Framework\Cli\Command;

use Loy\Framework\Kernel;
use Loy\Framework\Web\Kernel as WebKernel;

class Command
{
    /**
     * @CMD(web.start)
     * @Desc(Start/Restart web application)
     */
    public function startWeb($console)
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
     * @CMD(web.stop)
     * @Desc(Stop/Halt web application)
     */
    public function stopWeb($console)
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
     * @CMD(cmd.list)
     * @Desc(List commands)
     */
    public function listCommand($console)
    {
        $console->output('TODO: display commands list');
    }

    /**
     * @CMD(test.domain)
     * @Desc(Run domain tests)
     */
    public function testDomain($console)
    {
    }

    /**
     * @CMD(test.framework)
     * @Desc(Run framework tests)
     */
    public function testFramework($console)
    {
        $tests = ospath(__DIR__.'/../../..', ['tests']);
        $start = microtime(true);
        run_gwt_tests($tests, [realpath(ospath($tests, 'run.php')) => true]);
        $success  = \Loy\Framework\GWT::getSuccess();
        $_success = count($success);
        $failure  = \Loy\Framework\GWT::getFailure();
        $_failure = count($failure);
        $exception  = \Loy\Framework\GWT::getException();
        $_exception = count($exception);
        $end = microtime(true);

        echo '-- Time Taken: ', $end-$start, ' s.', PHP_EOL;
        echo '-- Memory Used: ', format_bytes(memory_get_usage()), PHP_EOL;
        echo '-- Total Test Cases: ', $_success + $_failure + $_exception, PHP_EOL;
        echo "-- \033[0;31mFailed Tests: {$_failure}\033[0m", PHP_EOL;
        echo "-- \033[1;33mTesting Exceptions: {$_exception}\033[0m", PHP_EOL;
        echo "-- \033[0;32mPassed Tests: {$_success}\033[0m", PHP_EOL;

        echo "\033[1;33mException Tests => \033[0m";
        print_r($exception);
        echo "\033[0;31mFailed Tests => \033[0m";
        print_r($failure);

        exit;
    }

    /**
     * @CMD(docs.http.port)
     * @Desc(Generate HTTP ports docs of domains)
     */
    public function genDomainHttpPortDocs($console)
    {
    }

    /**
     * @CMD(compile.framework)
     * @Desc(Compiles classes and annotations of framework)
     */
    public function complieFramework()
    {
    }

    /**
     * @CMD(compile.domain)
     * @Desc(Compiles classes and annotations of domains)
     * @Option(domain)
     */
    public function complieDomain($console)
    {
    }

    /**
     * @CMD(config.get.framework)
     * @Desc(Get framework configs)
     */
    public function getFrameworkConfig()
    {
    }

    /**
     * CMD(config.get.domain)
     * @Desc(Get domain's configs)
     */
    public function getDomainConfig($console)
    {
    }
}
