<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Service\CURD\__NAME__;

use Domain\__DOMAIN__\Repository\__ENTITY__Repository;
use Domain\__DOMAIN__\Entity\__ENTITY__;

use Dof\Framework\DDD\Service;

class __NAME__ extends Service
{
    private $id;
    
    private $repository;

    public function __construct(__ENTITY__Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute()
    {
        // TODO
    }

    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }
}
