<?php

GWT('Test PHP Version', PHP_VERSION, function ($given) {
    return version_compare($given, '7.1');
}, function ($result) {
    return $result >= 0;
});

GWT('Test disk free space is greater than 1MB', ospath(\Dof\Framework\Kernel::getRoot(), \Dof\Framework\Kernel::RUNTIME), function ($given) {
    return is_dir($given) ? disk_free_space($given) : 1048577;
}, function ($result) {
    return $result > 1048576;
});
