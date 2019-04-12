<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', __DIR__.'/php.errors');

require_once __DIR__.'/../vendor/autoload.php';

$start = microtime(true);
run_framework_tests(__DIR__, [
    __FILE__ => true,
]);
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
