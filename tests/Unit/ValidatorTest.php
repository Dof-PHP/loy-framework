<?php

GWT('测试validator的check方法', [
        'id' => 'need=%s必填的&uint=%s必须是正整数&in:22,323,43=%s不在配置中: %s&sthno&default=32',
], function ($given) {
    return \Dof\Framework\Facade\Validator::setData(['id' => 01])->setRules($given)->execute();
}, function ($result) {
    return ($result->getFails()->first()->key ?? false) === 'id不在配置中: 1';
});

GWT('测试2', [
    'id' => 'need=%s必填的&uint=%s必须是正整数&in:22,323,43=%s不在配置中: %s&sthno&default=32',
], function ($given) {
    return \Dof\Framework\Facade\Validator::setData(['id' => 1])->setRules($given)->execute();
}, function ($result) {
    return ($result->getFails()->first()->key ?? false) === 'id不在配置中: 1';
});

GWT('测试4', [
    'id' => 'need=%s必填的&uint=%s必须是正整数&in:22,323,43=%s不在配置中: %s&sdsd&default=32',
], function ($given) {
    return \Dof\Framework\Facade\Validator::setData(['id' => 22])->setRules($given)->execute();
}, function ($result) {
    return is_null($result->getFails());
});
