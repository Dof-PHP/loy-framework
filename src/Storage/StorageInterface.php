<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

interface StorageInterface
{
    public function __construct(array $config = []);

    /**
     * Set parameters for quering use
     */
    public function setQuery(array $query);

    /**
     * Find by primary key
     */
    public function find(int $pk);

    /**
     * Delete by primary key
     */
    public function delete(int $pk);
}
