<?php

declare(strict_types=1);

namespace Dof\Framework\Cli\Command;

use Throwable;
use Dof\Framework\Kernel;
use Dof\Framework\GWT;
use Dof\Framework\Doc\Generator as DocGen;
use Dof\Framework\ConfigManager;
use Dof\Framework\DomainManager;
use Dof\Framework\DataModelManager;
use Dof\Framework\EntityManager;
use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;
use Dof\Framework\CommandManager;
use Dof\Framework\WrapinManager;
use Dof\Framework\PortManager;
use Dof\Framework\Web\Kernel as WebKernel;

class Command
{
    /**
     * @CMD(version)
     * @Desc(Get Dof version)
     */
    public function version($console)
    {
        if ($console->getOption('raw')) {
            $console->success((string) get_dof_version_raw(), true);
        }

        $console->success(get_dof_version(), true);
    }

    private function header($console)
    {
        $console->line();
        $console->output($console->render('Dof-PHP Framework', 'LIGHT_BLUE'));
        $console->output('  ');
        $console->output(get_dof_version());
        $console->output('  ');
        $console->output($console->render('ckwongloy@gmail.com', 'DARK_GRAY'));
        $console->line(null, 2);
        $console->exit();
    }

    /**
     * @CMD(help)
     * @Desc(Print Dof help messages)
     */
    public function help($console)
    {
        // TODO
        $this->header($console);
    }

    /**
     * @CMD(php)
     * @Desc(Execute a standalone php script)
     */
    public function php($console)
    {
        $php = $console->getParams()[0] ?? null;
        if (! $php) {
            $console->fail('NoPhpScriptToRun', true);
        }
        if (! is_file($php)) {
            $php = ospath(Kernel::getRoot(), $php);
            if (! is_file($php)) {
                $console->exception('PhpScriptNotExists', ['path' => $php]);
            }
        }

        try {
            require $php;
        } catch (Throwable $e) {
            $console->exception('FailedToExecutePhpScript', [
                'message' => $e->getMessage(),
                'path' => $php
            ]);
        }
    }

    /**
     * @CMD(dof)
     * @Desc(Dof default command)
     */
    public function dof($console)
    {
        if ($console->getOption('help')) {
            return $this->help($console);
        }

        if ($console->getOption('version')) {
            return $this->version($console);
        }

        if ($console->getOptions()->count()) {
            $console->exception(
                'UnSupportOptions',
                array_keys($console->getOptions()->toArray())
            );
        }

        return $this->header($console);
    }

    /**
     * @CMD(web.start)
     * @Desc(Start/Restart web application)
     */
    public function startWeb($console)
    {
        $lock = ospath(Kernel::getRoot(), WebKernel::HALT_FLAG);
        if (! is_file($lock)) {
            $console->success('OK! Application is up and running.', true);
        }

        $res = unlink($lock);
        if ($res === false) {
            $console->fail('Failed!', true);
        }

        $console->success('Success!', true);
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
                    $console->fail('ERROR! Force shutdown failed.', true);
                }
            } else {
                $console->success('OK! Application was shutdown already.', true);
            }
        }

        $message = $console->getOption('message', 'Unknown');
        $since = microftime('T Y-m-d H:i:s');
        $res = file_put_contents($lock, enjson(compact('message', 'since')));
        if (false === $res) {
            $console->fail('Failed!', true);
        }

        $console->success('Success!', true);
    }

    /**
     * @CMD(cmd.list)
     * @Desc(List commands)
     */
    public function listCommand($console)
    {
        $console->line('TODO: display commands list');
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
        $success  = GWT::getSuccess();
        $_success = count($success);
        $failure  = GWT::getFailure();
        $_failure = count($failure);
        $exception  = GWT::getException();
        $_exception = count($exception);
        $end = microtime(true);

        $console->info('-- Time Taken: '.($end-$start).' s');
        $console->info('-- Memory Used: '.format_bytes(memory_get_usage()));
        $console->info('-- Total Test Cases: '.($_success + $_failure + $_exception));
        $console->success('-- Passed Tests: '.$_success);
        $console->fail('-- Failed Tests: '.$_failure);
        if ($_failure > 0) {
            $console->fail(json_pretty($failure));
        }
        $console->warning('-- Exception Exceptions: '.$_exception);
        if ($_exception > 0) {
            $console->warning(json_pretty($exception));
        }
    }

    /**
     * @CMD(docs.build.model)
     * @Desc(Generate data model docs of domains)
     */
    public function buildDocsModel($console)
    {
        $save  = $console->getOption('save', 'tmp/dof-docs-data-model');
        $isabs = $console->getOption('absolute', 0);
        if (! $isabs) {
            $save = ospath(Kernel::getRoot(), $save);
        }

        DocGen::buildModel($console->getOption('ui', 'gitbook'), $save);

        $console->success('Done!', true);
    }

    /**
     * @CMD(docs.build.wrapin)
     * @Desc(Generate wrapin docs of domains)
     */
    public function buildDocsWrapin($console)
    {
        $save  = $console->getOption('save', 'tmp/dof-docs-wrapin');
        $isabs = $console->getOption('absolute', 0);
        if (! $isabs) {
            $save = ospath(Kernel::getRoot(), $save);
        }

        DocGen::buildWrapin($console->getOption('ui', 'gitbook'), $save);

        $console->success('Done!', true);
    }

    /**
     * @CMD(docs.build.http)
     * @Desc(Generate http docs of domains)
     */
    public function buildDocsHttp($console)
    {
        $save  = $console->getOption('save', 'tmp/dof-docs-http');
        $isabs = $console->getOption('absolute', 0);
        if (! $isabs) {
            $save = ospath(Kernel::getRoot(), $save);
        }

        DocGen::buildHttp($console->getOption('ui', 'gitbook'), $save);

        $console->success('Done!', true);
    }

    /**
     * @CMD(docs.build.all)
     * @Desc(Generate all docs of domains)
     */
    public function buildDocsAll($console)
    {
        $save  = $console->getOption('save', 'tmp/dof-docs-all');
        $isabs = $console->getOption('absolute', 0);
        if (! $isabs) {
            $save = ospath(Kernel::getRoot(), $save);
        }

        DocGen::buildAll($console->getOption('ui', 'gitbook'), $save);

        $console->success('Done!', true);
    }

    /**
     * @CMD(docs.build)
     * @Desc(Generate all docs of domains)
     */
    public function buildDocs($console)
    {
        return $this->buildDocsAll($console);
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

        DataModelManager::flush();

        StorageManager::flush();

        RepositoryManager::flush();

        CommandManager::flush();

        WrapinManager::flush();

        PortManager::flush();

        $console->exit();
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

        DataModelManager::compile($domains, true);

        StorageManager::compile($domains, true);

        RepositoryManager::compile($domains, true);

        CommandManager::compile($domains, true);

        WrapinManager::compile($domains, true);

        PortManager::compile($domains, true);

        $console->exit();
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

        PortManager::compile($domains, true);

        $console->exit();
    }

    /**
    * @CMD(compile.port.clear)
    * @Desc(Clear Port compile result)
    */
    public function clearPortComplie($console)
    {
        PortManager::flush();

        $console->exit();
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
