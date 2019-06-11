<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Service\CRUD;

use Throwable;
use Dof\Framework\EXCP;
use Dof\Framework\DDD\Service;
use Domain\__DOMAIN__\Repository\__ENTITY__Repository;

class Update__ENTITY__ extends Service
{
    private $id;
    private $param1;

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

        $entity
            ->setParam1($this->param1)
            ->setUpdatedAt(time());

        try {
            return $this->repository->save($role);
        } catch (Throwable $e) {
            if (EXCP::is($e, EXCP::VIOLATED_UNIQUE_CONSTRAINT)) {
                $this->exception('Duplicate__ENTITY__Param1', ['param1' => $this->param1]);
            }

            $this->exception('Update__ENTITY__Failed', [], $e);
        }
    }

    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    public function setParam1(string $param1)
    {
        $this->param1 = $param1;

        return $this;
    }
}
