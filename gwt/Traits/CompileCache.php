<?php

$gwt->unit('Test \DOF\Traits\CompileCache::formatCompileFile()', function ($t) {
    $t->eq(\DOF\ETC::formatCompileFile(), 'var/compile/DOF.ETC');
    $t->eq(\DOF\INI::formatCompileFile(), 'var/compile/DOF.INI');
    $t->eq(\DOF\DMN::formatCompileFile(), 'var/compile/DOF.DMN');
    $t->eq(\DOF\Container::formatCompileFile(), 'var/compile/DOF.Container');
});

$gwt->true('Test \DOF\Traits\CompileCache::removeCompileFile()', function ($t) {
    $file = \DOF\ETC::formatCompileFile();
    \DOF\Util\FS::save($file, 'testing');
    $res1 =\is_file($file);
    \DOF\ETC::removeCompileFile();
    $res2 = !\is_file($file);

    return $res1 && $res2;
});
