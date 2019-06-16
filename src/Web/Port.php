<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

use Dof\Framework\OFB\Traits\CollectionFacadeTrait;
use Dof\Framework\Facade\Annotation;

final class Port
{
    use CollectionFacadeTrait;

    public static function argvs() : array
    {
        return self::getInstance()->argument->toArray();
    }

    public static function annotations()
    {
        list($ofClass, , $ofMethods) = Annotation::get(self::$instance->class);

        return collect([
            'class'  => $ofClass['doc'] ?? [],
            'method' => $ofMethods[self::$instance->method]['doc'] ?? [],
        ], __CLASS__);
    }

    public static function class(string $annotation = null)
    {
        list($ofClass, , ) = Annotation::get(self::$instance->class);

        $annotations = $ofClass['doc'] ?? [];

        return $annotation
            ? ($annotations[strtoupper($annotation)] ?? null)
            : $annotations;
    }

    public static function method(string $annotation = null)
    {
        list(, , $ofMethods) = Annotation::get(self::$instance->class);
        $annotations = $ofMethods[self::$instance->method]['doc'] ?? [];

        return $annotation
            ? ($annotations[strtoupper($annotation)] ?? null)
            : $annotations;
    }
}
