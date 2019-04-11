<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once __DIR__.'/../vendor/autoload.php';

$rules = [
    'id' => 'need=%s必填的&uint=%s必须是正整数&in:22,323,43=%s不在配置中: %s&sthno&default=32',
];

$validator = \Loy\Framework\Facade\Validator::setData(['id' => 01])->setRules($rules)->execute();
if ($fails = $validator->getFails()) {
    $fail = $fails->first();
    pd($fail->key, $fail->value);
}
