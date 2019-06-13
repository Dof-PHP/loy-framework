<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Closure;

class Storage
{
    protected $annotations;

    protected $connection;

    /** @var Closure: The getter to get acutal connection */
    protected $connectionGetter;

    public function __construct(array $annotations = [])
    {
        $this->annotations = collect($annotations);
    }

    public function setConnectionGetter(Closure $getter)
    {
        $this->connectionGetter = $getter;

        return $this;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection()
    {
        if ((! $this->connection) && $this->connectionGetter) {
            $this->connection = ($this->connectionGetter)();
        }

        if (! $this->connection) {
            exception('MissingStorageConnection');
        }

        // ...

        return $this->connection;
    }

    public function annotations()
    {
        return $this->annotations;
    }
}
