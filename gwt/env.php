<?php

$gwt->true('Test PHP Version against 7.1+', \version_compare(PHP_VERSION, '7.1') >= 0);
$gwt->true('Test Swoole enabled', \extension_loaded('swoole'));

$gwt->add(
    'Test disk free space is greater than 1MB',
    \DOF\Util\FS::path(\DOF\DOF::root(), \DOF\Convention::DIR_RUNTIME),
    function ($given) {
        return\is_dir($given) ? disk_free_space($given) : 1048577;
    },
    function ($result) {
        return $result > 1048576;
    }
);
