<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Service__NAMESPACE__;

use Throwable;
use Dof\Framework\DDD\Service;
// use Domain\__DOMAIN__\AUX;
// use Domain\__DOMAIN__\EXCP;
// use Domain\__DOMAIN__\Repository\EntityRepository;

class __NAME__ extends Service
{
    private $param1;

    private $repository;

//    public function __construct(EntityRepository $repository)
//    {
          // $this->repository = $repository;
//    }

    public function execute()
    {
        // TODO
    }

    public function setParam1(string $param1)
    {
        $this->param1 = $param1;

        return $this;
    }
}
