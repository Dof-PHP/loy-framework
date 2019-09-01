<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Service\CRUD;

use Throwable;
use Dof\Framework\DDD\Service;
use Domain\__DOMAIN__\Repository\__ENTITY__Repository;
use Domain\__DOMAIN__\Entity\__ENTITY__;
// use Dof\Framework\EXCP;
// use Domain\__DOMAIN__\EXCP as ERR;

class Create__ENTITY__ extends Service
{
    private $param1;

    private $repository;
    private $entity;

    public function __construct(__ENTITY__Repository $repository, __ENTITY__ $entity)
    {
        $this->repository = $repository;
        $this->entity = $entity;
    }

    public function execute()
    {
        // $entity = __ENTITY__::new();

        // if ($this->param1) {
        // ...
        // }

        $this->entity->updatedAt = 0;
        $this->entity->createdAt = time();

        try {
            return $this->repository->add($this->entity);
        } catch (Throwable $e) {
            // if (EXCP::is($e, EXCP::VIOLATED_UNIQUE_CONSTRAINT)) {
            //   $this->exception('Duplicate__ENTITY__Attr1', ['attr1' => $this->entity->attr1]);
            // }

            $this->exception('Create__ENTITY__Failed', [], $e);
        }
    }

    public function setParam1(string $param1)
    {
        $this->param1 = $param1;

        return $this;
    }

    public function setAttr1(string $attr1)
    {
        $this->entity->attr1 = $attr1;

        return $this;
    }
}
