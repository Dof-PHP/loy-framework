<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Repository__NAMESPACE__;

use Dof\Framework\DDD\ORMRepository;

/**
 * @Implementor(Domain\__DOMAIN__\Storage\__STORAGE__)
 * @Entity(Dof\Framework\OFB\Entity\Logging)
 */
interface __NAME__Repository extends ORMRepository
{
    public function logging(array $context);
}
