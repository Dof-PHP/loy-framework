<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Listener__NAMESPACE__;

use Throwable;
use Dof\Framework\Listener;
// use Domain\__DOMAIN__\Repository\SomeRepository;
// use Domain\__DOMAIN__\Service\__NAME__;

/**
 * @_Listen(Domain\__DOMAIN__\Event\EventName)
 * @_Listen(Dof\Framework\OFB\Event\EntityCreated)
 * @_Listen(Dof\Framework\OFB\Event\EntityRemoved)
 * @_Listen(Dof\Framework\OFB\Event\EntityUpdated)
 */
class __NAME__ extends Listener
{
    public function execute()
    {
        // $repository = $this->di(SomeRepository::class);
        // $service = __NAME__::init();
        // TODO: $this->event
    }
}