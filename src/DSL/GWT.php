<?php

declare(strict_types=1);

namespace Loy\Framework\DSL;

use Closure;

/**
 * Given-When-Then descriptive model
 */
class GWT
{
    private $params;
    private $event;

    public function given(array $params)
    {
        $this->params;

        return $this;
    }

    public function when(Closure $event)
    {
        $this->event = $event;

        return $this;
    }

    public function then($wish) : bool
    {
        // See: <https://stackoverflow.com/questions/7067536/how-to-call-a-closure-that-is-a-class-variable>
        $result = ($this->event)($this->params);

        return $this->equals($result, $wish);
    }

    public function equals($result, $wish) : bool
    {
        // TODO

        return false;
    }
}
