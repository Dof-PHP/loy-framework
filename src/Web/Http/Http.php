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

    public function getMimeAliases() : array
    {
        return array_keys(self::$mimes);
    }

    public function getMimeAlias() : ?string
    {
        $mime  = $this->getMime();
        $alias = array_search($mime, self::$mimes);

        return ($alias === false) ? null : $alias;
    }

    public function getMimeByAlias(string $alias) : ?string
    {
        return self::$mimes[$alias] ?? '?';
    }
}
