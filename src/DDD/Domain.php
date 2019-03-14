<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

use Loy\Framework\ConfigManager;

class Domain
{
    private $meta;
    private $root;
    private $chain;
    private $config;

    public function __construct(string $meta, string $root, array $chain)
    {
        $this->meta  = $meta;
        $this->root  = $root;
        $this->chain = $chain;
    }

    public function config(string $key = 'domain')
    {
        if (! $this->config) {
            $this->config = collect(ConfigManager::getDomain($this->root), __CLASS__);
        }

        return is_null($key) ? $this->config : $this->config->get($key);
    }

    public function chain()
    {
        return $this->chain;
    }

    public function children()
    {
        return $this->chain['down'][$this->meta] ?? [];
    }

    public function parent()
    {
        return $this->chain['up'][$this->meta] ?? null;
    }

    public function ancestor()
    {
        return $this->chain['root'] ?? null;
    }

    public function meta()
    {
        return $this->meta;
    }

    public function root()
    {
        return $this->root;
    }
}
