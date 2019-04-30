<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', 'php.errors');

$autoloader = __DIR__.'/../vendor/autoload.php';
if (! is_file($autoloader)) {
    exit('Autoloader not found, run `composer install` first!');
}

require_once $autoloader;

(new \Dof\Framework\Cli\Command\Command)->testFramework(\Dof\Framework\Facade\Console::setEntry(__FILE__));
