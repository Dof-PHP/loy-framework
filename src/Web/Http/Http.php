<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Http;

trait Http
{
    protected static $mimes = [
        'text' => 'text/plain',
        'html' => 'text/html',
        'view' => 'text/html',
        'json' => 'application/json',
        'xml'  => 'application/xml',
    ];

    public function getMimeByAlias(string $alias) : ?string
    {
        return self::$mimes[$alias] ?? '?';
    }
}
