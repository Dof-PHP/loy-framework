<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Http;

use Closure;
use Loy\Framework\Base\Collection;

trait Http
{
    protected static $mimes = [
        'text' => 'text/plain',
        'html' => 'text/html',
        'view' => 'text/html',
        'json' => 'application/json',
        'xml'  => 'application/xml',
    ];

    private $data = [];

    public function __construct(array $data = [])
    {
        $this->data = new Collection($data);
    }

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

    protected function getOrSet(string $key, Closure $callback)
    {
        $value = $this->data->get($key);
        if (is_null($value)) {
            $value = $callback();
            $this->data->set($key, $value);
        }

        return $value;
    }
}
