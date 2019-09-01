<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Service\CRUD;

use Throwable;
use Dof\Framework\Collection;
use Dof\Framework\DDD\Service;
use Domain\__DOMAIN__\Repository\__ENTITY__Repository;

class List__ENTITY__ extends Service
{
    private $page;
    private $size;
    private $sortField;
    private $sortOrder;
    private $filter;

    private $repository;

    public function __construct(__ENTITY__Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute()
    {
        return $this->repository->list(
            $this->page,
            $this->size,
            $this->filter,
            $this->sortField,
            $this->sortOrder
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

    public function setSortOrder(string $sortOrder = null)
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function setSortField(string $sortField = null)
    {
        $this->sortField = $sortField;

        return $this;
    }

    public function setFilter(Collection $filter)
    {
        $this->filter = $filter;

        return $this;
    }
}
