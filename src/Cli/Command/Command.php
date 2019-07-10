<?php

declare(strict_types=1);

namespace Dof\Framework\Cli\Command;

use Throwable;
use Dof\Framework\Kernel;
use Dof\Framework\GWT;
use Dof\Framework\Event;
use Dof\Framework\Listener;
use Dof\Framework\Reflector;
use Dof\Framework\QueueManager;
use Dof\Framework\Queue\Dispatcher as QueueDispatcher;
use Dof\Framework\Doc\Generator as DocGen;
use Dof\Framework\DDD\Storage;
use Dof\Framework\DDD\ORMStorage;
use Dof\Framework\Storage\StorageSchema;
use Dof\Framework\ConfigManager;
use Dof\Framework\DomainManager;
use Dof\Framework\ModelManager;
use Dof\Framework\EntityManager;
use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;
use Dof\Framework\CommandManager;
use Dof\Framework\WrapinManager;
use Dof\Framework\PortManager;
use Dof\Framework\EventManager;
use Dof\Framework\Web\Kernel as WebKernel;

class Command
{
    /**
     * @CMD(version)
     * @Desc(Get Dof version)
     * @Option(raw){notes=Get the raw version count of framework}
     */
    public function version($console)
    {
        if ($console->hasOption('raw')) {
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
    }

    /**
     * @CMD(help)
     * @Desc(Print help messages of Dof commands)
     * @Argv(1){notes=The command name used to print help message}
     */
    public function help($console)
    {
        $console->line();

        $cmd = $console->getParams()[0] ?? null;
        if ($cmd) {
            $cmd = strtolower($cmd);
            $attr = CommandManager::get($cmd);
            if (! $attr) {
                $console->exception('CommandToHelpNotExist', [$cmd]);
            }

            $console->line($console->render("Usage: php dof {$cmd} [--options ...] [[--] parameters ...]", 'YELLOW'), 2);
            extract($attr);

            $console->line(
                $console->render('* Command: ', $console::TITLE_COLOR)
                .$console->render($cmd, $console::SUCCESS_COLOR)
            );
            $console->line();
            $console->info('* Comment: '.$comment);
            $console->line();
            $console->title('* Options: ');
            foreach ($options as $option => $_attr) {
                extract($_attr);
                $default = $DEFAULT ?: 'NULL';
                $console->line(
                    $console->render("\t--{$option}\t", $console::SUCCESS_COLOR)
                    .$console->render($NOTES, $console::INFO_COLOR)
                    .$console->render("\t(Default: {$default})", 'CYAN')
                );
            }
            $console->title('* Arguments: ');
            foreach ($argvs as $order => $desc) {
                $console->line(
                    $console->render("\t#{$order}\t", $console::SUCCESS_COLOR)
                    .$console->render($desc, $console::INFO_COLOR)
                );
            }

            $console->line();
            $console->info('* Class: '.$class);
            $console->info('* Method: '.$method);

            $reflector = Reflector::getClassMethod($class, $method);
            $console->info('* File: '.($reflector['file'] ?? get_file_of_namespace($class)));
            $console->info('* Line: '.($reflector['line'] ?? -1));
        } else {
            $console->line($console->render('Usage: php dof {COMMAND} [--options ...] [[--] parameters ...]', 'YELLOW'));
        }

        $console->line();
    }

    /**
     * @CMD(php)
     * @Desc(Execute a standalone php script)
     * @Argv(1){notes=The php script file to run}
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
            ], $e);
        }
    }

    /**
     * @CMD(root)
     * @Desc(Get Dof framework root)
     * @Option(project){notes=Get project root instead}
     */
    public function getRoot($console)
    {
        if ($console->hasOption('project')) {
            return $console->success(Kernel::getRoot(), true);
        }

        return $console->success(Kernel::root(), true);
    }

    /**
     * @CMD(cmd.domain)
     * @Desc(List domain commands in current project)
     */
    public function listDomainCMD($console)
    {
        $commands = CommandManager::getDomain();
        ksort($commands);
        
        $console->line();
        foreach ($commands as $domain => $cmds) {
            $title = ConfigManager::getDomainDomainByKey($domain, 'title', strtoupper($domain));
            $path = str_replace(Kernel::getRoot().'/', '', DomainManager::getByKey($domain));
            $console->title(join(' | ', [$domain, $title, $path]));

            foreach ($cmds as $cmd => $idx) {
                $attr = CommandManager::get($cmd);
                extract($attr);

                $console->line(
                    $console->render($cmd, $console::SUCCESS_COLOR)
                    ."\t\t"
                    .$console->render($comment, $console::INFO_COLOR)
                );

                if (false !== next($commands)) {
                    if (! ci_equal(mb_strcut($cmd, 0, 1), mb_strcut(key($commands), 0, 1))) {
                        $console->line();
                    }
                }
            }
        }
        $console->line();
    }

    /**
     * @CMD(cmd.default)
     * @Desc(List default commands builtin Dof-PHP framework)
     */
    public function listDefaultCMD($console)
    {
        $commands = CommandManager::getDefault();
        ksort($commands);
        
        $console->line();
        foreach ($commands as $cmd => $idx) {
            $attr = CommandManager::get($cmd);
            extract($attr);

            $console->line(
                $console->render($cmd, $console::SUCCESS_COLOR)
                ."\t\t"
                .$console->render($comment, $console::INFO_COLOR)
            );

            if (false !== next($commands)) {
                if (! ci_equal(mb_strcut($cmd, 0, 1), mb_strcut(key($commands), 0, 1))) {
                    $console->line();
                }
            }
        }
        $console->line();
    }

    /**
     * @CMD(cmd)
     * @Desc(List all commands in current Dof-PHP project)
     */
    public function listCMD($console)
    {
        return $this->listAllCMD($console);
    }

    /**
     * @CMD(cmd.all)
     * @Desc(List all commands in current Dof-PHP project)
     */
    public function listAllCMD($console)
    {
        $commands = CommandManager::getCommands();
        ksort($commands);

        $console->line();
        foreach ($commands as $cmd => $attr) {
            extract($attr);
            $console->line(
                $console->render($cmd, $console::SUCCESS_COLOR)
                ."\t\t"
                .$console->render($comment, $console::INFO_COLOR)
            );

            if (false !== next($commands)) {
                if (! ci_equal(mb_strcut($cmd, 0, 1), mb_strcut(key($commands), 0, 1))) {
                    $console->line();
                }
            }
        }

        $console->line();
    }

    /**
     * @CMD(dof)
     * @Desc(Dof default command)
     * @Option(help){notes=Print dof cli help message}
     * @Option(version){notes=Get dof framework version string}
     * @Option(root){notes=Get dof framework root}
     */
    public function dof($console)
    {
        if ($console->hasOption('help')) {
            return $this->help($console);
        }

        if ($console->hasOption('version')) {
            return $this->version($console);
        }

        if ($console->hasOption('root')) {
            return $this->getRoot($console);
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
     * @Option(force){notes=Whether force stop web application even if it's stopped already}
     * @Option(message){notes=The application shutdown message text displays to visitors}
     */
    public function stopWeb($console)
    {
        $lock = ospath(Kernel::getRoot(), WebKernel::HALT_FLAG);
        if (is_file($lock)) {
            if ($console->hasOption('force')) {
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
     * @CMD(test)
     * @Desc(Run all domain tests)
     */
    public function test($console)
    {
        $domains = DomainManager::getDirs();
        foreach ($domains as $domain) {
            $tests = ospath($domain, 'tests');
            if (! is_dir($tests)) {
                continue;
            }

            $console->title("---- Domain Root: {$domain} ----");
            $this->__test($console, $tests);
            $console->line();
        }
    }

    /**
     * @CMD(test.domain)
     * @Desc(Run domain tests)
     * @Argv(1){notes=The domain name to run test cases}
     */
    public function testDomain($console)
    {
        $domain = $console->first('domain');
        if (! $domain) {
            $console->fail('MissingDomainName', true);
        }

        $_domain = DomainManager::getByKey($domain);
        if (! $_domain) {
            $console->exception('DomainNotFound', compact('domain'));
        }

        $this->__test($console, ospath($_domain, 'tests'));
    }

    /**
     * @CMD(test.dir)
     * @Desc(Run Dof GWT test cases by directory)
     * @Option(path){notes=The directory to run test cases}
     */
    public function testDir($console)
    {
        if (! $console->hasOption('path')) {
            $console->fail('MissingTestsPath', true);
        }

        $path = $console->getOption('path');
        if (! is_dir($path)) {
            $path = ospath(Kernel::getRoot(), $path);
            if (! is_dir($path)) {
                $console->exception('TestsPathNotExists', compact('path'));
            }
        }

        $this->__test($console, $path);
    }

    private function __test($console, string $dir, array $excludes = [])
    {
        GWT::reset();
        $start = microtime(true);
        GWT::run($dir, $excludes);
        $end = microtime(true);
        $success  = GWT::getSuccess();
        $_success = count($success);
        $failure  = GWT::getFailure();
        $_failure = count($failure);
        $exception  = GWT::getException();
        $_exception = count($exception);

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
     * @CMD(test.framework)
     * @Desc(Run framework tests)
     */
    public function testFramework($console)
    {
        $tests = ospath(__DIR__.'/../../..', ['tests']);
        $this->__test($console, $tests, [
            ospath($tests, 'run.php'),
            ospath($tests, 'data'),
        ]);
    }

    /**
     * @CMD(docs.build.model)
     * @Desc(Generate data model docs of domains)
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocsModel($console)
    {
        $save  = $console->getOption('save', ospath(Kernel::RUNTIME, DocGen::DOC_MODEL));
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
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocsWrapin($console)
    {
        $save  = $console->getOption('save', ospath(Kernel::RUNTIME, DocGen::DOC_WRAPIN));
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
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocsHttp($console)
    {
        $save  = $console->getOption('save', ospath(Kernel::RUNTIME, DocGen::DOC_HTTP));
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
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocsAll($console)
    {
        $save  = $console->getOption('save', ospath(Kernel::RUNTIME, DocGen::DOC_ALL));
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
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocs($console)
    {
        return $this->buildDocsAll($console);
    }

    private function buildPortCategories(array $categories)
    {
        $data = [];

        foreach ($categories as $_category => $category) {
            $children = [];
            $list = $category['list'] ?? [];
            if ($list) {
                foreach ($list as $port) {
                    foreach ($port['verbs'] ?? [] as $verb) {
                        $children[] = [
                            'title' => $port['title'] ?? null,
                            'route' => $port['route'] ?? null,
                            'verb' => $verb,
                        ];
                    }
                }
            }

            $data[] = [
                'title' => $category['title'] ?? $_category,
                'categories' => $this->buildPortCategories($category['group'] ?? []),
                'apis' => $children,
            ];
        }

        return $data;
    }

    /**
     * @CMD(port.dump)
     * @Desc(Dump all ports data as a format)
     * @Option(format){notes=Dump format: JSON/XML/ARRAY&default=JSON}
     * @Option(save){notes=File path when has save option}
     */
    public function dumpPort($console)
    {
        $save = $console->getOption('save', null, true);

        $data = [];

        $docs = PortManager::getDocs();
        foreach ($docs as $_version => $version) {
            $domains = $version['main'] ?? [];
            if (! $domains) {
                continue;
            }

            $category = [];
            $category['title'] = $_version;
            $category['categories'] = $this->buildPortCategories($domains);

            $data['categories'][] = $category;
        }

        $format = $console->getOption('format', 'json');
        if (! ciin($format, ['json', 'xml', 'array'])) {
            $format = 'json';
        }

        switch (strtolower($format)) {
            case 'array':
                array2code($data, $save);
                break;
            case 'xml':
                save($save, enxml($data));
                break;
            case 'json':
            default:
                save($save, enjson($data));
                break;
        }

        $console->success('Done!');
    }

    /**
     * @CMD(compile.clear)
     * @Desc(Clear all classes compile cache)
     */
    public function clearCompile($console)
    {
        ConfigManager::flush();

        DomainManager::flush();

        EntityManager::flush();

        ModelManager::flush();

        StorageManager::flush();

        RepositoryManager::flush();

        CommandManager::flush();

        WrapinManager::flush();

        PortManager::flush();

        EventManager::flush();

        $console->success('Done!');
    }

    /**
     * @CMD(compile)
     * @Desc(Compile all classes)
     */
    public function compile($console)
    {
        try {
            ConfigManager::compileDefault(Kernel::getRoot(), true);

            DomainManager::compile(Kernel::getRoot(), true);

            ConfigManager::compileDomains(DomainManager::getMetas(), true);

            $domains = DomainManager::getDirs();

            EntityManager::compile($domains, true);

            ModelManager::compile($domains, true);

            StorageManager::compile($domains, true);

            RepositoryManager::compile($domains, true);

            CommandManager::compile($domains, true);

            WrapinManager::compile($domains, true);

            PortManager::compile($domains, true);

            EventManager::compile($domains, true);

            $console->success('Done!', true);
        } catch (Throwable $e) {
            $console->exception('CompileFailed', [], $e);
        }
    }

    /**
     * @CMD(orm.init)
     * @Desc(Init an ORM storage from its annotations to connected driver schema)
     * @Option(orm){notes=The single orm class filepath or namespace to init}
     * @Option(force){notes=Whether execute the dangerous operations like drop/delete&default=false}
     * @Option(dump){notes=Dump the sqls will be executed rather than execute them directly&default=false}
     */
    public function initORM($console)
    {
        $orm = $console->getOption('orm');
        if (! $orm) {
            $console->exception('MissingORMToInit');
        }

        $class = null;
        if (is_file($orm)) {
            $class = get_namespace_of_file($orm, true);
        } elseif (class_exists($orm)) {
            $class = $orm;
        }

        if ((! $class) || (! is_subclass_of($class, ORMStorage::class))) {
            $console->exception('InvalidORMClass', compact('orm', 'class'));
        }

        $force = $console->hasOption('force');
        $dump  = $console->hasOption('dump');

        $res = StorageSchema::init($class, $force, $dump);
        if ($dump) {
            foreach ($res as $sql) {
                $console->line($sql, 2);
            }
        } else {
            $_force = $force ? ' (FORCE) ' : '';
            $console->render("Initializing{$_force}... {$class} ... ", $console::INFO_COLOR, true);
            $res ? $console->success('OK') : $console->fail('FAILED', true);
        }
    }

    /**
    * @CMD(orm.sync)
    * @Desc(Sync from storage ORM annotations to storage driver schema)
    * @Option(single){notes=The single file name to sync at once}
    * @Option(force){notes=Whether execute the dangerous operations like drop/delete&default=false}
    * @Option(domain){notes=The domain name used to sync orm classes schema}
    * @Option(dump){notes=Dump the sqls will be executed rather than execute them directly&default=false}
    * @Option(skip){notes=The orm class files to exclude, using `,` to separate}
    */
    public function syncORM($console)
    {
        $params = $console->getParams();
        $options = $console->getOptions();
        $excludes = array_trim_from_string($console->getOption('skip', ''), ',');
        array_walk($excludes, function (&$skip) {
            $class = get_namespace_of_file($skip, true);
            $skip = $class ? $class : '';
        });
        array_filter($excludes);

        $syncSingle = function ($single) use ($console, $excludes) {
            $storage = null;
            if (class_exists($single)) {
                if (! is_subclass_of($single, ORMStorage::class)) {
                    $console->exception('SingleClassNotAnORMStorage', compact('single'));
                }
                $storage = $single;
            } elseif (is_file($single)) {
                $class = get_namespace_of_file($single, true);
                if ((! $class) || (! is_subclass_of($class, Storage::class))) {
                    $console->exception('InvalidSingleStorageFile', compact('single', 'class'));
                }
                $storage = $class;
            }
            if (! $storage) {
                $console->exception('InvalidStorageSingle', compact('single', 'storage'));
            }

            $force = $console->hasOption('force');
            $dump = $console->hasOption('dump');

            if (in_array($storage, $excludes)) {
                if ($dump) {
                    return $console->line("-- SKIP: {$storage}");
                }

                return $console->line(
                    $console->render('SKIPPED: ', 'BLUE')
                    .$console->render($storage, $console::INFO_COLOR)
                );
            }

            $res = StorageSchema::sync($storage, $force, $dump);
            if ($dump) {
                foreach ($res as $sql) {
                    $console->line($sql, 2);
                }
            } else {
                $_force = $force ? ' (FORCE) ' : '';
                $console->render("Syncing{$_force}... {$storage} ... ", $console::INFO_COLOR, true);
                $res ? $console->success('OK') : $console->fail('FAILED', true);
            }
        };

        if ($console->hasOption('single')) {
            $single = $console->getOption('single');
            if (! $single) {
                $console->exception('MissingSingleTarget');
            }

            $syncSingle($single);
        } elseif ($console->hasOption('domain')) {
            $domain = $console->getOption('domain');
            if (! $domain) {
                $console->exception('MissingStorageDomainToInit');
            }

            $orms = DomainManager::getNamespaces(function ($key, $ns) use ($domain) {
                return true
                    && ci_equal($key, $domain)
                    && is_subclass_of($ns, Storage::class)
                    && ci_equal(mb_substr($ns, -3, 3), 'ORM');
            });

            foreach ($orms as $orm) {
                $syncSingle($orm);
            }
        } elseif ($params) {
            foreach ($params as $single) {
                $syncSingle($single);
            }
        } else {
            $orms = DomainManager::getNamespaces(function ($key, $ns) {
                return true
                    && is_subclass_of($ns, Storage::class)
                    && ci_equal(mb_substr($ns, -3, 3), 'ORM');
            });

            foreach ($orms as $orm) {
                $syncSingle($orm);
            }
        }
    }

    /**
     * @CMD(config.get.global)
     * @Desc(Get global configs acorss domains)
     * @Argv(1){notes=The config key}
     */
    public function getGlobalConfig($console)
    {
        $key = $console->getParams()[0] ?? null;

        $console->success(json_pretty(ConfigManager::getDefault($key)));
    }

    /**
     * @CMD(config.get.framework)
     * @Desc(Get global framework configs)
     * @Argv(1){notes=The config key}
     */
    public function getFrameworkConfig($console)
    {
        $key = $console->getParams()[0] ?? null;

        $console->success(json_pretty(ConfigManager::getFramework($key)));
    }

    /**
     * @CMD(config.get.domain)
     * @Desc(Get domain's configs)
     * @Option(domain){notes=The domain name}
     * @Argv(1){notes=The config key}
     */
    public function getDomainConfig($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->success(json_pretty(ConfigManager::getDomains()), true);
        }

        if (! DomainManager::getByKey($domain)) {
            $console->exception('DomainNotExists', compact($domain));
        }

        $key = $console->getParams()[0] ?? null;

        $console->success(json_pretty(ConfigManager::getDomainByKey($domain, $key)));
    }

    /**
     * @CMD(entity.add)
     * @Desc(Add an entity class in a domain)
     * @Option(domain){notes=Domain name of entity to be created}
     * @Option(entity){notes=Name of entity to be created}
     * @Option(force){notes=Whether force recreate entity when given entity name exists}
     * @Option(withts){notes=Whether the entity to be created has timestamp properties&default=true}
     */
    public function addEntity($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('entity');
        if (! $name) {
            $console->exception('MissingEntityName');
        }
        $class = ospath($path, EntityManager::ENTITY_DIR, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('EntityAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'entity.tpl');
        if (! is_file($template)) {
            $console->exception('EntityClassTemplateNotExist', [$template]);
        }

        $parent = $console->getOption('withts', true) ? 'EntityWithTS' : 'Entity';

        $entity = file_get_contents($template);
        $entity = str_replace('__DOMAIN__', $domain, $entity);
        $entity = str_replace('__NAMESPACE__', path2ns($name), $entity);
        $entity = str_replace('__PARENT__', $parent, $entity);
        $entity = str_replace('__NAME__', basename($name), $entity);

        save($class, $entity);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created Entity: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(model.add)
     * @Desc(Add an data model class in a domain)
     * @Option(domain){notes=Domain name of model to be created}
     * @Option(model){notes=Name of data model to be created}
     * @Option(force){notes=Whether force recreate model when given model name exists}
     */
    public function addModel($console)
    {
        return $this->addDM($console);
    }

    /**
     * @CMD(dm.add)
     * @Desc(Add an data model class in a domain)
     * @Option(domain){notes=Domain name of model to be created}
     * @Option(model){notes=Name of data model to be created}
     * @Option(force){notes=Whether force recreate model when given model name exists}
     */
    public function addDM($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('model');
        if (! $name) {
            $console->exception('MissingModelName');
        }
        $class = ospath($path, ModelManager::MODEL_DIR, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('ModelAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'model.tpl');
        if (! is_file($template)) {
            $console->exception('ModelClassTemplateNotExist', [$template]);
        }

        $model = file_get_contents($template);
        $model = str_replace('__DOMAIN__', $domain, $model);
        $model = str_replace('__NAMESPACE__', path2ns($name), $model);
        $model = str_replace('__NAME__', basename($name), $model);

        save($class, $model);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created Model: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(port.add)
     * @Desc(Add a port class in a domain)
     * @Option(domain){notes=Domain name of port to be created}
     * @Option(port){notes=Name of port to be created}
     * @Option(force){notes=Whether force recreate port when given port name exists}
     * @Option(crud){notes=Whether add crud port methods into port&default=false}
     * @Option(autonomy){notes=Whether create an autonomy port&default=false}
     */
    public function addPort($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('port');
        if (! $name) {
            $console->exception('MissingPortName');
        }
        $class = ospath($path, PortManager::PORT_DIR, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('PortAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        if ($console->hasOption('autonomy')) {
            $tpl = 'port-autonomy.tpl';
        } elseif ($console->hasOption('crud')) {
            $tpl = 'port-crud.tpl';
        } else {
            $tpl = 'port-basic.tpl';
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('PortClassTemplateNotExist', [$template]);
        }

        $port = file_get_contents($template);
        $port = str_replace('__DOMAIN__', $domain, $port);
        $port = str_replace('__NAMESPACE__', path2ns($name), $port);
        $port = str_replace('__NAME__', basename($name), $port);

        save($class, $port);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created Port: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(pipe.add)
     * @Desc(Add a pipe class in a domain)
     * @Option(domain){notes=Domain name of pipe to be created}
     * @Option(pipe){notes=Name of pipe to be created}
     * @Option(force){notes=Whether force recreate pipe when given pipe name exists}
     */
    public function addPipe($console)
    {
        $domain = $console->getOption('domain', null, true);
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('pipe', null, true);

        $class = ospath($path, PortManager::PIPE_DIR, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('PipeAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'pipe.tpl');
        if (! is_file($template)) {
            $console->exception('PipeClassTemplateNotExist', [$template]);
        }

        $pipe = file_get_contents($template);
        $pipe = str_replace('__DOMAIN__', $domain, $pipe);
        $pipe = str_replace('__NAMESPACE__', path2ns($name), $pipe);
        $pipe = str_replace('__NAME__', basename($name), $pipe);

        save($class, $pipe);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created Pipe: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(storage.add.orm)
     * @Desc(Add an orm storage class in a domain)
     * @Option(domain){notes=Domain name of orm storage to be created}
     * @Option(storage){notes=Name of orm storage to be created}
     * @Option(force){notes=Whether force recreate orm storage when given orm storage name exists}
     * @Option(withts){notes=Whether orm storage has timestamps&default=true}
     * @Option(withtssd){notes=Whether orm storage has timestamps and is soft deleted&default=false}
     * @Option(withsd){notes=Whether orm storage is soft deleted&default=false}
     * @Option(impl){notes=Whether orm storage implements a repository&default=false}
     * @Option(logging){notes=Whether the orm storage is a logging storage&default=false}
     */
    public function addORMStorage($console)
    {
        $domain = $console->getOption('domain', null, true);
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('storage', null, true);
        $class = ospath($path, StorageManager::STORAGE_DIR, "{$name}ORM.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('StorageAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }
        if ($console->hasOption('logging')) {
            $tpl = 'storage-orm-logging.tpl';
        } else {
            $tpl = $console->getOption('impl', false)
                ? 'storage-orm-impl.tpl'
                : 'storage-orm.tpl';
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('ORMStorageClassTemplateNotExist', [$template]);
        }

        $storage = $console->getOption('withts', false) ? 'ORMStorageWithTS' : 'ORMStorage';
        $storage = $console->getOption('withtssd', false) ? 'ORMStorageWithTSSD' : $storage;
        $storage = $console->getOption('withsd', false) ? 'ORMStorageWithSD' : $storage;

        $orm = file_get_contents($template);
        $orm = str_replace('__DOMAIN__', $domain, $orm);
        $orm = str_replace('__NAMESPACE__', path2ns($name), $orm);
        $orm = str_replace('__NAME__', basename($name), $orm);
        $orm = str_replace('__STORAGE__', $storage, $orm);

        save($class, $orm);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created ORM Storage: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(storage.add.kv)
     * @Desc(Add an kv storage class in a domain)
     * @Option(domain){notes=Domain name of kv storage to be created}
     * @Option(storage){notes=Name of kv storage to be created}
     * @Option(force){notes=Whether force recreate kv storage when given kv storage name exists}
     * @Option(impl){notes=Whether kv storage implements a repository&default=false}
     */
    public function addKVStorage($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('storage');
        if (! $name) {
            $console->exception('MissingKVStorageName');
        }
        $class = ospath($path, StorageManager::STORAGE_DIR, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('KVStorageAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $tpl = $console->getOption('impl', false) ? 'storage-kv-impl.tpl' : 'storage-kv.tpl';
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('KVStorageClassTemplateNotExist', [$template]);
        }

        $kv = file_get_contents($template);
        $kv = str_replace('__DOMAIN__', $domain, $kv);
        $kv = str_replace('__NAMESPACE__', path2ns($name), $kv);
        $kv = str_replace('__NAME__', basename($name), $kv);

        save($class, $kv);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created KV Storage: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(repo.add)
     * @Desc(Add a repository interface in a domain)
     * @Option(domain){notes=Domain name of repository to be created}
     * @Option(repo){notes=Name of repository to be created}
     * @Option(force){notes=Whether force recreate repository when given repository name exists}
     * @Option(type){notes=Repository type: Entity/ORM | Model/KV | Logging&default=Entity/ORM}
     * @Option(storage){notes=Storage path relative to storage base}
     * @Option(entity){notes=Entity path relative to entity base}
     * @Option(model){notes=Model path relative to model base}
     */
    public function addRepository($console)
    {
        $domain = $console->getOption('domain', null, true);
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('repo', null, true);
        $class = ospath($path, RepositoryManager::REPOSITORY_DIR, "{$name}Repository.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('StorageAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $type = $console->getOption('type', 'entity');
        if ($isLogging = ci_equal($type, 'logging')) {
            $tpl = 'repository-logging.tpl';
        } elseif ($isEntity = ciin($type, ['entity', 'orm'])) {
            $tpl = 'repository-entity.tpl';
        } elseif ($isModel = ciin($type, ['model', 'kv'])) {
            $tpl = 'repository-model.tpl';
        } else {
            $console->exception('InvalidType', compact('type'));
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('RepositoryInterfaceTemplateNotExist', [$template]);
        }

        $storage = $name = basename($name);
        if ($_storage = $console->getOption('storage')) {
            $storage = path2ns($_storage, true);
        }
        if ($isLogging || ($isEntity ?? false)) {
            $storage .= 'ORM';
        }

        $repo = file_get_contents($template);
        $repo = str_replace('__DOMAIN__', $domain, $repo);
        $repo = str_replace('__NAMESPACE__', path2ns($name), $repo);
        $repo = str_replace('__NAME__', $name, $repo);
        $repo = str_replace('__STORAGE__', $storage, $repo);

        if ($isEntity ?? false) {
            $entity = $console->getOption('entity', $name);
            $repo = str_replace('__ENTITY__', path2ns($entity, true), $repo);
        } elseif ($isModel ?? false) {
            $model = $console->getOption('model', $name);
            $repo = str_replace('__MODEL__', path2ns($model, true), $repo);
        }

        save($class, $repo);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Repository ({$type}): ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(service.add)
     * @Desc(Add a service class in a domain)
     * @Option(domain){notes=Domain name of service to be created}
     * @Option(service){notes=Name of service to be created}
     * @Option(force){notes=Whether force recreate service when given service name exists}
     * @Option(entity){notes=Entity name used for CRUD template}
     * @Option(crud){notes=CRUD template type, one of create/delete/update/show/list}
     */
    public function addService($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('service');
        if (! $name) {
            $console->exception('MissingServiceName');
        }
        $class = ospath($path, Kernel::SERVICE, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('ServiceAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $tpl = 'service-basic.tpl';
        if ($console->hasOption('crud')) {
            $crud = strtolower(strval($console->getOption('crud')));
            $types = ['create', 'delete', 'update', 'show', 'list'];
            if ((! $crud) || (! in_array($crud, $types))) {
                $console->exception('InvalidCRUDType', compact('crud', 'types'));
            }
            $tpl = "service-crud-{$crud}.tpl";
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('ServiceClassTemplateNotExist', [$template]);
        }

        $service = file_get_contents($template);
        $service = str_replace('__DOMAIN__', $domain, $service);
        $service = str_replace('__NAMESPACE__', path2ns($name), $service);
        $service = str_replace('__NAME__', basename($name), $service);

        if ($entity = $console->getOption('entity')) {
            $service = str_replace('__ENTITY__', path2ns($entity, true), $service);
        }

        save($class, $service);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Service: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(asm.add)
     * @Desc(Add Assembler in a domain)
     * @Option(domain){notes=Domain name of assembler to be created}
     * @Option(asm){notes=Assembler name}
     * @Option(force){notes=Whether force recreate assembler when given assembler name exists}
     */
    public function addAssembler($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $name = $console->getOption('asm');
        if (! $name) {
            $console->exception('MissingAssemblerName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $class = ospath($path, Kernel::ASSEMBLER, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('AssemblerAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'assembler.tpl');
        if (! is_file($template)) {
            $console->exception('AssemblerClassTemplateNotExist', [$template]);
        }

        $assembler = file_get_contents($template);
        $assembler = str_replace('__DOMAIN__', $domain, $assembler);
        $assembler = str_replace('__NAMESPACE__', path2ns($name), $assembler);
        $assembler = str_replace('__NAME__', basename($name), $assembler);

        save($class, $assembler);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Assembler: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(crud)
     * @Desc(Generate all CRUD operations related classes based on a resource/entity name)
     * @Option(domain){notes=Domain name of classes to be created}
     * @Option(entity){notes=Entity directory path}
     * @Option(storage){notes=ORM storage directory path}
     * @Option(repo){notes=Repository directory path}
     * @Option(port){notes=Port directory path}
     * @Option(noport){notes=Do not create port class&default=false}
     * @Option(service){notes=Service directory path}
     * @Option(asm){notes=Assembler directory path}
     * @Option(noasm){notes=Do not create assembler class&default=false}
     * @Option(withts){notes=Entity and ORM storage to be created need timestamps or not&default=true}
     * @Option(nodelete){notes=Do not create delete service&default=false}
     * @Option(noshow){notes=Do not create show service&default=false}
     * @Option(noupdate){notes=Do not create update service&default=false}
     * @Option(nolist){notes=Do not create list service&default=false}
     */
    public function crud($console)
    {
        $entity = $console->getOption('entity');
        if (! $entity) {
            $console->exception('MissingEntityName');
        }
        $this->addEntity($console);
        $_entity = basename($entity);

        $storage = $console->hasOption('storage')
            ? join('/', [$console->getOption('storage'), $_entity])
            : $_entity;

        $repo = $console->hasOption('repo')
            ? join('/', [$console->getOption('repo'), $_entity])
            : $_entity;
        $console->setOption('storage', $storage)->setOption('repo', $repo);
        $this->addRepository($console);

        $console->setOption('impl', true)->setOption('storage', $storage);
        $this->addORMStorage($console);

        if (! $console->hasOption('noport')) {
            $port = $console->hasOption('port')
                ? join('/', [$console->getOption('port'), $_entity])
                : $_entity;
            $console->setOption('crud', true)->setOption('port', $port);
            $this->addPort($console);
        }

        $console->setOption('crud', 'create')->setOption('service', "CRUD/Create{$_entity}");
        $this->addService($console);
        if (! $console->hasOption('nodelete')) {
            $console->setOption('crud', 'delete')->setOption('service', "CRUD/Delete{$_entity}");
            $this->addService($console);
        }
        if (! $console->hasOption('noupdate')) {
            $console->setOption('crud', 'update')->setOption('service', "CRUD/Update{$_entity}");
            $this->addService($console);
        }
        if (! $console->hasOption('noshow')) {
            $console->setOption('crud', 'show')->setOption('service', "CRUD/Show{$_entity}");
            $this->addService($console);
        }
        if (! $console->hasOption('nolist')) {
            $console->setOption('crud', 'list')->setOption('service', "CRUD/List{$_entity}");
            $this->addService($console);
        }

        if (! $console->hasOption('noasm')) {
            $asm = $console->hasOption('asm')
                ? join('/', [$console->getOption('asm'), $_entity])
                : $_entity;
            $console->setOption('asm', $asm);
            $this->addAssembler($console);
        }
    }

    /**
     * @CMD(domain.add)
     * @Desc(Create a new domain)
     * @Argv(1){notes=Domain name to be Created}
     */
    public function addDomain($console)
    {
        $name = $console->first();
        if (! $name) {
            $console->exception('MissingDomainName');
        }

        if (DomainManager::getByKey($name)) {
            $console->exception('DomainAlreadyExists', compact('name'));
        }

        $_name = ucfirst($name);

        $file = ospath(Kernel::getRoot(), DomainManager::DOMAIN_PATH, $_name, DomainManager::DOMAIN_FLAG, DomainManager::DOMAIN_FILE);
        $init = <<<PHP
<?php

return [
];
PHP;

        save($file, $init);

        $console->success("Created new domain: {$_name}");
    }

    /**
     * @CMD(cmd.add)
     * @Desc(Create a domain command class)
     * @Option(cmd){notes=Command name to be Created}
     * @Option(domain){notes=Domain name of command to be created}
     * @Option(force){notes=Whether force recreate command when given command class exists}
     */
    public function addCMD($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $name = $console->getOption('cmd');
        if (! $name) {
            $console->exception('MissingCommandName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $class = ospath($path, Kernel::COMMAND, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('CommandAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'command.tpl');
        if (! is_file($template)) {
            $console->exception('CommandClassTemplateNotExist', [$template]);
        }

        $command = file_get_contents($template);
        $command = str_replace('__DOMAIN__', $domain, $command);
        $command = str_replace('__NAMESPACE__', path2ns($name), $command);
        $command = str_replace('__NAME__', basename($name), $command);

        save($class, $command);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Command: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(event.add)
     * @Desc(Add Event in a domain)
     * @Option(domain){notes=Domain name of event to be created}
     * @Option(event){notes=Event name}
     * @Option(force){notes=Whether force recreate event when given event name exists}
     */
    public function addEvent($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $name = $console->getOption('event');
        if (! $name) {
            $console->exception('MissingEventName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $class = ospath($path, Kernel::EVENT, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('EventAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'event.tpl');
        if (! is_file($template)) {
            $console->exception('EventClassTemplateNotExist', [$template]);
        }

        $event = file_get_contents($template);
        $event = str_replace('__DOMAIN__', $domain, $event);
        $event = str_replace('__NAMESPACE__', path2ns($name), $event);
        $event = str_replace('__NAME__', basename($name), $event);

        save($class, $event);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Event: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(listener.add)
     * @Desc(Add Listener in a domain)
     * @Option(domain){notes=Domain name of listener to be created}
     * @Option(listener){notes=Listener name}
     * @Option(force){notes=Whether force recreate listener when given listener name exists}
     */
    public function addListener($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $name = $console->getOption('listener');
        if (! $name) {
            $console->exception('MissingListenerName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $class = ospath($path, Kernel::LISTENER, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('ListenerAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'listener.tpl');
        if (! is_file($template)) {
            $console->exception('ListenerClassTemplateNotExist', [$template]);
        }

        $listener = file_get_contents($template);
        $listener = str_replace('__DOMAIN__', $domain, $listener);
        $listener = str_replace('__NAMESPACE__', path2ns($name), $listener);
        $listener = str_replace('__NAME__', basename($name), $listener);

        save($class, $listener);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Listener: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(logging.add)
     * @Desc(Add a Logging repository and storage at the same time)
     * @Option(domain){notes=Domain name of logging to be created}
     * @Option(logging){notes=Logging name}
     * @Option(force){notes=Whether force recreate logging when given logging name exists}
     */
    public function addLogging($console)
    {
        $logging = $console->getOption('logging', null, true);
        $domain = $console->getOption('domain', 'Logging');
        if (! DomainManager::getByKey($domain)) {
            $console->setParams([$domain]);

            $this->addDomain($console);
        }

        $console->setOption('domain', $domain);
        if ($storage = $console->getOption('storage')) {
            $console->setOption('storage', "{$storage}/{$logging}Log");
        } else {
            $console->setOption('storage', "{$logging}Log");
        }

        $console->setOption('repo', "{$logging}Log");
        $console->setOption('type', "logging");
        $this->addRepository($console);

        $console->setOption('logging', true);
        $this->addORMStorage($console);
    }

    /**
     * @CMD(event.queues)
     * @Desc(Get queues list of an event class in current environment for deployment)
     * @Argv(1){notes=The event class path}
     */
    public function getEventQueues($console)
    {
        $event = $console->first();
        if (! $event) {
            $console->exception('MissingEventClass');
        }
        if (! is_file($event)) {
            $console->exception('EventNotExists', compact('event'));
        }
        $_event = get_namespace_of_file($event, true);
        if (! $_event) {
            $console->exception('InvalidEventClass', compact('event', '_event'));
        }
        if (! is_subclass_of($_event, Event::class)) {
            $console->exception('InvalidEventClass', compact('event', '_event'));
        }
        $queue = (new $_event)->formatQueueName($_event, QueueManager::QUEUE_NORMAL);
        $domain = DomainManager::getKeyByNamespace($_event);
        $driver = ConfigManager::getDomainFinalEnvByNamespace(
            $_event,
            EVENT::EVENT_QUEUE_DRIVER,
            ConfigManager::getDomainFinalEnvByNamespace($_event, QueueManager::QUEUE_DRIVER)
        );
        $_queue = "--domain={$domain} --driver={$driver} --queue={$queue}";
        if ($queue === Event::DEFAULT_QUEUE) {
            $console->success($_queue, true);
        }
        $async = ConfigManager::getDomainEnvByNamespace($_event, Event::EVENT_ASYNC, []);
        if ((! $async) || (! array_key_exists($_event, $async))) {
            $console->success($_queue, true);
        }
        $partition = $async[$_event] ?? 0;
        if (! is_int($partition)) {
            $console->exception('InvalidAsyncEventPartitionInteger', compact('partition'));
        }
        if ($partition < 1) {
            $console->success($_queue, true);
        }
        for ($i = 0; $i < $partition; $i++) {
            $console->success(join('_', [$_queue, $i]));
        }
    }

    /**
     * @CMD(listener.queues)
     * @Desc(Get queues list of a listener class in current environment)
     * @Argv(1){notes=The listener class path}
     */
    public function getListenerQueues($console)
    {
        $listener = $console->first();
        if (! $listener) {
            $console->exception('MissingListenerClass');
        }
        if (! is_file($listener)) {
            $console->exception('ListenerNotExists', compact('listener'));
        }
        $_listener = get_namespace_of_file($listener, true);
        if (! $_listener) {
            $console->exception('InvalidListenerClass', compact('listener', '_listener'));
        }
        if (! is_subclass_of($_listener, Listener::class)) {
            $console->exception('InvalidListenerClass', compact('listener', '_listener'));
        }
        $queue = (new $_listener)->formatQueueName($_listener);
        $domain = DomainManager::getKeyByNamespace($_listener);
        $driver = ConfigManager::getDomainFinalEnvByNamespace(
            $_listener,
            Listener::LISTENER_QUEUE_DRIVER,
            ConfigManager::getDomainFinalEnvByNamespace($_listener, QueueManager::QUEUE_DRIVER)
        );
        $_queue = "--domain={$domain} --driver={$driver} --queue={$queue}";
        if ($queue === Listener::DEFAULT_QUEUE) {
            $console->success($_queue, true);
        }
        $async = ConfigManager::getDomainEnvByNamespace($_listener, Listener::LISTENER_ASYNC, []);
        if ((! $async) || (! array_key_exists($_listener, $async))) {
            $console->success($_queue, true);
        }
        $partition = $async[$_listener] ?? 0;
        if (! is_int($partition)) {
            $console->exception('InvalidAsyncListenerPartitionInteger', compact('partition'));
        }
        if ($partition < 1) {
            $console->success($_queue, true);
        }
        for ($i = 0; $i < $partition; $i++) {
            $console->success(join('_', [$_queue, $i]));
        }
    }

    /**
     * @CMD(queue.run)
     * @Desc(Start a queue worker on a queue in a domain)
     * @Option(domain){notes=Domain name where the origin of queue name}
     * @Option(driver){notes=Queue driver stores queue jobs}
     * @Option(queue){notes=Queue name to listen}
     * @Option(once){notes=Pop the first job of the queue and exit after that job finished}
     * @Option(quiet){notes=Do not print any output to console}
     * @Option(debug){notes=Queue dispatcher self as job worker}
     * @Option(daemon){notes=Run queue workers as daemon, required restart if code updated&default=true}
     * @Option(interval){notes=Seconds to waiting to re-check jobs if no jobs in current queue}
     * @Option(timeout){notes=Max seconds allowed for each job worker to execute}
     * @Option(try-times){notes=Max re-execute times when job failed}
     * @Option(try-delay){notes=Seconds of time to wait for re-executing after job failed}
     */
    public function queueRun($console)
    {
        $domain = $console->getOption('domain', null, true);
        $ns = array_flip(DomainManager::getNamespaces())[$domain] ?? $ns;
        if (! $ns) {
            $console->exception('InvalidDomainKey', compact('domain'));
        }

        $driver = $console->getOption('driver', null);
        $queue = $console->getOption('queue', null, true);

        // Get a queuable instance first
        $queuable = QueueManager::get($ns, $queue, $driver);
        if (! $queuable) {
            $console->exception('NoQueuableFoundInGivenDomainDriverAndName', compact(
                'domain',
                'driver',
                'queue'
            ));
        }

        if ($console->hasOption('once')) {
            $job = $queuable->dequeue(QueueManager::formatQueueName($queue, QueueManager::QUEUE_NORMAL));
            if (! $job) {
                $console->info('NoJobOnCurrentQueue');
                $console->info(json_pretty(compact('domain', 'queue', 'driver')), true);
            }

            try {
                $job->execute();
            } catch (Throwable $e) {
                $console->exception('JobExceptionFoundWhenOncing', compact('queue'), $e);
            }

            return $console->success('Done!', true);
        }

        $interval = (int) $console->getOption('interval', 3);
        $timeout = (int) $console->getOption('timeout', 0);
        $tryTimes = (int) $console->getOption('try-times', 0);
        $tryDelay = (int) $console->getOption('try-delay', -1);

        // Start a queue scheduler and looping jobs for workers on that queue
        (new QueueDispatcher)
            ->setQueuable($queuable)
            ->setConsole($console)
            ->setQueue($queue)
            ->setQuiet($console->hasOption('quiet'))
            ->setDebug($console->hasOption('debug'))
            ->setDaemon($console->hasOption('daemon'))
            ->setInterval($interval)
            ->setTimeout($timeout)
            ->setTryTimes($tryTimes)
            ->setTryDelay($tryDelay)
            ->looping();
    }

    /**
     * @CMD(queue.restart)
     * @Desc(Re-start a queue worker on a queue name of a domain)
     * @Option(domain){notes=Domain name where the origin of queue name}
     * @Option(driver){notes=Queue driver stores queue jobs}
     * @Option(queue){notes=Queue name to restart}
     */
    public function queueRestart($console)
    {
        $domain = $console->getOption('domain', null, true);
        $ns = array_flip(DomainManager::getNamespaces())[$domain] ?? $ns;
        if (! $ns) {
            $console->exception('InvalidDomainKey', compact('domain'));
        }

        $driver = $console->getOption('driver', null);
        $queue = $console->getOption('queue', null, true);

        // Get a queuable instance first
        $queuable = QueueManager::get($ns, $queue, $driver);
        if (! $queuable) {
            $console->exception('NoQueuableFoundInGivenDomainDriverAndName', compact(
                'domain',
                'driver',
                'queue'
            ));
        }

        $_queue = QueueManager::formatQueueName($queue, QueueManager::QUEUE_RESTART);

        $queuable->setRestart($_queue)
            ? $console->success('Done!') : $console->fail('Failed');
    }
}
