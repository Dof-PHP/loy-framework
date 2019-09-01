<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

interface Storable
{
    public function setConnection($connection);

    public function getConnection();

    public function connectable() : bool;
}
