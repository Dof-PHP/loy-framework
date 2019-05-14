<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

interface StorageInterface
{
    public function setConnection($connection);

    public function getConnection();
}
