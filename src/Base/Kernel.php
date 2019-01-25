<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\ConfigManager;
use Loy\Framework\Base\DomainManager;
use Loy\Framework\Base\OrmManager;
use Loy\Framework\Base\Exception\InvalidProjectRootException;
use Loy\Framework\Web\RouteManager;
use Loy\Framework\Web\PipeManager;
use Loy\Framework\Web\WrapperManager;

class Kernel
{
    protected static $root = null;

    public static function handle(string $root)
    {
        if (! is_dir($root)) {
            throw new InvalidProjectRootException($root);
        }
        self::$root = $root;

        self::initBaseConfig();
        self::compileDomain();
        self::loadDomainConfig();
        self::buildContainer();
        self::compileOrm();
    }

    public static function loadDomainConfig()
    {
        ConfigManager::load(DomainManager::getDirsD2M());
    }

    public static function initBaseConfig()
    {
        ConfigManager::init(self::$root);
    }

    public static function buildContainer()
    {
        Container::build(DomainManager::getDirsD2M());
    }

    public static function compileOrm()
    {
        OrmManager::compile(DomainManager::getDirsD2M());
    }

    public static function compileRoute()
    {
        RouteManager::compile(DomainManager::getDirs());
    }

    public static function compilePipe()
    {
        PipeManager::compile(DomainManager::getDirs());
    }

    public static function compileWrapper()
    {
        WrapperManager::compile(DomainManager::getDirs());
    }

    public static function compileDomain()
    {
        DomainManager::compile(self::$root);
    }

    public static function getRoot()
    {
        return self::$root;
    }
}
