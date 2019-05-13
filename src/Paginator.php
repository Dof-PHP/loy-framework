<?php

declare(strict_types=1);

namespace Dof\Framework;

class Paginator
{
    /** @var array: Current data list */
    private $list = [];

    /** @var int: Total rows */
    private $total = 0;

    /** @var int: Current list length */
    private $count = 0;

    /** @var int: Current page number */
    private $page = 1;

    /** @var int: Current page size */
    private $size = 16;

    public function __construct(array $list = [], array $params = [])
    {
        $this->setList($list)->setParams($params);
    }

    /**
     * Getter for list
     *
     * @return array
     */
    public function getList(): array
    {
        return $this->list;
    }
    
    /**
     * Setter for list
     *
     * @param array $list
     * @return Paginator
     */
    public function setList(array $list)
    {
        $this->list = $list;
        $this->count = count($list);
    
        return $this;
    }

    /**
     * Getter for total
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }
    
    /**
     * Setter for total
     *
     * @param int $total
     * @return Paginator
     */
    public function setTotal(int $total)
    {
        $this->total = $total;
    
        return $this;
    }

    /**
     * Getter for page
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }
    
    /**
     * Setter for page
     *
     * @param int $page
     * @return Paginator
     */
    public function setPage(int $page)
    {
        $this->page = $page;
    
        return $this;
    }

    /**
     * Getter for size
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }
    
    /**
     * Setter for size
     *
     * @param int $size
     * @return Paginator
     */
    public function setSize(int $size)
    {
        $this->size = $size;
    
        return $this;
    }

    public function getMeta(): array
    {
        return [
            'total' => $this->total,
            'count' => $this->count,
            'page'  => $this->page,
            'size'  => $this->size,
        ];
    }

    public function setParams($params)
    {
        $this->page  = intval($params['page']  ?? 1);
        $this->size  = intval($params['size']  ?? 16);
        $this->total = intval($params['total'] ?? 0);

        return $this;
    }
}
