<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

use Closure;
use Dof\Framework\Collection;

trait HttpTrait
{
    protected $headers = [];

    protected static $mimes = [
        'text' => 'text/plain',
        'html' => 'text/html',
        'view' => 'text/html',
        'json' => 'application/json',
        'xml'  => 'application/xml',
        'form' => 'application/x-www-form-urlencoded',
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

        return ($alias === false) ? $mime : $alias;
    }

    public function getMimeByAlias(string $alias, string $default = null) : ?string
    {
        $mime = self::$mimes[strtolower($alias)] ?? null;

        return $mime ? $mime : $default;
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

    public function convertStringAsMime(string $input, string $mime = null)
    {
        if ($mime === 'json') {
            $data = dejson($input, true);
            return is_array($data) ? $data : [];
        }
        if ($mime === 'xml') {
            $data = dexml($input, true);
            return is_array($data) ? $data : [];
        }
        if ($mime === 'form') {
            $data = [];
            parse_str($input, $data);
            return $data;
        }

        return $input;
    }
}
