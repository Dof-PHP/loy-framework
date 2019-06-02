<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Service\CRUD;

use Dof\Framework\DDD\Service;
use Domain\__DOMAIN__\Repository\__ENTITY__Repository;

class List__ENTITY__ extends Service
{
    private $id;
    private $page;
    private $size;
    private $search;

    private $repository;

    public function __construct(__ENTITY__Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute()
    {
        return $this->repository->search(
            $this->page,
            $this->size,
            $this->id,
            $this->search
        );
    }

    public function setPage(int $page)
    {
        $this->page = $page;

        return $this;
    }

    public function setSize(int $size)
    {
        $this->size = $size;

        return $this;
    }

    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    public function setSearch(string $search)
    {
        $this->search = $search;

        return $this;
    }
}
