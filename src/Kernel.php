<?php

declare(strict_types=1);

namespace Loy\Framework;

/**
 * Loy Framework Core Kernel
 */
final class Kernel
{
    /** @var string Project Root Directory */
    protected static $root;

    /**
     * Core kernel handler - The genesis of application
     *
     * 1. Load framework and domain configurations
     * 2. Compile components and build application container
     *
     * @param string $root
     * @return null
     */
    public static function boot(string $root)
    {
        if (! is_dir(self::$root = $root)) {
            exception('InvalidProjectRoot', ['root' => $root]);
        }

        ConfigManager::init(self::$root);

        // Record every uncatched exceptions
        set_exception_handler(function ($throwable) {
            pd('error_handler', $throwable->getTraceAsString());
            // TODO: Logging
        });
        // Record every uncatched error regardless to the setting of the error_reporting setting
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            pd('error_handler', $errstr, $errfile, $errline);
            // TODO: Logging
        });

        DomainManager::compile(self::$root);

        ConfigManager::load(DomainManager::getDirsD2M());

        // Container::build(DomainManager::getDirsD2M());

        EntityManager::compile(DomainManager::getDirs());

        // RepositoryManager::compile(DomainManager::getDirs());

        // pd(RepositoryManager::getRepositories());

        // StorageManager::compile(DomainManager::getDirs());

        PipeManager::compile(DomainManager::getDirs());

        WrapperManager::compile(DomainManager::getDirs());

        RouteManager::compile(DomainManager::getDirs());
        // pd(RouteManager::getRoutes());
    }

    public static function getRoot()
    {
        return self::$root;
    }
}
