<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\Annotation;

class ORMManager
{
    const ORM_DIR = 'ORM';
    const REGEX = '#@([a-zA-z]+)\((.*)\)#';

    private static $dirs = [];
    private static $orms = [];

    public static function compile()
    {
        $dirs = [
            '/data/wwwroot/pw.centos/domain/User',
        ];

        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = join(DIRECTORY_SEPARATOR, [$item, self::ORM_DIR]);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Excetions may thrown but let invoker to catch for different scenarios
        //
        // use Loy\Framework\Base\Exception\InvalidAnnotationDirException;
        // use Loy\Framework\Base\Exception\InvalidAnnotationNamespaceException;
        Annotation::parseClassDirs(self::$dirs, self::REGEX, function ($annotations) {
            if ($annotations) {
                list($ofClass, $ofProperties, $ofMethods) = $annotations;
                self::assembleOrmsFromAnnotations($ofClass, $ofProperties);
            }
        }, __CLASS__);
    }

    public static function assembleOrmsFromAnnotations(array $ofClass, array $ofProperties)
    {
        ee($ofClass, $ofProperties);
    }
}
