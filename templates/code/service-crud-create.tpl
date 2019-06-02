<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Service\CRUD;

use Throwable;
use Dof\Framework\EXCP;
use Dof\Framework\DDD\Service;
use Domain\__DOMAIN__\Repository\__ENTITY__Repository;
use Domain\__DOMAIN__\Entity\__ENTITY__;
// use Domain\__DOMAIN__\EXCP as ERR;

class Create__NAME__ extends Service
{
    private $param1;

    private $repository;

    public function __construct(__ENTITY__Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute()
    {
        $entity = __ENTITY__::new()
                ->setParam1($this->param1)
                ->setUpdatedAt(0)
                ->setCreatedAt(time());

        try {
            return $this->repository->add($entity);
        } catch (Throwable $e) {
            if (EXCP::is($e, EXCP::VIOLATED_UNIQUE_CONSTRAINT)) {
               $this->exception('Duplicate__ENTITY__Param1', ['param1' => $this->param1]);
            }

            $this->exception('Add__ENTITY__Failed', [], $e);
        }
    }

    public function setParam1(string $param1)
    {
        $this->param1 = $param1;

        return $this;
    }
}
