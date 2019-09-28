<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Service\CRUD;

use Throwable;
use Dof\Framework\DDD\Service;
// use Domain\__DOMAIN__\EXCP;
// use Domain\__DOMAIN__\AUX;
use Domain\__DOMAIN__\Repository\__ENTITY__Repository;

class Show__ENTITY__ extends Service
{
    private $id;

    private $repository;

    public function __construct(__ENTITY__Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute()
    {
        $entity = $this->repository->find($this->id);
        if (! $entity) {
            $this->exception('__ENTITY__NotFound', [$this->id]);
        }

        return $entity;
    }

    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }
}
