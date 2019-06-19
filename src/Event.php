<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Container;
use Dof\Framework\DDD\Model;

/**
 * Event properties must be un-private
 */
abstract class Event extends Model
{
    final public function publish()
    {
        // pd($this->__toXml());
        // pd($this->__toJson());
        // pd($this->toString());
        // pd($this->toArray());
    }
}
