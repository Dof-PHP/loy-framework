<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

use Dof\Framework\CollectionFacadeTrait;
use Dof\Framework\Facade\Annotation;

final class Port
{
    use CollectionFacadeTrait;

    public function annotations()
    {
        list($ofClass, , $ofMethods) = Annotation::get(self::$instance->class);

        return collect([
            'class'  => $ofClass['doc'] ?? [],
            'method' => $ofMethods[self::$instance->method]['doc'] ?? [],
        ], __CLASS__);
    }

    public function class(string $annotation = null)
    {
        list($ofClass, , ) = Annotation::get(self::$instance->class);

        $annotations = $ofClass['doc'] ?? [];

        return $annotation
            ? ($annotations[strtoupper($annotation)] ?? null)
            : $annotations;
    }

    public function method(string $annotation = null)
    {
        list(, , $ofMethods) = Annotation::get(self::$instance->class);
        $annotations = $ofMethods[self::$instance->method]['doc'] ?? [];

        return $annotation
            ? ($annotations[strtoupper($annotation)] ?? null)
            : $annotations;
    }
}
