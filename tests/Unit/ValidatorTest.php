<?php

GWT('Test Validator rule `uint` #1', [
    'id' => 'uint=%s必须是正整数: %s',
], function ($given) {
    return \Dof\Framework\Facade\Validator::setData(['id' => -1])->setRules($given)->execute();
}, function ($result) {
    return ($result->getFails()->first()->key ?? false) === 'id必须是正整数: -1';
});

GWT('Test Validator rule `uint` #2', [
    'id' => 'uint=%s必须是正整数: %s',
], function ($given) {
    return \Dof\Framework\Facade\Validator::setData(['id' => 1])->setRules($given)->execute();
}, function ($result) {
    return is_null($result->getFails());
});

GWT('Test Validator rule `uint` #3: typehint', [
    'id' => 'uint=%s必须是正整数: %s',
], function ($given) {
    return \Dof\Framework\Facade\Validator::setData(['id' => '1'])->setRules($given)->execute();
}, function ($result) {
    return is_null($result->getFails());
});

GWT('Test Validator rule `in` #1', [
    'id' => [
        'in:1,2,3' => '%s不在配置中: %s',
    ],
], function ($given) {
    return \Dof\Framework\Facade\Validator::setData(['id' => 0])->setRules($given)->execute();
}, function ($result) {
    return ($result->getFails()->first()->key ?? false) === 'id不在配置中: 0';
});

GWT('Test Validator rule `in` #2', [
    'id' => [
        'in:1,2,3' => '%s不在配置中: %s',
    ],
], function ($given) {
    return \Dof\Framework\Facade\Validator::setData(['id' => 1])->setRules($given)->execute();
}, function ($result) {
    return is_null($result->getFails());
});
