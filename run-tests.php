<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', 'php.errors');

require_once __DIR__.'/vendor/autoload.php';

(new Loy\Framework\Cli\Command\Command)->testFramework(Loy\Framework\Facade\Console::setEntry(__FILE__));
