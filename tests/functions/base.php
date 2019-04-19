<?php

GWT('Test get_file_of_namespace() #1', \Dof\Framework\Kernel::class, function ($given) {
    return get_file_of_namespace($given);
}, function ($result) {
    $file = join(DIRECTORY_SEPARATOR, [dirname(dirname(dirname(__FILE__))), 'src', 'Kernel.php']);

    return $file === $result;
});

GWT('Test get_file_of_namespace() #2: class not exists', 'non-exists namespace', function ($given) {
    return get_file_of_namespace($given);
}, function ($result) {
    return false === $result;
});

GWT('Test get_namespace_of_file() #1: bad namespace', __FILE__, function ($given) {
    return get_namespace_of_file($given);
}, function ($result) {
    return false === $result;
});

GWT('Test get_namespace_of_file() #2', function () {
    return join(DIRECTORY_SEPARATOR, [dirname(dirname(dirname(__FILE__))), 'src', 'Kernel.php']);
}, function ($given) {
    return get_namespace_of_file($given, false);
}, function ($result) {
    return 'Dof\Framework' === $result;
});

GWT('Test get_namespace_of_file() #3', function () {
    return join(DIRECTORY_SEPARATOR, [dirname(dirname(dirname(__FILE__))), 'src', 'Kernel.php']);
}, function ($given) {
    return get_namespace_of_file($given, true);
}, function ($result) {
    return 'Dof\Framework\Kernel' === $result;
});

GWT('Test get_used_classes() #1', function () {
    return join(DIRECTORY_SEPARATOR, [dirname(dirname(dirname(__FILE__))), 'src', 'Kernel.php']);
}, function ($given) {
    return get_used_classes($given, false);
}, function ($result) {
    return (true
        && array_key_exists('Closure', $result)
        && array_key_exists('Dof\Framework\Facade\Log', $result)
        && ($result['Dof\Framework\Web\Kernel'] ?? false === 'WebKernel')
        && ($result['Dof\Framework\Cli\Kernel'] ?? false === 'CliKernel')
    );
});

GWT('Test get_used_classes() #2: bad php file', 'non-exists php file', function ($given) {
    return get_used_classes($given, false);
}, function ($result) {
    return is_null($result);
});

GWT('Test get_used_classes() #3: bad namespace', 'non-exists namespace', function ($given) {
    return get_used_classes($given, true);
}, function ($result) {
    return is_null($result);
});

GWT('Test enxml() #1: assoc array', ['a' => ['b' => ['d' => 1024]]], function ($given) {
    return enxml($given);
}, function ($result) {
    return $result === '<?xml version="1.0" encoding="utf-8"?><xml><a><b><d>1024</d></b></a></xml>';
});

GWT('Test enxml() #2: object with toArray()', function () {
    return new class {
        public function toArray()
        {
            return ['a' => ['b' => ['d' => 1024]]];
        }
    };
}, function ($given) {
    return enxml($given);
}, function ($result) {
    return $result === '<?xml version="1.0" encoding="utf-8"?><xml><a><b><d>1024</d></b></a></xml>';
});

GWT('Test enxml() #3: object with __toArray()', function () {
    return new class {
        public function __toArray()
        {
            return ['a' => ['b' => ['d' => 1024]]];
        }
    };
}, function ($given) {
    return enxml($given);
}, function ($result) {
    return $result === '<?xml version="1.0" encoding="utf-8"?><xml><a><b><d>1024</d></b></a></xml>';
});

GWT('Test enxml() #3: index array', [1, 2, 3, 4], function ($given) {
    return enxml($given);
}, function ($result) {
    return $result === '<?xml version="1.0" encoding="utf-8"?><xml><0>1</0><1>2</1><2>3</2><3>4</3></xml>';
});

GWT('Test enxml() #3: index array', [1, 2, 3, 4], function ($given) {
    return enxml($given);
}, function ($result) {
    return $result === '<?xml version="1.0" encoding="utf-8"?><xml><0>1</0><1>2</1><2>3</2><3>4</3></xml>';
});

GWT('Test dexml() #1: assoc array', '<?xml version="1.0" encoding="utf-8"?><xml><a><b><d>1024</d></b></a></xml>', function ($given) {
    return dexml($given);
}, function ($result, $tester) {
    return $tester->assertArrayEqual(['a' => ['b' => ['d' => 1024]]], $result);
});

/* FIXME
GWT('Test dexml() #1: index array', '<?xml version="1.0" encoding="utf-8"?><xml><0>1</0><1>2</1><2>3</2><3>4</3></xml>', function ($given) {
    return dexml($given);
}, function ($result, $tester) {
    return $tester->assertArrayEqual([1, 2, 3, 4], $result);
});
 */

GWT('Test is_date_format(): #1', '2019-01-01 10:10:10', function ($given) {
    return is_date_format($given);
}, function ($result, $tester) {
    return $result === true;
});

GWT('Test is_date_format(): #2', '2019-01-01', function ($given) {
    return is_date_format($given);
}, function ($result, $tester) {
    return $result === false;
});

GWT('Test is_date_format(): #3', '2019/01/01 10:10:10', function ($given) {
    return is_date_format($given);
}, function ($result, $tester) {
    return $result === false;
});

GWT('Test is_date_format(): #4', ['2019/01/01 10:10:10', 'Y/m/d H:i:s'], function ($given) {
    return is_date_format(...$given);
}, function ($result, $tester) {
    return $result === true;
});

GWT('Test fdate(): #1', [strtotime('2019/01/02 10:10:10'), 'm/d/y H:i:s'], function ($given) {
    return fdate(...$given);
}, function ($result, $tester) {
    return $result === '01/02/19 10:10:10';
});