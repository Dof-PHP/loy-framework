<?php

declare(strict_types=1);

namespace Dof\Framework\Cli\Command;

use Dof\Framework\Kernel;
use Dof\Framework\Doc\Generator as DocGen;
use Dof\Framework\ConfigManager;
use Dof\Framework\DomainManager;
use Dof\Framework\EntityManager;
use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;
use Dof\Framework\CommandManager;
use Dof\Framework\RouteManager;
use Dof\Framework\Web\Kernel as WebKernel;

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
            $force = strtolower((string) $console->getOption('force', '0'));
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

        $message = $console->getOption('message', 'Unknown');
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
        run_gwt_tests($tests, [
            ospath($tests, 'run.php'),
            ospath($tests, 'data'),
        ]);
        $success  = \Dof\Framework\GWT::getSuccess();
        $_success = count($success);
        $failure  = \Dof\Framework\GWT::getFailure();
        $_failure = count($failure);
        $exception  = \Dof\Framework\GWT::getException();
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
     * @CMD(docs.http.build)
     * @Desc(Generate HTTP ports docs of domains)
     */
    public function buildHttpDocs($console)
    {
        $save  = $console->getOption('save', 'tmp/http-docs');
        $isabs = $console->getOption('absolute', 0);
        if (! $isabs) {
            $save = ospath(Kernel::getRoot(), $save);
        }

        DocGen::buildHttp($console->getOption('ui', 'gitbook'), $save);

        exit;
    }

    /**
     * @CMD(clear.compile)
     * @Desc(Clear all classes compile cache)
     */
    public function clearCompile($console)
    {
        ConfigManager::flush();

        DomainManager::flush();

        EntityManager::flush();

        StorageManager::flush();

        RepositoryManager::flush();

        CommandManager::flush();

        RouteManager::flush();
    }

    /**
     * @CMD(compile)
     * @Desc(Compile all classes)
     */
    public function compile($console)
    {
        ConfigManager::compileDefault(Kernel::getRoot(), true);

        DomainManager::compile(Kernel::getRoot(), true);

        ConfigManager::compileDomains(DomainManager::getMetas(), true);

        $domains = DomainManager::getDirs();

        EntityManager::compile($domains, true);

        StorageManager::compile($domains, true);

        RepositoryManager::compile($domains, true);

        CommandManager::compile($domains, true);

        RouteManager::compile($domains, true);
    }

    /**
    * @CMD(compile.port)
    * @Desc(Compiles Port classes and it's annotations)
    */
    public function compliePort($console)
    {
        ConfigManager::compileDefault(Kernel::getRoot(), true);

        DomainManager::compile(Kernel::getRoot(), true);

        ConfigManager::compileDomains(DomainManager::getMetas(), true);

        $domains = DomainManager::getDirs();

        RouteManager::compile($domains, true);
    }

    /**
    * @CMD(compile.port.clear)
    * @Desc(Clear Port compile result)
    */
    public function clearPortComplie($console)
    {
        RouteManager::flush();
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
